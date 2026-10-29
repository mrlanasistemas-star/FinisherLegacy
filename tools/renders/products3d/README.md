# Product studio renders (three.js)

Regenerate: `node tools/renders/products3d/render.mjs [racepack|trisuit|chill-band[:angle]] [--out-png <dir>]` (prefix with `FL_FAST=1` for a quick half-size preview). Needs Chrome at `C:/Program Files/Google/Chrome/Application/chrome.exe`, PHP with GD/WebP, and `three` in node_modules — no network.

- `render.mjs` serves the repo root on a random local port, opens `stage.html?product=…&angle=…` in headless Chrome (WebGL through SwiftShader), renders at 2400×3000 and encodes 480/800/1200 WebP into `public/media/products/concepts/` with `tools/renders/towebp.php` (2× supersampling). Hero → `{key}-{w}.webp`, others → `{key}-{angle}-{w}.webp`.
- `lib.js`: studio (softbox PMREM environment, VSM key light + rim, blurred contact shadow, ACES, paper backdrop), procedural fabric maps (ripstop, knit, jersey, hex mesh), a triplanar/bump/AO material patcher, sweeps, an SDF mesher (surface nets) and auto-framing.
- `racepack.js`, `trisuit.js`, `chill-band.js`: one scene each; `build(lib, angle)` returns the object and camera view.
- The angles shown in the store are listed in `config/finisher.php` → `product_concepts.*.angles` (read by `Product::conceptGallery()`). A real photo uploaded in admin always replaces these.
