/**
 * Builds the conceptual product renders used by the public site.
 *
 *   node tools/renders/build.mjs [scene-name ...]
 *
 * Each scene is an HTML/SVG composition rendered by headless Chrome to PNG,
 * then encoded to WebP (via PHP GD) at the sizes listed. Output lands in
 * public/media/** — paths are referenced from resources/js/config/media.ts,
 * so a real photograph can replace a render by swapping the file only.
 */
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { scenes } from './scenes.mjs';

const root = path.resolve(path.dirname(new URL(import.meta.url).pathname.replace(/^\/(\w:)/, '$1')), '../..');
const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'fl-renders-'));
const chrome = [
    'C:/Program Files/Google/Chrome/Application/chrome.exe',
    '/usr/bin/google-chrome',
    '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
].find((p) => fs.existsSync(p));

if (!chrome) {
    throw new Error('Chrome not found — needed to rasterize the scenes.');
}

const only = process.argv.slice(2);

const page = (scene) => `<!doctype html><html><head><meta charset="utf-8">
<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=fraunces:400,500,600|instrument-sans:400,500,600,700" rel="stylesheet">
<style>
    html,body{margin:0;width:${scene.width}px;height:${scene.height}px;overflow:hidden;background:${scene.transparent ? 'transparent' : '#f8f7f3'}}
    *{box-sizing:border-box}
    ${scene.css ?? ''}
</style></head><body>${scene.html}</body></html>`;

for (const scene of scenes) {
    if (only.length && !only.includes(scene.name)) {
        continue;
    }

    const html = path.join(tmp, `${scene.name}.html`);
    const png = path.join(tmp, `${scene.name}.png`);
    fs.writeFileSync(html, page(scene));

    execFileSync(chrome, [
        '--headless=new',
        '--disable-gpu',
        '--hide-scrollbars',
        '--force-device-scale-factor=1',
        ...(scene.transparent ? ['--default-background-color=00000000'] : []),
        '--virtual-time-budget=4000',
        `--window-size=${scene.width},${scene.height}`,
        `--screenshot=${png}`,
        `file:///${html.replace(/\\/g, '/')}`,
    ], { stdio: 'ignore' });

    for (const width of scene.sizes) {
        const out = path.join(root, scene.out.replace('{w}', String(width)));
        fs.mkdirSync(path.dirname(out), { recursive: true });
        execFileSync('php', [path.join(root, 'tools/renders/towebp.php'), png, out, String(width), String(scene.quality ?? 82)], { stdio: 'inherit' });
    }

    console.log(`✓ ${scene.name}`);
}
