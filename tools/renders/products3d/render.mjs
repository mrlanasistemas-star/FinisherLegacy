/**
 * Studio product renders made with three.js, rasterized offline by headless
 * Chrome (WebGL via SwiftShader) and encoded to WebP with PHP GD.
 *
 *   node tools/renders/products3d/render.mjs                 # every product, every angle
 *   node tools/renders/products3d/render.mjs racepack        # one product
 *   node tools/renders/products3d/render.mjs trisuit:back    # one angle
 *   node tools/renders/products3d/render.mjs --preview ...   # also keep PNGs in --out-png dir
 *
 * How it works: a tiny static server exposes the repo root (so the scene
 * modules can import `three` straight from node_modules and the FL logo from
 * public/images), Chrome opens stage.html?product=…&angle=…, the scene
 * renders at 2× the largest output size, composites itself on the paper
 * backdrop and hands back a PNG data URL over CDP. towebp.php then
 * downsamples it (supersampling) to every size in SIZES.
 *
 * Output: public/media/products/concepts/{key}-{w}.webp for the hero angle
 * and {key}-{angle}-{w}.webp for the rest (see app/Models/Product.php).
 */
import { execFileSync, spawn } from 'node:child_process';
import fs from 'node:fs';
import http from 'node:http';
import os from 'node:os';
import path from 'node:path';

const here = path.dirname(new URL(import.meta.url).pathname.replace(/^\/(\w:)/, '$1'));
const root = path.resolve(here, '../../..');
const SIZES = [480, 800, 1200];
// FL_FAST=1 renders at half size (quick look while iterating; output is softer)
const RENDER = process.env.FL_FAST ? { width: 1200, height: 1500 } : { width: 2400, height: 3000 };

const PRODUCTS = {
    racepack: ['hero', 'front', 'back', 'detail'],
    trisuit: ['hero', 'back', 'side', 'detail'],
    'chill-band': ['hero', 'top', 'detail'],
};

const args = process.argv.slice(2);
const pngDirArg = args.indexOf('--out-png');
const pngDir = pngDirArg >= 0 ? args.splice(pngDirArg, 2)[1] : null;
const jobs = (args.length ? args : Object.keys(PRODUCTS)).flatMap((a) => {
    const [product, angle] = a.split(':');

    if (!PRODUCTS[product]) {
        throw new Error(`Unknown product ${product}`);
    }

    return (angle ? [angle] : PRODUCTS[product]).map((ang) => ({ product, angle: ang }));
});

// --- static server -----------------------------------------------------------
const types = { '.html': 'text/html', '.js': 'text/javascript', '.mjs': 'text/javascript', '.png': 'image/png', '.json': 'application/json' };
const server = http.createServer((req, res) => {
    const file = path.join(root, decodeURIComponent(new URL(req.url, 'http://x').pathname));

    if (!file.startsWith(root) || !fs.existsSync(file) || fs.statSync(file).isDirectory()) {
        res.writeHead(404).end();

        return;
    }

    res.writeHead(200, { 'Content-Type': types[path.extname(file)] ?? 'application/octet-stream', 'Cache-Control': 'no-store' });
    fs.createReadStream(file).pipe(res);
});
await new Promise((r) => server.listen(0, '127.0.0.1', r));
const base = `http://127.0.0.1:${server.address().port}`;

// --- chrome over CDP ---------------------------------------------------------
const chrome = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const port = 9800 + Math.floor(Math.random() * 150);
const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'fl-3d-'));
const proc = spawn(chrome, [
    '--headless=new', '--hide-scrollbars', '--use-angle=swiftshader', '--enable-unsafe-swiftshader', '--ignore-gpu-blocklist',
    `--remote-debugging-port=${port}`, `--user-data-dir=${profile}`, 'about:blank',
], { stdio: 'ignore' });
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

let wsUrl;

for (let i = 0; i < 60 && !wsUrl; i++) {
    await sleep(250);

    try {
        wsUrl = (await (await fetch(`http://127.0.0.1:${port}/json/list`)).json()).find((t) => t.type === 'page')?.webSocketDebuggerUrl;
    } catch {}
}

const ws = new WebSocket(wsUrl);
await new Promise((r) => ws.addEventListener('open', r));
let id = 0;
const pending = new Map();
ws.addEventListener('message', (e) => {
    const msg = JSON.parse(e.data);

    if (msg.method === 'Runtime.consoleAPICalled') {
        console.log('   [page]', msg.params.args.map((a) => a.value ?? a.description).join(' '));
    }

    if (msg.method === 'Runtime.exceptionThrown') {
        console.log('   [page error]', msg.params.exceptionDetails.exception?.description ?? msg.params.exceptionDetails.text);
    }

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

await send('Runtime.enable');
await send('Page.enable');
await send('Emulation.setDeviceMetricsOverride', { width: 600, height: 750, deviceScaleFactor: 1, mobile: false });

const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'fl-3d-png-'));

try {
    for (const { product, angle } of jobs) {
        const t0 = Date.now();
        await send('Page.navigate', { url: `${base}/tools/renders/products3d/stage.html?product=${product}&angle=${angle}&w=${RENDER.width}&h=${RENDER.height}` });
        let data = null;

        for (let i = 0; i < 600 && !data; i++) {
            await sleep(500);
            const r = await send('Runtime.evaluate', { expression: 'window.__result ?? null', returnByValue: true });
            data = r.result?.result?.value ?? null;
        }

        if (!data || !data.startsWith('data:image/png')) {
            throw new Error(`${product}:${angle} did not render (${String(data).slice(0, 200)})`);
        }

        const png = path.join(tmp, `${product}-${angle}.png`);
        fs.writeFileSync(png, Buffer.from(data.split(',')[1], 'base64'));

        if (pngDir) {
            fs.mkdirSync(pngDir, { recursive: true });
            execFileSync('php', ['-r', `$s=imagecreatefrompng($argv[1]);$d=imagescale($s,1200,1500,IMG_BICUBIC);imagepng($d,$argv[2]);`, png, path.join(pngDir, `${product}-${angle}.png`)]);
        }

        const name = angle === 'hero' ? product : `${product}-${angle}`;

        for (const w of SIZES) {
            execFileSync('php', [path.join(root, 'tools/renders/towebp.php'), png, path.join(root, `public/media/products/concepts/${name}-${w}.webp`), String(w), '86'], { stdio: 'inherit' });
        }

        console.log(`✓ ${product}:${angle} (${((Date.now() - t0) / 1000).toFixed(1)}s)`);
    }
} finally {
    ws.close();
    proc.kill();
    server.close();
}
