/**
 * QA screenshots at exact viewport widths (headless Chrome clamps window
 * width to ~500px, so small breakpoints need DevTools emulation).
 *
 *   node tools/renders/screenshot.mjs <url> <width> <out.png> [maxHeight] [cookieHeader]
 */
import { spawn } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';

const [url, width, out, maxHeight = '12000', cookie = ''] = process.argv.slice(2);
const chrome = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const port = 9300 + Math.floor(Math.random() * 500);
const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'fl-shot-'));
const proc = spawn(chrome, ['--headless=new', '--disable-gpu', '--hide-scrollbars', `--remote-debugging-port=${port}`, `--user-data-dir=${profile}`, 'about:blank'], { stdio: 'ignore' });
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

let wsUrl;

for (let i = 0; i < 50 && !wsUrl; i++) {
    await sleep(200);

    try {
        const list = await (await fetch(`http://127.0.0.1:${port}/json/list`)).json();
        wsUrl = list.find((t) => t.type === 'page')?.webSocketDebuggerUrl;
    } catch {}
}

const ws = new WebSocket(wsUrl);
await new Promise((r) => ws.addEventListener('open', r));
let id = 0;
const pending = new Map();
ws.addEventListener('message', (e) => {
    const msg = JSON.parse(e.data);

    if (msg.id && pending.has(msg.id)) {
        pending.get(msg.id)(msg);
        pending.delete(msg.id);
    }
});
const send = (method, params = {}) =>
    new Promise((resolve) => {
        const n = ++id;
        pending.set(n, resolve);
        ws.send(JSON.stringify({ id: n, method, params }));
    });

const w = Number(width);
await send('Emulation.setDeviceMetricsOverride', { width: w, height: 900, deviceScaleFactor: 1, mobile: w < 768 });

if (cookie) {
    await send('Network.enable');
    await send('Network.setExtraHTTPHeaders', { headers: { Cookie: cookie } });
}

await send('Page.enable');
await send('Page.navigate', { url });
await sleep(3500);

// Scroll through so lazy images load, then measure.
const { result } = await send('Runtime.evaluate', {
    expression: `(async()=>{for(let y=0;y<document.body.scrollHeight;y+=600){scrollTo(0,y);await new Promise(r=>setTimeout(r,120));}scrollTo(0,0);await new Promise(r=>setTimeout(r,800));return JSON.stringify({h:document.documentElement.scrollHeight,sw:document.documentElement.scrollWidth});})()`,
    awaitPromise: true,
    returnByValue: true,
});
const { h, sw } = JSON.parse(result.result.value);
const height = Math.min(h, Number(maxHeight));
await send('Emulation.setDeviceMetricsOverride', { width: w, height, deviceScaleFactor: 1, mobile: w < 768 });
await sleep(1200);
const shot = await send('Page.captureScreenshot', { format: 'png', clip: { x: 0, y: 0, width: w, height, scale: 1 } });
fs.writeFileSync(out, Buffer.from(shot.result.data, 'base64'));
console.log(`${out} ${w}x${height} (page ${h}px tall, scrollWidth ${sw}${sw > w ? ' ⚠ HORIZONTAL OVERFLOW' : ''})`);
ws.close();
proc.kill();
