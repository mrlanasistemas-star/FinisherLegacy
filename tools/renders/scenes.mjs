import { flMark, nfcGlyph, PLATE, plateDefs, plateFront, plateSvg } from './plate.mjs';

/*
 * Conceptual product renders (not photographs). Every scene lists the
 * WebP widths it is exported at; resources/js/config/media.ts points at
 * these files.
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

const plateOrtho = (face) => ({
    name: `plate-${face}`,
    width: 1600,
    height: 800,
    sizes: [1600, 800],
    out: `public/media/brand/plate/legacy-plate-${face}-{w}.webp`,
    css: `body{${studio}}
          .wrap{position:absolute;inset:0;display:flex;align-items:center;justify-content:center}
          .wrap svg{width:1180px;height:auto;filter:drop-shadow(0 38px 38px rgba(40,32,20,.28)) drop-shadow(0 6px 8px rgba(40,32,20,.25))}`,
    html: `<div class="wrap">${plateSvg(face, face)}</div>`,
});

/* 1 — Hero product shot on dark stone, soft side light. */
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
        .rig{position:absolute;left:50%;top:52%;transform-style:preserve-3d;transform:rotateX(56deg) rotateZ(-17deg)}
        .shadow{position:absolute;inset:-10px;border-radius:40px;background:rgba(0,0,0,.85);filter:blur(28px);transform:translate3d(46px,40px,-16px)}
        .grain{position:absolute;inset:0;background:${grain};mix-blend-mode:overlay}`,
    html: `<div class="stone"></div><div class="light"></div>
        <div class="scene"><div class="rig">${plate3d({ id: 'h', width: 980, thickness: 10 })}</div></div>
        <div class="grain"></div>`,
};

/* 2 — Macro: metal edge, resin depth, FL panel. */
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
        .rig{position:absolute;left:46%;top:58%;transform-style:preserve-3d;transform:rotateX(38deg) rotateY(-14deg) rotateZ(-22deg)}
        .blur{position:absolute;inset:0;backdrop-filter:blur(7px);-webkit-mask:linear-gradient(115deg,#000 0%,transparent 34%,transparent 70%,#000 100%);mask:linear-gradient(115deg,#000 0%,transparent 34%,transparent 70%,#000 100%)}
        .grain{position:absolute;inset:0;background:${grain};mix-blend-mode:overlay}`,
    html: `<div class="bg"></div><div class="scene"><div class="rig">${plate3d({ id: 'm', width: 2300, thickness: 14 })}</div></div><div class="blur"></div><div class="grain"></div>`,
};

/* 3 — NFC: the plate approaching a phone, discreet waves. */
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
        .phone{position:absolute;left:50%;top:58%;width:430px;height:880px;border-radius:64px;background:linear-gradient(140deg,#3a3936,#121211 40%,#1d1c1a);transform:translate(-50%,-50%) rotateX(48deg) rotateZ(24deg);box-shadow:0 0 0 3px #4d4b46 inset, 60px 80px 90px rgba(40,30,15,.35)}
        .screen{position:absolute;inset:16px;border-radius:50px;background:radial-gradient(circle at 50% 38%,#2b2924 0%,#0b0b0a 70%);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:28px}
        .ring{width:190px;height:190px;border-radius:50%;border:2px solid rgba(201,164,92,.55);display:flex;align-items:center;justify-content:center;box-shadow:0 0 60px rgba(201,164,92,.25)}
        .bar{width:150px;height:8px;border-radius:8px;background:rgba(255,255,255,.12)}
        .bar.s{width:100px}
        .rig{position:absolute;left:50%;top:27%;transform-style:preserve-3d;transform:rotateX(40deg) rotateZ(14deg) translateZ(140px)}
        .shadow{position:absolute;inset:0;border-radius:30px;background:rgba(40,30,15,.35);filter:blur(30px);transform:translate3d(30px,160px,-160px)}
        .waves{position:absolute;left:50%;top:27%;transform:translate(-50%,-50%) rotate(8deg)}`,
    html: `<div class="scene">
        <div class="phone"><div class="screen"><div class="ring"><svg width="96" height="46" viewBox="0 0 1400 671"><path d="${'M52 618 L205 268 C252 160 300 66 452 52 L915 52 L863 167 L532 168 C470 172 442 196 418 244 L397 294 L752 294 C722 372 676 410 620 411 L348 412 L258 618 Z M956 52 L1157 52 L971 482 L1347 482 C1312 576 1270 612 1190 618 L707 618 Z'}" fill="#c9a45c"/></svg></div><div class="bar"></div><div class="bar s"></div></div></div>
        <svg class="waves" width="520" height="360" viewBox="0 0 520 360" fill="none" stroke="#c9a45c" stroke-linecap="round">
            <path d="M190 250 Q260 300 330 250" stroke-width="4" opacity=".9"/>
            <path d="M150 268 Q260 350 370 268" stroke-width="3.5" opacity=".6"/>
            <path d="M110 286 Q260 400 410 286" stroke-width="3" opacity=".35"/>
        </svg>
        <div class="rig">${plate3d({ id: 'n', width: 620, thickness: 9, shadow: 'rgba(60,45,20,.35)', shadowOffset: [40, 230] })}</div>
    </div>`,
};

/* 6 — Exploded view: resin / NFC inlay / ferrite / Zamak body. */
const layerSvg = (inner, w = PW, h = PH) => `<svg viewBox="0 0 ${w} ${h}" width="${w * 1.5}" height="${h * 1.5}">${inner}</svg>`;
const coil = Array.from({ length: 7 }, (_, i) => `<rect x="${384 + i * 6}" y="${46 + i * 6}" width="${128 - i * 12}" height="${114 - i * 12}" rx="${18 - i * 2}" fill="none" stroke="#c07a3a" stroke-width="2.2"/>`).join('');
const exploded = {
    name: 'plate-exploded',
    width: 1800,
    height: 1500,
    sizes: [1800, 1200, 800],
    out: 'public/media/brand/plate/legacy-plate-exploded-{w}.webp',
    css: `body{background:linear-gradient(180deg,#fbfaf6,#f1ece2)}
        .scene{position:absolute;left:0;top:0;width:1150px;height:1500px;perspective:3000px}
        .rig{position:absolute;left:50%;top:69%;transform-style:preserve-3d;transform:rotateX(58deg) rotateZ(-32deg)}
        .l{position:absolute;left:0;top:0;transform-style:preserve-3d}
        .l svg{display:block;filter:drop-shadow(0 18px 14px rgba(60,45,20,.18))}
        .labels{position:absolute;right:80px;top:0;bottom:0;width:560px;font-family:'Instrument Sans',sans-serif}
        .lab{position:absolute;left:0;display:flex;gap:22px;align-items:flex-start}
        .n{font:600 15px 'Instrument Sans';color:#85662b;letter-spacing:.2em;border:1.5px solid #c9a45c;border-radius:999px;width:46px;height:46px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
        .t{font:500 34px Fraunces,serif;color:#171714;line-height:1.1}
        .d{font:400 18px 'Instrument Sans';color:#6b675f;margin-top:8px;line-height:1.45;max-width:420px}
        .lead{position:absolute;height:1.5px;background:#c9a45c;opacity:.7}`,
    html: `<div class="scene"><div class="rig">
        <div class="l" style="transform:translate(-420px,-157px) translateZ(690px)">${layerSvg(`<defs>${plateDefs('x1')}</defs><rect width="${PW}" height="${PH}" rx="${PR}" fill="#fff" fill-opacity=".35" stroke="#e8dcc4"/><g opacity=".92">${plateFront('x1').replace(/<rect width="560"[^>]*\/>/, '')}</g><rect width="${PW}" height="${PH}" rx="${PR}" fill="url(#x1-gloss)"/>`)}</div>
        <div class="l" style="transform:translate(-420px,-157px) translateZ(460px)">${layerSvg(`<rect x="370" y="32" width="156" height="142" rx="18" fill="#f4efe4" fill-opacity=".9" stroke="#d8cdb6"/>${coil}<rect x="440" y="96" width="16" height="16" rx="3" fill="#2a2926"/>`)}</div>
        <div class="l" style="transform:translate(-420px,-157px) translateZ(230px)">${layerSvg(`<rect x="364" y="26" width="168" height="154" rx="20" fill="#3b3a37"/><rect x="364" y="26" width="168" height="154" rx="20" fill="url(#fe)"/><defs><linearGradient id="fe" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#fff" stop-opacity=".18"/><stop offset="1" stop-color="#000" stop-opacity=".2"/></linearGradient></defs>`)}</div>
        <div class="l" style="transform:translate(-420px,-157px) translateZ(0)">${layerSvg(`<defs>${plateDefs('x4')}</defs><rect width="${PW}" height="${PH}" rx="${PR}" fill="url(#x4-edge)"/><rect x="5" y="4" width="${PW - 10}" height="${PH - 11}" rx="${PR - 4}" fill="url(#x4-face)"/><rect x="24" y="24" width="322" height="158" rx="13" fill="#b9a479"/><rect x="24" y="24" width="322" height="158" rx="13" fill="#000" filter="url(#x4-inset)"/><rect x="360" y="24" width="176" height="158" rx="13" fill="#a8946b"/><rect x="360" y="24" width="176" height="158" rx="13" fill="#000" filter="url(#x4-inset)"/><rect x="5" y="4" width="${PW - 10}" height="${PH - 11}" rx="${PR - 4}" fill="url(#x4-sheen)" opacity=".5"/>`)}</div>
    </div></div>
    <div class="labels">
        <div class="lab" style="top:300px"><span class="n">01</span><div><div class="t">Resina y gráfica</div><div class="d">Acabado transparente de brillo controlado que protege la impresión de tus datos.</div></div></div>
        <div class="lab" style="top:520px"><span class="n">02</span><div><div class="t">Inlay NFC</div><div class="d">Antena pasiva integrada: abre tu Legacy al acercar un teléfono compatible.</div></div></div>
        <div class="lab" style="top:740px"><span class="n">03</span><div><div class="t">Ferrita anti-metal</div><div class="d">Separa la antena del metal para que la lectura NFC funcione.</div></div></div>
        <div class="lab" style="top:1000px"><span class="n">04</span><div><div class="t">Zamak niquelado</div><div class="d">Cuerpo metálico con peso real y acabado premium.</div></div></div>
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
        .rig{position:absolute;left:45%;top:50%;transform-style:preserve-3d;transform:rotateX(52deg) rotateZ(-20deg)}
        .shadow{position:absolute;inset:0;border-radius:40px;background:rgba(60,45,20,.5);filter:blur(30px);transform:translate3d(30px,46px,-14px)}
        .grain{position:absolute;inset:0;background:${grain};mix-blend-mode:multiply;opacity:.6}`,
    html: `<div class="scene"><div class="rig">${plate3d({ id: 'pp', width: 840, thickness: 10, shadow: 'rgba(60,45,20,.45)', shadowOffset: [26, 40] })}</div></div><div class="grain"></div>`,
};

/* Floating plate on transparent background (overlays photography). */
const plateFloat = {
    name: 'plate-float',
    width: 1200,
    height: 700,
    transparent: true,
    sizes: [1200, 700],
    out: 'public/media/brand/plate/legacy-plate-float-{w}.webp',
    quality: 86,
    css: `${plate3dCss}body{background:transparent}
        .scene{position:absolute;inset:0;perspective:2200px}
        .rig{position:absolute;left:50%;top:48%;transform-style:preserve-3d;transform:rotateX(34deg) rotateY(-12deg) rotateZ(-9deg)}`,
    html: `<div class="scene"><div class="rig">${plate3d({ id: 'f', width: 900, thickness: 11, shadow: 'rgba(0,0,0,.55)', shadowOffset: [30, 60] })}</div></div>`,
};

/* Portrait card crop: plate diagonal on dark stone, room for copy below. */
const portrait = {
    ...hero,
    name: 'plate-portrait',
    width: 1000,
    height: 1400,
    sizes: [1000, 640],
    out: 'public/media/brand/plate/legacy-plate-portrait-{w}.webp',
    css: hero.css.replace('.rig{position:absolute;left:50%;top:52%;transform-style:preserve-3d;transform:rotateX(56deg) rotateZ(-17deg)}', '.rig{position:absolute;left:52%;top:27%;transform-style:preserve-3d;transform:rotateX(48deg) rotateZ(-30deg)}'),
    html: hero.html.replace("width: 980, thickness: 10", "width: 760, thickness: 10"),
};

export const scenes = [
    portrait,
    plateFloat,
    plateOrtho('front'),
    plateOrtho('back'),
    hero,
    macro,
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
