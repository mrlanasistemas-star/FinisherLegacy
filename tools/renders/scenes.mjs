import { clipBack, plateBackBody, DEFAULT_SPEC, flMark, nfcGlyph, PLATE, plateDefs, plateFront, plateOnRibbonSvg, plateProfileSvg, plateSvg } from './plate.mjs';

/*
 * Conceptual product renders (not photographs). Every scene lists the
 * WebP widths it is exported at; resources/js/config/media.ts points at
 * these files. Legacy Plate V3: 70 × 45 mm Zamak niquelado, front design +
 * NFC under the FL panel, stainless money clip on the unprinted back.
 */

const { w: PW, h: PH, r: PR } = PLATE;

// Mineral/stone texture as an inline SVG background.
const stone = (tone = 'dark') => {
    const svg = `<svg xmlns='http://www.w3.org/2000/svg' width='600' height='600'><filter id='n'><feTurbulence type='fractalNoise' baseFrequency='.012 .02' numOctaves='5' seed='7'/><feColorMatrix values='0 0 0 0 ${tone === 'dark' ? '.5' : '.35'}  0 0 0 0 ${tone === 'dark' ? '.48' : '.32'}  0 0 0 0 ${tone === 'dark' ? '.44' : '.28'}  0 0 0 .55 0'/></filter><rect width='100%' height='100%' filter='url(%23n)'/></svg>`;

    return `url("data:image/svg+xml,${svg.replace(/#/g, '%23').replace(/"/g, "'")}")`;
};
const grain = `url("data:image/svg+xml,${`<svg xmlns='http://www.w3.org/2000/svg' width='300' height='300'><filter id='g'><feTurbulence type='fractalNoise' baseFrequency='.9' numOctaves='2'/><feColorMatrix values='0 0 0 0 0  0 0 0 0 0  0 0 0 0 0  0 0 0 .07 0'/></filter><rect width='100%' height='100%' filter='url(%23g)'/></svg>`}")`;

/** A plate with real thickness: stacked edge layers + the face, in CSS 3D. */
const plate3d = ({ id, face = 'front', data = {}, thickness = 9, width = 760, shadow = 'rgba(0,0,0,.8)', shadowOffset = [40, 40] }) => {
    const scale = width / PW;
    const edge = `<svg viewBox="0 0 ${PW} ${PH}" width="${width}" height="${PH * scale}"><defs>${plateDefs(id + 'e')}</defs><rect width="${PW}" height="${PH}" rx="${PR}" fill="url(#${id}e-edge)"/></svg>`;
    const layers = Array.from({ length: thickness }, (_, i) => `<div class="layer" style="transform:translateZ(${-(i + 1) * 1.6}px);filter:brightness(${0.9 - i * 0.05})">${edge}</div>`).join('');

    const sh = `<div class="layer" style="border-radius:${PR * scale}px;background:${shadow};filter:blur(${width / 30}px);transform:translate3d(${shadowOffset[0]}px,${shadowOffset[1]}px,${-(thickness + 4) * 1.6}px)"></div>`;

    return `<div class="plate3d" style="width:${width}px;height:${PH * scale}px;left:${-width / 2}px;top:${(-PH * scale) / 2}px">${sh}${layers}<div class="layer face">${plateSvg(face, id, data).replace(`width="${PW}" height="${PH}"`, `width="${width}" height="${PH * scale}"`)}</div></div>`;
};
const plate3dCss = `.plate3d{position:absolute;transform-style:preserve-3d}.plate3d .layer{position:absolute;inset:0}.plate3d .layer svg{display:block}`;

const studio = `background:radial-gradient(ellipse at 50% 35%,#fbf9f4 0%,#f0ebe1 55%,#e3dbcc 100%)`;

/* 1 / 3 — Orthographic front and back (back = clean metal + money clip). */
const plateOrtho = (face) => ({
    name: `plate-${face}`,
    width: 1600,
    height: 1100,
    sizes: [1600, 800],
    out: `public/media/brand/plate/legacy-plate-${face}-{w}.webp`,
    css: `body{${studio}}
          .wrap{position:absolute;inset:0;display:flex;align-items:center;justify-content:center}
          .wrap svg{width:1060px;height:auto;filter:drop-shadow(0 38px 38px rgba(40,32,20,.28)) drop-shadow(0 6px 8px rgba(40,32,20,.25))}`,
    html: `<div class="wrap">${plateSvg(face, face)}</div>`,
});

/* 2 — 3/4 perspective: thickness, brushed edge and resin. */
const perspective = {
    name: 'plate-perspective',
    width: 1600,
    height: 1100,
    sizes: [1600, 1000, 600],
    out: 'public/media/brand/plate/legacy-plate-perspective-{w}.webp',
    quality: 84,
    css: `${plate3dCss}body{${studio}}
        .scene{position:absolute;inset:0;perspective:2000px}
        .rig{position:absolute;left:50%;top:50%;transform-style:preserve-3d;transform:rotateX(46deg) rotateY(-18deg) rotateZ(-14deg)}
        .grain{position:absolute;inset:0;background:${grain};mix-blend-mode:multiply;opacity:.6}`,
    html: `<div class="scene"><div class="rig">${plate3d({ id: 'pv', width: 940, thickness: 22, shadow: 'rgba(60,45,20,.42)', shadowOffset: [40, 60] })}</div></div><div class="grain"></div>`,
};

/* 1b — Hero product shot on dark stone, soft side light. */
const hero = {
    name: 'plate-hero',
    width: 1800,
    height: 1200,
    sizes: [1800, 1200, 800],
    out: 'public/media/brand/plate/legacy-plate-hero-{w}.webp',
    quality: 84,
    css: `${plate3dCss}
        body{background:#151412}
        .stone{position:absolute;inset:0;background:${stone('dark')},radial-gradient(ellipse at 18% 20%,#4a453d 0%,#24221f 45%,#121110 100%);background-size:900px 900px,cover;background-blend-mode:overlay}
        .light{position:absolute;inset:0;background:radial-gradient(ellipse 60% 70% at 12% 30%,rgba(255,236,200,.28),transparent 60%),radial-gradient(ellipse at 100% 100%,rgba(0,0,0,.6),transparent 60%)}
        .scene{position:absolute;inset:0;perspective:2200px;perspective-origin:50% 20%}
        .rig{position:absolute;left:50%;top:52%;transform-style:preserve-3d;transform:rotateX(52deg) rotateZ(-17deg)}
        .grain{position:absolute;inset:0;background:${grain};mix-blend-mode:overlay}`,
    html: `<div class="stone"></div><div class="light"></div>
        <div class="scene"><div class="rig">${plate3d({ id: 'h', width: 1040, thickness: 22 })}</div></div>
        <div class="grain"></div>`,
};

/* Detail macro: metal edge, resin depth, FL panel. */
const macro = {
    name: 'plate-macro',
    width: 1400,
    height: 1400,
    sizes: [1400, 900, 600],
    out: 'public/media/brand/plate/legacy-plate-macro-{w}.webp',
    quality: 84,
    css: `${plate3dCss}
        body{background:#1a1917}
        .bg{position:absolute;inset:0;background:radial-gradient(ellipse at 70% 20%,#5a5246 0%,#23211e 50%,#0f0e0d 100%)}
        .scene{position:absolute;inset:0;perspective:1400px}
        .rig{position:absolute;left:40%;top:56%;transform-style:preserve-3d;transform:rotateX(38deg) rotateY(-14deg) rotateZ(-22deg)}
        .blur{position:absolute;inset:0;backdrop-filter:blur(7px);-webkit-mask:linear-gradient(115deg,#000 0%,transparent 34%,transparent 70%,#000 100%);mask:linear-gradient(115deg,#000 0%,transparent 34%,transparent 70%,#000 100%)}
        .grain{position:absolute;inset:0;background:${grain};mix-blend-mode:overlay}`,
    html: `<div class="bg"></div><div class="scene"><div class="rig">${plate3d({ id: 'm', width: 1900, thickness: 26 })}</div></div><div class="blur"></div><div class="grain"></div>`,
};

/* 4 — Clip macro: the stamped stainless money clip, close and angled. */
const clipMacro = {
    name: 'plate-clip-macro',
    width: 1400,
    height: 1400,
    sizes: [1400, 900, 600],
    out: 'public/media/brand/plate/legacy-plate-clip-macro-{w}.webp',
    quality: 84,
    css: `${plate3dCss}body{${studio}}
        .scene{position:absolute;inset:0;perspective:1500px}
        .rig{position:absolute;left:50%;top:50%;transform-style:preserve-3d;transform:rotateX(30deg) rotateY(12deg) rotateZ(14deg)}
        .vig{position:absolute;inset:0;background:radial-gradient(ellipse at 50% 50%,transparent 55%,rgba(60,45,20,.18) 100%)}
        .grain{position:absolute;inset:0;background:${grain};mix-blend-mode:multiply;opacity:.6}`,
    html: `<div class="scene"><div class="rig">${plate3d({ id: 'cm', face: 'back', width: 1500, thickness: 26, shadow: 'rgba(60,45,20,.4)', shadowOffset: [50, 70] })}</div></div><div class="vig"></div><div class="grain"></div>`,
};

/* 5 — Side profile: body + clip waves ≈ 6 mm in total. */
const profile = {
    name: 'plate-profile',
    width: 1600,
    height: 900,
    sizes: [1600, 800],
    out: 'public/media/brand/plate/legacy-plate-profile-{w}.webp',
    css: `body{${studio}}
        .wrap{position:absolute;left:120px;right:120px;top:250px}
        .wrap svg{width:100%;height:auto;filter:drop-shadow(0 18px 18px rgba(40,32,20,.18))}
        .cap{position:absolute;left:120px;top:110px;font-family:'Instrument Sans',sans-serif}
        .k{font:600 15px 'Instrument Sans';letter-spacing:.24em;color:#85662b;text-transform:uppercase}
        .t{font:500 44px Fraunces,serif;color:#171714;margin-top:10px}
        .legend{position:absolute;left:120px;bottom:90px;display:flex;gap:46px;font:500 19px 'Instrument Sans';color:#57534b}
        .legend b{color:#171714;font-weight:600}`,
    html: `<div class="cap"><div class="k">Perfil lateral</div><div class="t">Delgada, firme y lista para el listón.</div></div>
        <div class="wrap">${plateProfileSvg('pf', 'none', DEFAULT_SPEC, true)}</div>
        <div class="legend"><span><b>Cuerpo</b> · Zamak niquelado + resina</span><span><b>Clip</b> · acero inoxidable, 3 puntos de presión</span><span><b>Grosor total</b> · ≈ ${DEFAULT_SPEC.total_depth_mm} mm</span></div>`,
};

/* 6 — Ribbon inserting: back view, the ribbon slides under the clip. */
const ribbonBand = (x, y, w, h, id) => `<rect x="${x}" y="${y}" width="${w}" height="${h}" fill="url(#${id}-ribbon)"/><rect x="${x}" y="${y}" width="${w}" height="${h}" fill="#000" opacity=".06"/>`;
const ribbonInsert = {
    name: 'plate-ribbon-insert',
    width: 1400,
    height: 1400,
    sizes: [1400, 900, 600],
    out: 'public/media/brand/plate/legacy-plate-ribbon-insert-{w}.webp',
    quality: 84,
    css: `body{${studio}}.wrap{position:absolute;left:0;right:0;top:140px;height:820px;display:flex;align-items:center;justify-content:center}.wrap svg{width:1100px;height:100%}
        .inset{position:absolute;left:110px;right:110px;bottom:70px;background:rgba(255,255,255,.8);border-radius:28px;padding:22px 30px;box-shadow:0 10px 30px rgba(40,32,20,.08)}
        .inset svg{width:100%;height:auto}
        .k{position:absolute;left:110px;top:90px;font:600 15px 'Instrument Sans';letter-spacing:.24em;color:#85662b;text-transform:uppercase}`,
    html: `<div class="k">Inserta el listón</div><div class="wrap"><svg viewBox="-60 -170 820 790" preserveAspectRatio="xMidYMid meet"><defs>${plateDefs('ri')}<marker id="ri-ar" viewBox="0 0 10 10" refX="5" refY="5" markerWidth="4" markerHeight="4" orient="auto"><path d="M0 0 L10 5 L0 10 z" fill="#171714"/></marker></defs>
        <g filter="url(#ri-drop)">${plateBackBody('ri')}</g>
        ${ribbonBand(530, -170, 150, 790, 'ri')}
        <g>${clipBack('ri')}</g>
        <path d="M760 540 L560 540" stroke="#171714" stroke-width="7" marker-end="url(#ri-ar)"/>
        </svg></div>
        <div class="inset">${plateProfileSvg('rip', 'inserting', DEFAULT_SPEC, false)}</div>`,
};

/* 7 — Ribbon held: the plate worn on the medal ribbon. */
const ribbonHeld = {
    name: 'plate-ribbon-held',
    width: 1200,
    height: 1500,
    sizes: [1200, 800, 480],
    out: 'public/media/brand/plate/legacy-plate-ribbon-held-{w}.webp',
    quality: 84,
    css: `body{${studio}}.wrap{position:absolute;inset:60px 120px;display:flex;align-items:center;justify-content:center}.wrap svg{height:100%;width:auto;filter:drop-shadow(0 30px 30px rgba(40,32,20,.22))}
        .grain{position:absolute;inset:0;background:${grain};mix-blend-mode:multiply;opacity:.5}`,
    html: `<div class="wrap">${plateOnRibbonSvg('rh')}</div><div class="grain"></div>`,
};

/* 8 — NFC: the phone approaches the FRONT of the plate. */
const nfc = {
    name: 'plate-nfc',
    width: 1400,
    height: 1400,
    sizes: [1400, 900, 600],
    out: 'public/media/brand/plate/legacy-plate-nfc-{w}.webp',
    quality: 84,
    css: `${plate3dCss}
        body{${studio}}
        .scene{position:absolute;inset:0;perspective:2000px}
        .rig{position:absolute;left:46%;top:62%;transform-style:preserve-3d;transform:rotateX(50deg) rotateZ(-12deg)}
        .phone{position:absolute;left:62%;top:30%;width:380px;height:780px;border-radius:58px;background:linear-gradient(140deg,#3a3936,#121211 40%,#1d1c1a);transform:translate(-50%,-50%) rotateX(36deg) rotateZ(28deg);box-shadow:0 0 0 3px #4d4b46 inset, 60px 90px 90px rgba(40,30,15,.32)}
        .screen{position:absolute;inset:14px;border-radius:46px;background:radial-gradient(circle at 50% 38%,#2b2924 0%,#0b0b0a 70%);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:26px}
        .ring{width:170px;height:170px;border-radius:50%;border:2px solid rgba(201,164,92,.55);display:flex;align-items:center;justify-content:center;box-shadow:0 0 60px rgba(201,164,92,.25)}
        .bar{width:140px;height:8px;border-radius:8px;background:rgba(255,255,255,.12)}
        .bar.s{width:96px}
        .waves{position:absolute;left:66%;top:52%;transform:translate(-50%,-50%) rotate(-24deg)}`,
    html: `<div class="scene">
        <div class="rig">${plate3d({ id: 'n', width: 760, thickness: 18, shadow: 'rgba(60,45,20,.35)', shadowOffset: [30, 50] })}</div>
        <svg class="waves" width="420" height="300" viewBox="0 0 420 300" fill="none" stroke="#c9a45c" stroke-linecap="round">
            <path d="M150 210 Q210 250 270 210" stroke-width="5" opacity=".9"/>
            <path d="M115 228 Q210 300 305 228" stroke-width="4" opacity=".6"/>
            <path d="M80 246 Q210 350 340 246" stroke-width="3.5" opacity=".35"/>
        </svg>
        <div class="phone"><div class="screen"><div class="ring"><svg width="86" height="42" viewBox="0 0 1400 671">${flMark(0, 0, 1400, '#c9a45c')}</svg></div><div class="bar"></div><div class="bar s"></div></div></div>
    </div>`,
};

/* 9 — Exploded view: resin/graphic · NFC inlay · ferrite · Zamak body · stainless clip. */
const layerSvg = (inner, w = PW, h = PH) => `<svg viewBox="-20 -20 ${w + 40} ${h + 40}" width="${(w + 40) * 1.2}" height="${(h + 40) * 1.2}">${inner}</svg>`;
const panelX = 30 + (PW - 60 - Math.round(PW * 0.2) - 18) + 18;
const panelW = Math.round(PW * 0.2);
const coil = Array.from({ length: 7 }, (_, i) => `<rect x="${panelX + 14 + i * 7}" y="${70 + i * 7}" width="${panelW - 28 - i * 14}" height="${PH - 200 - i * 14}" rx="${20 - i * 2}" fill="none" stroke="#c07a3a" stroke-width="2.6"/>`).join('');
const exploded = {
    name: 'plate-exploded',
    width: 1800,
    height: 1700,
    sizes: [1800, 1200, 800],
    out: 'public/media/brand/plate/legacy-plate-exploded-{w}.webp',
    css: `body{background:linear-gradient(180deg,#fbfaf6,#f1ece2)}
        .scene{position:absolute;left:0;top:0;width:1100px;height:1700px;perspective:3000px}
        .rig{position:absolute;left:47%;top:84%;transform-style:preserve-3d;transform:rotateX(58deg) rotateZ(-28deg)}
        .l{position:absolute;left:0;top:0;transform-style:preserve-3d}
        .l svg{display:block;filter:drop-shadow(0 18px 14px rgba(60,45,20,.18))}
        .labels{position:absolute;right:70px;top:0;bottom:0;width:600px;font-family:'Instrument Sans',sans-serif}
        .lab{position:absolute;left:0;display:flex;gap:22px;align-items:flex-start}
        .n{font:600 15px 'Instrument Sans';color:#85662b;letter-spacing:.2em;border:1.5px solid #c9a45c;border-radius:999px;width:46px;height:46px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
        .t{font:500 34px Fraunces,serif;color:#171714;line-height:1.1}
        .m{display:inline-block;margin-top:8px;font:600 13px 'Instrument Sans';letter-spacing:.14em;text-transform:uppercase;color:#fff;background:#171714;border-radius:999px;padding:5px 12px}
        .m.s{background:#5f676d}
        .d{font:400 18px 'Instrument Sans';color:#6b675f;margin-top:8px;line-height:1.45;max-width:440px}`,
    html: `<div class="scene"><div class="rig">
        <div class="l" style="transform:translate(-440px,-290px) translateZ(1180px)">${layerSvg(`<defs>${plateDefs('x1')}</defs><rect width="${PW}" height="${PH}" rx="${PR}" fill="#fff" fill-opacity=".3" stroke="#e8dcc4"/><g opacity=".93">${plateFront('x1').replace(/<rect width="700"[^>]*\/>/, '')}</g><rect width="${PW}" height="${PH}" rx="${PR}" fill="url(#x1-gloss)"/>`)}</div>
        <div class="l" style="transform:translate(-440px,-290px) translateZ(900px)">${layerSvg(`<rect x="${panelX}" y="40" width="${panelW}" height="${PH - 80}" rx="22" fill="#f4efe4" fill-opacity=".92" stroke="#d8cdb6"/>${coil}<rect x="${panelX + panelW / 2 - 12}" y="${PH / 2 - 12}" width="24" height="24" rx="4" fill="#2a2926"/>`)}</div>
        <div class="l" style="transform:translate(-440px,-290px) translateZ(660px)">${layerSvg(`<defs><linearGradient id="fe" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#fff" stop-opacity=".18"/><stop offset="1" stop-color="#000" stop-opacity=".2"/></linearGradient></defs><rect x="${panelX - 6}" y="34" width="${panelW + 12}" height="${PH - 68}" rx="24" fill="#3b3a37"/><rect x="${panelX - 6}" y="34" width="${panelW + 12}" height="${PH - 68}" rx="24" fill="url(#fe)"/>`)}</div>
        <div class="l" style="transform:translate(-440px,-290px) translateZ(380px)">${layerSvg(`<defs>${plateDefs('x4')}</defs><rect width="${PW}" height="${PH}" rx="${PR}" fill="url(#x4-edge)"/><rect x="6" y="5" width="${PW - 12}" height="${PH - 13}" rx="${PR - 5}" fill="url(#x4-face)"/><rect x="6" y="5" width="${PW - 12}" height="${PH - 13}" rx="${PR - 5}" fill="#000" filter="url(#x4-brush)"/><rect x="30" y="30" width="${panelX - 48}" height="${PH - 66}" rx="20" fill="#000" opacity=".12" filter="url(#x4-inset)"/><rect x="${panelX}" y="30" width="${panelW}" height="${PH - 66}" rx="20" fill="#000" opacity=".16" filter="url(#x4-inset)"/>`)}</div>
        <div class="l" style="transform:translate(-440px,-290px) translateZ(0)">${layerSvg(`<defs>${plateDefs('x5')}</defs>${clipBack('x5')}`)}</div>
    </div></div>
    <div class="labels">
        <div class="lab" style="top:150px"><span class="n">01</span><div><div class="t">Resina y diseño frontal</div><div class="d">Tu nombre, tiempo, distancia y ritmo, protegidos con resina de brillo controlado.</div></div></div>
        <div class="lab" style="top:430px"><span class="n">02</span><div><div class="t">Inlay NFC</div><div class="d">Antena pasiva bajo el panel FL del frente: abre tu Legacy al acercar el teléfono.</div></div></div>
        <div class="lab" style="top:680px"><span class="n">03</span><div><div class="t">Ferrita anti-metal</div><div class="d">Aísla la antena del metal para que la lectura funcione.</div></div></div>
        <div class="lab" style="top:930px"><span class="n">04</span><div><div class="t">Cuerpo</div><span class="m">Zamak niquelado</span><div class="d">Pieza metálica de ${DEFAULT_SPEC.width_mm} × ${DEFAULT_SPEC.height_mm} mm con peso real y acabado ligeramente cepillado.</div></div></div>
        <div class="lab" style="top:1260px"><span class="n">05</span><div><div class="t">Clip posterior</div><span class="m s">Acero inoxidable</span><div class="d">Money clip estampado (${DEFAULT_SPEC.clip_length_mm} × ${DEFAULT_SPEC.clip_height_mm} mm) que sujeta la placa al listón de la medalla. Sin diseño atrás.</div></div></div>
    </div>`,
};
/* ---------- Store concept renders (4:5) ---------- */

const productScene = (name, svgInner, extraDefs = '') => ({
    name: `product-${name}`,
    width: 1200,
    height: 1500,
    sizes: [1200, 800, 480],
    out: `public/media/products/concepts/${name}-{w}.webp`,
    quality: 84,
    css: `body{${studio}}.grain{position:absolute;inset:0;background:${grain};mix-blend-mode:multiply;opacity:.6}svg.p{position:absolute;inset:0}`,
    html: `<svg class="p" viewBox="0 0 1200 1500" width="1200" height="1500"><defs>
        <filter id="soft" x="-30%" y="-30%" width="160%" height="160%"><feGaussianBlur stdDeviation="26"/></filter>
        <filter id="knit"><feTurbulence type="fractalNoise" baseFrequency="1.1 .35" numOctaves="2" seed="3"/><feColorMatrix values="0 0 0 0 1  0 0 0 0 1  0 0 0 0 1  0 0 0 .09 0"/><feComposite in2="SourceGraphic" operator="in"/></filter>
        <linearGradient id="gold" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#f1dca3"/><stop offset=".5" stop-color="#c9a45c"/><stop offset="1" stop-color="#94733a"/></linearGradient>
        <linearGradient id="ink" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#3a3936"/><stop offset=".55" stop-color="#1c1b19"/><stop offset="1" stop-color="#121211"/></linearGradient>
        <linearGradient id="inkV" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#3b3a37"/><stop offset="1" stop-color="#161615"/></linearGradient>
        <linearGradient id="shine" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#fff" stop-opacity="0"/><stop offset=".35" stop-color="#fff" stop-opacity=".13"/><stop offset=".5" stop-color="#fff" stop-opacity="0"/></linearGradient>
        ${extraDefs}</defs>${svgInner}</svg><div class="grain"></div>`,
});

const floor = (cx, cy, rx, ry, o = 0.28) => `<ellipse cx="${cx}" cy="${cy}" rx="${rx}" ry="${ry}" fill="#3d3020" opacity="${o}" filter="url(#soft)"/>`;

/** Ring/band seen from slightly above: cylinder of height h. */
const band = ({ cx, cy, rx, ry, h, fill, inner, stripe }) => {
    const top = cy;
    const bot = cy + h;

    return `
    <clipPath id="open${cy}"><ellipse cx="${cx}" cy="${top}" rx="${rx - 6}" ry="${ry - 3}"/></clipPath>
    <ellipse cx="${cx}" cy="${top}" rx="${rx}" ry="${ry}" fill="#e7dfd0"/>
    <path clip-path="url(#open${cy})" d="M${cx - rx} ${top} A${rx} ${ry} 0 0 1 ${cx + rx} ${top} L${cx + rx} ${bot} A${rx} ${ry} 0 0 0 ${cx - rx} ${bot} Z" fill="${inner}"/>
    <path d="M${cx - rx} ${top} A${rx} ${ry} 0 0 0 ${cx + rx} ${top} L${cx + rx} ${bot} A${rx} ${ry} 0 0 1 ${cx - rx} ${bot} Z" fill="${fill}"/>
    <path d="M${cx - rx} ${top} A${rx} ${ry} 0 0 0 ${cx + rx} ${top} L${cx + rx} ${bot} A${rx} ${ry} 0 0 1 ${cx - rx} ${bot} Z" fill="#000" filter="url(#knit)"/>
    <path d="M${cx - rx} ${top} A${rx} ${ry} 0 0 0 ${cx + rx} ${top} L${cx + rx} ${bot} A${rx} ${ry} 0 0 1 ${cx - rx} ${bot} Z" fill="url(#shine)"/>
    ${stripe ? `<path d="M${cx - rx} ${top + h * 0.82} A${rx} ${ry} 0 0 0 ${cx + rx} ${top + h * 0.82}" fill="none" stroke="url(#gold)" stroke-width="5"/>` : ''}
    <ellipse cx="${cx}" cy="${top}" rx="${rx}" ry="${ry}" fill="none" stroke="#000" stroke-opacity=".35" stroke-width="2"/>`;
};

const chillBand = productScene(
    'chill-band',
    `${floor(600, 1050, 420, 70)}
    ${band({ cx: 600, cy: 760, rx: 380, ry: 150, h: 170, fill: 'url(#ink)', inner: '#0f0f0e', stripe: true })}
    ${flMark({ x: 548, y: 958, width: 104, fill: 'url(#gold)' })}`,
);

const brimDefs = '<linearGradient id="brim" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#4a4844"/><stop offset="1" stop-color="#1f1e1c"/></linearGradient>';
const visor = productScene(
    'visor',
    `${floor(600, 1000, 420, 60)}
    <g transform="rotate(-7 600 760)">
    ${band({ cx: 690, cy: 690, rx: 290, ry: 120, h: 78, fill: 'url(#ink)', inner: '#141413' })}
    <path d="M470 780 C380 800 250 850 170 930 C300 990 500 960 640 860 C600 830 520 800 470 780 Z" fill="url(#brim)"/>
    <path d="M170 930 C300 990 500 960 640 860" fill="none" stroke="#000" stroke-opacity=".55" stroke-width="4"/>
    <path d="M205 920 C320 965 480 940 600 862" fill="none" stroke="#c9a45c" stroke-opacity=".6" stroke-width="2" stroke-dasharray="7 8"/>
    <path d="M470 780 C380 800 250 850 170 930" fill="none" stroke="#fff" stroke-opacity=".12" stroke-width="3"/>
    ${flMark({ x: 690, y: 790, width: 66, fill: 'url(#gold)' })}
    </g>`,
    brimDefs,
);

const cap = productScene(
    'cap',
    `${floor(620, 1110, 440, 85)}
    <path d="M260 860 C250 560 430 400 640 400 C850 400 990 560 960 860 C820 905 450 905 260 860 Z" fill="url(#ink)"/>
    <path d="M260 860 C250 560 430 400 640 400 C850 400 990 560 960 860 C820 905 450 905 260 860 Z" fill="#000" filter="url(#knit)"/>
    <path d="M640 402 C560 520 545 700 560 860" fill="none" stroke="#000" stroke-opacity=".55" stroke-width="3"/>
    <path d="M640 402 C760 520 800 700 790 860" fill="none" stroke="#000" stroke-opacity=".45" stroke-width="3"/>
    <path d="M300 760 C420 640 560 560 640 402" fill="none" stroke="#000" stroke-opacity=".35" stroke-width="2.5"/>
    <ellipse cx="640" cy="404" rx="22" ry="10" fill="#2a2927"/>
    <path d="M260 860 C190 905 150 990 230 1012 C420 1062 760 1002 905 902 C930 884 950 870 960 860 C820 905 450 905 260 860 Z" fill="url(#inkV)"/>
    <path d="M260 860 C450 905 820 905 960 860" fill="none" stroke="#000" stroke-opacity=".55" stroke-width="4"/><path d="M250 900 C420 960 760 950 900 905" fill="none" stroke="#c9a45c" stroke-opacity=".45" stroke-width="2" stroke-dasharray="7 8"/>
    <path d="M300 530 C420 420 560 405 640 402" fill="none" stroke="#fff" stroke-opacity=".08" stroke-width="40" stroke-linecap="round"/>
    ${flMark({ x: 580, y: 610, width: 120, fill: 'url(#gold)' })}`,
);

const trisuit = productScene(
    'trisuit',
    `${floor(600, 1380, 330, 40, 0.2)}
    <path id="ts" d="M455 150 C470 230 520 270 600 270 C680 270 730 230 745 150 L800 165 C790 260 800 330 820 420 C840 520 845 640 840 760 C860 900 880 1050 900 1250 L680 1290 C660 1180 630 1080 600 1010 C570 1080 540 1180 520 1290 L300 1250 C320 1050 340 900 360 760 C355 640 360 520 380 420 C400 330 410 260 400 165 Z" fill="url(#ink)"/>
    <path d="M455 150 C470 230 520 270 600 270 C680 270 730 230 745 150 L800 165 C790 260 800 330 820 420 C840 520 845 640 840 760 C860 900 880 1050 900 1250 L680 1290 C660 1180 630 1080 600 1010 C570 1080 540 1180 520 1290 L300 1250 C320 1050 340 900 360 760 C355 640 360 520 380 420 C400 330 410 260 400 165 Z" fill="#000" filter="url(#knit)"/>
    <path d="M380 420 C360 560 356 700 362 760 C340 900 322 1050 300 1250 L342 1257 C362 1060 382 900 404 760 C398 650 400 540 418 430 Z" fill="url(#gold)" opacity=".85"/>
    <path d="M820 420 C840 560 844 700 838 760 C860 900 878 1050 900 1250 L858 1257 C838 1060 818 900 796 760 C802 650 800 540 782 430 Z" fill="url(#gold)" opacity=".85"/>
    <path d="M600 272 L600 560" stroke="#c9a45c" stroke-width="3"/>
    <rect x="592" y="272" width="16" height="22" rx="3" fill="#c9a45c"/>
    <path d="M362 760 C460 790 740 790 838 760" fill="none" stroke="#000" stroke-opacity=".4" stroke-width="3"/>
    <path d="M300 1250 L520 1290 M680 1290 L900 1250" stroke="#c9a45c" stroke-opacity=".6" stroke-width="4"/>
    <path d="M430 300 C470 420 480 600 470 760" fill="none" stroke="#fff" stroke-opacity=".06" stroke-width="60"/>
    ${flMark({ x: 664, y: 360, width: 70, fill: 'url(#gold)' })}`,
);

const jersey = productScene(
    'jersey',
    `${floor(600, 1310, 380, 40, 0.2)}
    <path d="M470 250 C520 300 680 300 730 250 L900 320 L1040 560 L900 640 L860 580 L870 1220 C760 1250 440 1250 330 1220 L340 580 L300 640 L160 560 L300 320 Z" fill="url(#ink)"/>
    <path d="M470 250 C520 300 680 300 730 250 L900 320 L1040 560 L900 640 L860 580 L870 1220 C760 1250 440 1250 330 1220 L340 580 L300 640 L160 560 L300 320 Z" fill="#000" filter="url(#knit)"/>
    <path d="M470 250 C520 300 680 300 730 250 C690 330 510 330 470 250 Z" fill="#0d0d0c"/>
    <path d="M470 250 L340 580 M730 250 L860 580" stroke="#c9a45c" stroke-opacity=".75" stroke-width="3"/>
    <path d="M160 560 L300 640 M1040 560 L900 640" stroke="#c9a45c" stroke-width="5"/>
    <path d="M330 1200 C440 1230 760 1230 870 1200" fill="none" stroke="#c9a45c" stroke-opacity=".5" stroke-width="2.5"/>
    <path d="M420 380 C440 600 430 900 420 1180" fill="none" stroke="#fff" stroke-opacity=".05" stroke-width="80"/>
    ${flMark({ x: 664, y: 400, width: 74, fill: 'url(#gold)' })}
    <text x="600" y="1150" text-anchor="middle" font-family="Instrument Sans" font-size="18" letter-spacing="9" fill="#c9a45c" opacity=".8">FINISHER LEGACY</text>`,
);

const sock = (x, flip = false) => `<g transform="translate(${x} 0) ${flip ? 'scale(-1 1) translate(-380 0)' : ''}">
    <path d="M60 250 L300 250 L290 830 C288 890 312 925 365 948 L505 1004 C585 1036 596 1150 520 1185 C430 1226 232 1206 140 1150 C70 1108 58 1040 70 950 Z" fill="url(#ink)"/>
    <path d="M60 250 L300 250 L290 830 C288 890 312 925 365 948 L505 1004 C585 1036 596 1150 520 1185 C430 1226 232 1206 140 1150 C70 1108 58 1040 70 950 Z" fill="#000" filter="url(#knit)"/>
    <rect x="60" y="250" width="240" height="70" fill="#121211"/>${Array.from({ length: 11 }, (_, i) => `<line x1="${72 + i * 21}" y1="252" x2="${72 + i * 21}" y2="318" stroke="#000" stroke-opacity=".45" stroke-width="3"/>`).join('')}
    <path d="M60 300 L300 300" stroke="url(#gold)" stroke-width="5"/>
    <path d="M440 990 C500 1000 570 1060 540 1150" fill="none" stroke="#c9a45c" stroke-opacity=".45" stroke-width="3"/>
    <path d="M100 330 L100 1000" stroke="#fff" stroke-opacity=".05" stroke-width="40"/>
    ${flMark({ x: 148, y: 400, width: 46, fill: 'url(#gold)' })}
</g>`;
const socks = productScene('socks', `${floor(600, 1290, 430, 50, 0.22)}<g transform="translate(-40 -10)">${sock(150)}</g><g transform="translate(30 40) rotate(4 700 700)">${sock(500)}</g>`);

const backpack = productScene(
    'backpack',
    `${floor(600, 1330, 380, 60)}
    <path d="M520 240 C520 170 680 170 680 240" fill="none" stroke="#1c1b19" stroke-width="26" stroke-linecap="round"/>
    <path d="M330 420 C330 300 420 240 600 240 C780 240 870 300 870 420 L890 1180 C890 1260 840 1300 760 1300 L440 1300 C360 1300 310 1260 310 1180 Z" fill="url(#ink)"/>
    <path d="M330 420 C330 300 420 240 600 240 C780 240 870 300 870 420 L890 1180 C890 1260 840 1300 760 1300 L440 1300 C360 1300 310 1260 310 1180 Z" fill="#000" filter="url(#knit)"/>
    <path d="M340 420 C420 330 780 330 860 420" fill="none" stroke="#c9a45c" stroke-opacity=".55" stroke-width="3"/>
    <rect x="400" y="760" width="400" height="430" rx="70" fill="#121211"/>
    <rect x="400" y="760" width="400" height="430" rx="70" fill="none" stroke="#000" stroke-opacity=".5" stroke-width="3"/>
    <path d="M440 800 C520 770 680 770 760 800" fill="none" stroke="#c9a45c" stroke-width="3"/>
    <rect x="752" y="790" width="14" height="40" rx="4" fill="#c9a45c"/>
    <path d="M330 640 L870 640 M320 980 L880 980" stroke="#0d0d0c" stroke-width="18"/>
    <rect x="530" y="470" width="140" height="100" rx="16" fill="#0d0d0c"/>
    ${flMark({ x: 552, y: 497, width: 96, fill: 'url(#gold)' })}
    <path d="M380 440 C370 700 370 1000 380 1200" fill="none" stroke="#fff" stroke-opacity=".06" stroke-width="60"/>`,
);

const keychain = productScene(
    'nfc-keychain',
    `${floor(640, 860, 360, 46, 0.32)}
    <g transform="translate(170 640) rotate(-10) scale(1.3)">
        <circle cx="40" cy="105" r="78" fill="none" stroke="#b9a479" stroke-width="14"/>
        <circle cx="40" cy="105" r="78" fill="none" stroke="#f4ead2" stroke-width="4" stroke-dasharray="120 380" stroke-linecap="round"/>
        <g transform="translate(90 0) scale(1.05)">
            <defs>${plateDefs('k')}</defs>
            <rect width="560" height="210" rx="${PR}" fill="url(#k-edge)"/>
            <rect x="5" y="4" width="550" height="199" rx="${PR - 4}" fill="url(#k-face)"/>
            <rect x="5" y="4" width="550" height="199" rx="${PR - 4}" fill="none" stroke="url(#k-bevel)" stroke-width="3"/>
            <circle cx="46" cy="105" r="17" fill="#e7dfd0" stroke="#8e7a56" stroke-width="3"/>
            <rect x="96" y="24" width="440" height="158" rx="13" fill="url(#k-black)"/>
            ${flMark({ x: 236, y: 60, width: 170, fill: 'url(#k-gold)' })}
            ${nfcGlyph({ x: 498, y: 148, size: 20, color: '#c9a45c', opacity: 0.75 })}
            <rect x="96" y="24" width="440" height="158" rx="13" fill="url(#k-gloss)" opacity=".55"/>
            <rect x="5" y="4" width="550" height="199" rx="${PR - 4}" fill="url(#k-sheen)" opacity=".5"/>
        </g>
    </g>`,
);

const plateProduct = {
    ...productScene('legacy-plate', ''),
    css: `${plate3dCss}body{${studio}}
        .scene{position:absolute;inset:0;perspective:2400px}
        .rig{position:absolute;left:48%;top:50%;transform-style:preserve-3d;transform:rotateX(50deg) rotateZ(-20deg)}
        .grain{position:absolute;inset:0;background:${grain};mix-blend-mode:multiply;opacity:.6}`,
    html: `<div class="scene"><div class="rig">${plate3d({ id: 'pp', width: 900, thickness: 22, shadow: 'rgba(60,45,20,.45)', shadowOffset: [26, 40] })}</div></div><div class="grain"></div>`,
};

/* Floating plate on transparent background (overlays photography). */
const plateFloat = {
    name: 'plate-float',
    width: 1200,
    height: 900,
    transparent: true,
    sizes: [1200, 700],
    out: 'public/media/brand/plate/legacy-plate-float-{w}.webp',
    quality: 86,
    css: `${plate3dCss}body{background:transparent}
        .scene{position:absolute;inset:0;perspective:2200px}
        .rig{position:absolute;left:50%;top:48%;transform-style:preserve-3d;transform:rotateX(34deg) rotateY(-12deg) rotateZ(-9deg)}`,
    html: `<div class="scene"><div class="rig">${plate3d({ id: 'f', width: 800, thickness: 20, shadow: 'rgba(0,0,0,.55)', shadowOffset: [30, 60] })}</div></div>`,
};

/* Portrait card crop: plate diagonal on dark stone, room for copy below. */
const portrait = {
    ...hero,
    name: 'plate-portrait',
    width: 1000,
    height: 1400,
    sizes: [1000, 640],
    out: 'public/media/brand/plate/legacy-plate-portrait-{w}.webp',
    css: hero.css.replace('.rig{position:absolute;left:50%;top:52%;transform-style:preserve-3d;transform:rotateX(52deg) rotateZ(-17deg)}', '.rig{position:absolute;left:52%;top:30%;transform-style:preserve-3d;transform:rotateX(48deg) rotateZ(-30deg)}'),
    html: `<div class="stone"></div><div class="light"></div>
        <div class="scene"><div class="rig">${plate3d({ id: 'po', width: 660, thickness: 20 })}</div></div>
        <div class="grain"></div>`,
};

export const scenes = [
    portrait,
    plateFloat,
    plateOrtho('front'),
    plateOrtho('back'),
    perspective,
    hero,
    macro,
    clipMacro,
    profile,
    ribbonInsert,
    ribbonHeld,
    nfc,
    exploded,
    plateProduct,
    keychain,
    chillBand,
    visor,
    cap,
    trisuit,
    jersey,
    socks,
    backpack,
];
