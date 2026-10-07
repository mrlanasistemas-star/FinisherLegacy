/**
 * Legacy Plate V3 — the single drawing of the physical product, shared by
 * the site (PlateShowcase, PlateClipSteps) and the render pipeline
 * (tools/renders imports this file directly; Node strips the types).
 * Keep it dependency-free and use erasable TypeScript only.
 *
 *   70 × 45 mm, Zamak niquelado (lightly brushed), resin on the front
 *   FRONT : full sports design + black FL panel with the NFC inlay under it
 *   BACK  : clean metal + a stamped stainless-steel money clip — no print
 *   SIDE  : body ~3.5 mm + clip with 3 pressure waves ≈ 6 mm total
 *
 * Units: 10 SVG units = 1 mm.
 */

export type PlateSpec = {
    width_mm: number;
    height_mm: number;
    clip_length_mm: number;
    clip_height_mm: number;
    clip_thickness_mm: number;
    total_depth_mm: number;
};

export type PlateFrontData = {
    name?: string;
    event?: string;
    date?: string;
    time?: string;
    distance?: string;
    pace?: string;
};

export const DEFAULT_SPEC: PlateSpec = {
    width_mm: 70,
    height_mm: 45,
    clip_length_mm: 52,
    clip_height_mm: 20,
    clip_thickness_mm: 1,
    total_depth_mm: 6,
};

export const SAMPLE_FRONT: Required<PlateFrontData> = {
    name: 'JOSÉ ALBERTO CARLOS BUENO',
    event: 'MEDIO MARATÓN',
    date: '22 MAY 2026',
    time: '01:44:51',
    distance: '21.1 KM',
    pace: '4:58 min/km',
};

/** FL monogram traced from the brand mark (1400 × 671). */
export const FL_PATH =
    'M52 618 L205 268 C252 160 300 66 452 52 L915 52 L863 167 L532 168 C470 172 442 196 418 244 L397 294 L752 294 C722 372 676 410 620 411 L348 412 L258 618 Z ' +
    'M956 52 L1157 52 L971 482 L1347 482 C1312 576 1270 612 1190 618 L707 618 Z';

const U = 10;

export const esc = (s: string): string =>
    String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');

/** Balanced two-line split of a long name ("JOSÉ ALBERTO / CARLOS BUENO"). */
export function splitName(name: string, maxOneLine = 14): string[] {
    const words = name.trim().split(/\s+/).filter(Boolean);

    if (words.length < 2 || name.length <= maxOneLine) {
        return [name.trim()];
    }

    let best = 1;
    let bestDiff = Infinity;

    for (let i = 1; i < words.length; i++) {
        const a = words.slice(0, i).join(' ').length;
        const b = words.slice(i).join(' ').length;

        if (Math.abs(a - b) < bestDiff) {
            bestDiff = Math.abs(a - b);
            best = i;
        }
    }

    return [words.slice(0, best).join(' '), words.slice(best).join(' ')];
}

export function flMark(
    x: number,
    y: number,
    width: number,
    fill: string,
): string {
    const s = width / 1400;

    return `<g transform="translate(${x} ${y}) scale(${s})"><path d="${FL_PATH}" fill="${fill}"/></g>`;
}

export function nfcGlyph(
    x: number,
    y: number,
    size: number,
    color: string,
    opacity = 1,
): string {
    const s = size / 24;

    return `<g transform="translate(${x} ${y}) scale(${s})" fill="none" stroke="${color}" stroke-width="1.6" stroke-linecap="round" opacity="${opacity}"><path d="M6 8.3a6 6 0 0 1 0 7.4"/><path d="M9.6 6a10 10 0 0 1 0 12"/><path d="M13.2 3.6a14 14 0 0 1 0 16.8"/><circle cx="3.2" cy="12" r="1.2" fill="${color}" stroke="none"/></g>`;
}

/** Gradients/filters for one plate drawing. `id` keeps several plates unique. */
export function plateDefs(id: string): string {
    return `
    <linearGradient id="${id}-edge" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0" stop-color="#fbfbf9"/><stop offset=".16" stop-color="#d9d8d3"/>
        <stop offset=".55" stop-color="#9c9a93"/><stop offset=".85" stop-color="#6b6963"/><stop offset="1" stop-color="#43423e"/>
    </linearGradient>
    <linearGradient id="${id}-face" x1="0" y1="0" x2="1" y2=".6">
        <stop offset="0" stop-color="#f0efeb"/><stop offset=".2" stop-color="#d2d0c9"/>
        <stop offset=".38" stop-color="#f8f7f4"/><stop offset=".58" stop-color="#c3c1b9"/>
        <stop offset=".8" stop-color="#e5e3dd"/><stop offset="1" stop-color="#b0ada5"/>
    </linearGradient>
    <linearGradient id="${id}-bevel" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0" stop-color="#fff" stop-opacity=".95"/><stop offset=".5" stop-color="#fff" stop-opacity="0"/>
        <stop offset="1" stop-color="#3d3c38" stop-opacity=".55"/>
    </linearGradient>
    <linearGradient id="${id}-resin" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0" stop-color="#fbf9f4"/><stop offset="1" stop-color="#eeeae1"/>
    </linearGradient>
    <linearGradient id="${id}-black" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0" stop-color="#2a2926"/><stop offset=".45" stop-color="#0d0d0c"/><stop offset="1" stop-color="#1c1b19"/>
    </linearGradient>
    <linearGradient id="${id}-gold" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0" stop-color="#f3dfa6"/><stop offset=".45" stop-color="#c9a45c"/><stop offset="1" stop-color="#8f6f33"/>
    </linearGradient>
    <linearGradient id="${id}-gloss" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0" stop-color="#fff" stop-opacity=".6"/><stop offset=".3" stop-color="#fff" stop-opacity=".08"/>
        <stop offset=".31" stop-color="#fff" stop-opacity="0"/><stop offset="1" stop-color="#fff" stop-opacity="0"/>
    </linearGradient>
    <linearGradient id="${id}-sheen" x1="0" y1="0" x2="1" y2="0">
        <stop offset="0" stop-color="#fff" stop-opacity="0"/><stop offset=".42" stop-color="#fff" stop-opacity="0"/>
        <stop offset=".5" stop-color="#fff" stop-opacity=".45"/><stop offset=".58" stop-color="#fff" stop-opacity="0"/>
        <stop offset="1" stop-color="#fff" stop-opacity="0"/>
    </linearGradient>
    <linearGradient id="${id}-steel" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0" stop-color="#f7f9fa"/><stop offset=".22" stop-color="#c6ccd1"/>
        <stop offset=".45" stop-color="#eef1f3"/><stop offset=".7" stop-color="#9aa2a8"/>
        <stop offset=".9" stop-color="#d6dbdf"/><stop offset="1" stop-color="#858d93"/>
    </linearGradient>
    <linearGradient id="${id}-steel-side" x1="0" y1="0" x2="1" y2="0">
        <stop offset="0" stop-color="#7d858b"/><stop offset=".5" stop-color="#e8ecee"/><stop offset="1" stop-color="#8e969c"/>
    </linearGradient>
    <linearGradient id="${id}-waves" x1="0" y1="0" x2="1" y2="0">
        <stop offset="0" stop-color="#000" stop-opacity=".18"/><stop offset=".14" stop-color="#000" stop-opacity="0"/>
        <stop offset=".24" stop-color="#fff" stop-opacity=".55"/><stop offset=".32" stop-color="#000" stop-opacity=".2"/>
        <stop offset=".45" stop-color="#000" stop-opacity="0"/><stop offset=".53" stop-color="#fff" stop-opacity=".55"/>
        <stop offset=".61" stop-color="#000" stop-opacity=".2"/><stop offset=".74" stop-color="#000" stop-opacity="0"/>
        <stop offset=".82" stop-color="#fff" stop-opacity=".5"/><stop offset=".9" stop-color="#000" stop-opacity=".18"/>
        <stop offset="1" stop-color="#fff" stop-opacity=".35"/>
    </linearGradient>
    <linearGradient id="${id}-ribbon" x1="0" y1="0" x2="1" y2="0">
        <stop offset="0" stop-color="#7a1f24"/><stop offset=".2" stop-color="#7a1f24"/>
        <stop offset=".2" stop-color="#e9e3d6"/><stop offset=".3" stop-color="#e9e3d6"/>
        <stop offset=".3" stop-color="#1d3f6e"/><stop offset=".7" stop-color="#1d3f6e"/>
        <stop offset=".7" stop-color="#e9e3d6"/><stop offset=".8" stop-color="#e9e3d6"/>
        <stop offset=".8" stop-color="#7a1f24"/><stop offset="1" stop-color="#7a1f24"/>
    </linearGradient>
    <filter id="${id}-brush" x="0" y="0" width="100%" height="100%">
        <feTurbulence type="fractalNoise" baseFrequency="0.0025 0.6" numOctaves="2" seed="4" result="t"/>
        <feColorMatrix in="t" type="matrix" values=".7 0 0 0 .15  .7 0 0 0 .15  .7 0 0 0 .15  0 0 0 0 .2" result="c"/>
        <feComposite in="c" in2="SourceAlpha" operator="in"/>
    </filter>
    <filter id="${id}-inset" x="-5%" y="-5%" width="110%" height="110%">
        <feOffset dx="0" dy="3"/><feGaussianBlur stdDeviation="3" result="b"/>
        <feComposite in="SourceGraphic" in2="b" operator="arithmetic" k2="-1" k3="1" result="shadow"/>
        <feColorMatrix in="shadow" type="matrix" values="0 0 0 0 .1  0 0 0 0 .1  0 0 0 0 .09  0 0 0 .5 0"/>
        <feComposite in2="SourceGraphic" operator="in"/>
    </filter>
    <filter id="${id}-drop" x="-20%" y="-30%" width="140%" height="180%">
        <feGaussianBlur in="SourceAlpha" stdDeviation="9"/><feOffset dx="10" dy="16" result="o"/>
        <feColorMatrix in="o" type="matrix" values="0 0 0 0 .08  0 0 0 0 .08  0 0 0 0 .07  0 0 0 .45 0"/>
        <feMerge><feMergeNode/><feMergeNode in="SourceGraphic"/></feMerge>
    </filter>`;
}

function dims(spec: Partial<PlateSpec> = {}) {
    const s = { ...DEFAULT_SPEC, ...spec };

    return {
        s,
        w: s.width_mm * U,
        h: s.height_mm * U,
        r: Math.min(s.width_mm, s.height_mm) * U * 0.1,
    };
}

/** Metal body (edge + brushed face). Shared by both faces. */
function body(id: string, w: number, h: number, r: number): string {
    return `
        <rect width="${w}" height="${h}" rx="${r}" fill="url(#${id}-edge)"/>
        <rect x="6" y="5" width="${w - 12}" height="${h - 13}" rx="${r - 5}" fill="url(#${id}-face)"/>
        <rect x="6" y="5" width="${w - 12}" height="${h - 13}" rx="${r - 5}" fill="#000" filter="url(#${id}-brush)"/>
        <rect x="6" y="5" width="${w - 12}" height="${h - 13}" rx="${r - 5}" fill="none" stroke="url(#${id}-bevel)" stroke-width="4"/>`;
}

/**
 * FRONT: resin window with the sports data (Núcleo composition) and the
 * black FL panel on the right — the NFC inlay sits under that panel.
 */
export function plateFront(
    id = 'p',
    data: PlateFrontData = {},
    spec: Partial<PlateSpec> = {},
): string {
    const { w, h, r } = dims(spec);
    const d = { ...SAMPLE_FRONT, ...data };
    const m = 30;
    const panelW = Math.round(w * 0.2);
    const winW = w - m * 2 - panelW - 18;
    const panelX = m + winW + 18;
    const innerH = h - m * 2 - 6;
    const lines = splitName(d.name.toUpperCase());
    const longest = Math.max(...lines.map((l) => l.length), 1);
    const nameSize = Math.min(48, Math.round((winW - 60) / (longest * 0.66)));
    const x = m + 32;
    const top = m + 64;
    const nameBottom = top + (lines.length - 1) * nameSize * 1.05;
    const sans = 'Instrument Sans, Arial, sans-serif';

    return `
    <g>
        ${body(id, w, h, r)}
        <rect x="${m}" y="${m}" width="${winW}" height="${innerH}" rx="20" fill="url(#${id}-resin)"/>
        <rect x="${m}" y="${m}" width="${winW}" height="${innerH}" rx="20" fill="#000" filter="url(#${id}-inset)"/>
        <rect x="${m}" y="${m}" width="${winW}" height="${innerH}" rx="20" fill="none" stroke="#8f8d86" stroke-opacity=".5" stroke-width="1.4"/>
        ${lines
            .map(
                (line, i) =>
                    `<text x="${x - 2}" y="${top + i * nameSize * 1.05}" font-family="Fraunces, Georgia, serif" font-size="${nameSize}" font-weight="500" letter-spacing=".4" fill="#171714">${esc(line)}</text>`,
            )
            .join('')}
        <line x1="${x}" y1="${nameBottom + 26}" x2="${x + 58}" y2="${nameBottom + 26}" stroke="#c9a45c" stroke-width="3"/>
        <text x="${x}" y="${nameBottom + 62}" font-family="${sans}" font-size="19" font-weight="600" letter-spacing="3" fill="#85662b">${esc(d.date.toUpperCase())}</text>
        <text x="${x - 3}" y="${nameBottom + 128}" font-family="${sans}" font-size="62" font-weight="600" letter-spacing="1" fill="#171714">${esc(d.time)}</text>
        <text x="${x}" y="${h - m - 64}" font-family="${sans}" font-size="12" font-weight="600" letter-spacing="2.6" fill="#8b867c">DISTANCE</text>
        <text x="${x}" y="${h - m - 34}" font-family="${sans}" font-size="24" font-weight="600" letter-spacing=".6" fill="#171714">${esc(d.distance.toUpperCase())}</text>
        <text x="${x + winW * 0.46}" y="${h - m - 64}" font-family="${sans}" font-size="12" font-weight="600" letter-spacing="2.6" fill="#8b867c">PACE</text>
        <text x="${x + winW * 0.46}" y="${h - m - 34}" font-family="${sans}" font-size="24" font-weight="600" letter-spacing=".6" fill="#171714">${esc(d.pace)}</text>
        <rect x="${m}" y="${m}" width="${winW}" height="${innerH}" rx="20" fill="url(#${id}-gloss)" opacity=".7"/>

        <rect x="${panelX}" y="${m}" width="${panelW}" height="${innerH}" rx="20" fill="url(#${id}-black)"/>
        <rect x="${panelX}" y="${m}" width="${panelW}" height="${innerH}" rx="20" fill="none" stroke="#000" stroke-opacity=".6" stroke-width="1.4"/>
        ${flMark(panelX + panelW * 0.16, m + innerH * 0.4, panelW * 0.68, `url(#${id}-gold)`)}
        ${nfcGlyph(panelX + panelW / 2 - 14, m + innerH - 62, 28, '#c9a45c', 0.75)}
        <rect x="${panelX}" y="${m}" width="${panelW}" height="${innerH}" rx="20" fill="url(#${id}-gloss)" opacity=".55"/>

        <rect x="6" y="5" width="${w - 12}" height="${h - 13}" rx="${r - 5}" fill="url(#${id}-sheen)" opacity=".5"/>
    </g>`;
}

/** The stamped stainless clip as seen from the back (centred). */
export function clipBack(id: string, spec: Partial<PlateSpec> = {}): string {
    const { s, w, h } = dims(spec);
    const L = s.clip_length_mm * U;
    const H = s.clip_height_mm * U;
    const x = (w - L) / 2;
    const y = (h - H) / 2;
    const base = 70;

    return `
    <g>
        <rect x="${x + 6}" y="${y + 10}" width="${L}" height="${H}" rx="${H * 0.32}" fill="#24231f" opacity=".35" filter="url(#${id}-drop)"/>
        <rect x="${x}" y="${y}" width="${L}" height="${H}" rx="${H * 0.32}" fill="url(#${id}-steel)"/>
        <rect x="${x}" y="${y}" width="${L}" height="${H}" rx="${H * 0.32}" fill="url(#${id}-waves)" opacity=".45"/>
        <rect x="${x}" y="${y}" width="${L}" height="${H}" rx="${H * 0.32}" fill="#000" filter="url(#${id}-brush)" opacity=".7"/>
        <rect x="${x}" y="${y}" width="${base}" height="${H}" rx="${H * 0.32}" fill="#000" opacity=".1"/>
        <line x1="${x + base}" y1="${y + 8}" x2="${x + base}" y2="${y + H - 8}" stroke="#5f666c" stroke-opacity=".55" stroke-width="2"/>
        <circle cx="${x + base * 0.5}" cy="${y + H * 0.3}" r="13" fill="url(#${id}-edge)" stroke="#6f6d67" stroke-width="1.4"/>
        <circle cx="${x + base * 0.5}" cy="${y + H * 0.7}" r="13" fill="url(#${id}-edge)" stroke="#6f6d67" stroke-width="1.4"/>
        <circle cx="${x + base * 0.5 - 3}" cy="${y + H * 0.3 - 4}" r="4" fill="#fff" opacity=".7"/>
        <circle cx="${x + base * 0.5 - 3}" cy="${y + H * 0.7 - 4}" r="4" fill="#fff" opacity=".7"/>
        <path d="M${x + L - 22} ${y + 10} Q${x + L - 6} ${y + H / 2} ${x + L - 22} ${y + H - 10}" fill="none" stroke="#fff" stroke-opacity=".75" stroke-width="3" stroke-linecap="round"/>
        <rect x="${x}" y="${y}" width="${L}" height="${H}" rx="${H * 0.32}" fill="none" stroke="#6b737a" stroke-opacity=".7" stroke-width="1.6"/>
    </g>`;
}

/** The back body alone — clean brushed metal, no clip, no print. */
export function plateBackBody(id = 'p', spec: Partial<PlateSpec> = {}): string {
    const { w, h, r } = dims(spec);

    return `${body(id, w, h, r)}
        <rect x="6" y="5" width="${w - 12}" height="${h - 13}" rx="${r - 5}" fill="url(#${id}-sheen)" opacity=".35"/>`;
}

/** BACK: clean brushed metal + the money clip. No print of any kind. */
export function plateBack(id = 'p', spec: Partial<PlateSpec> = {}): string {
    return `<g>${plateBackBody(id, spec)}${clipBack(id, spec)}</g>`;
}

export function plateSvg(
    face: 'front' | 'back',
    id = 'p',
    data: PlateFrontData = {},
    spec: Partial<PlateSpec> = {},
    extra = '',
    attrs = '',
): string {
    const { w, h } = dims(spec);

    return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${w} ${h}" width="${w}" height="${h}" ${attrs}><defs>${plateDefs(id)}</defs>${face === 'front' ? plateFront(id, data, spec) : plateBack(id, spec)}${extra}</svg>`;
}

/**
 * Side profile seen from the top edge (front face up): Zamak body, resin,
 * the clip with its three pressure waves and — optionally — the medal
 * ribbon `inserting` (half-way, entering from the open end) or `held`.
 * Depth is drawn at 2.5× so 6 mm reads; the dimension line says the truth.
 */
export function plateProfileSvg(
    id = 'pr',
    ribbon: 'none' | 'inserting' | 'held' = 'none',
    spec: Partial<PlateSpec> = {},
    showDimension = true,
): string {
    const { s, w } = dims(spec);
    const k = 2.5;
    const bodyT = 3.2 * U * k;
    const resinT = 0.4 * U * k;
    const depth = s.total_depth_mm * U * k;
    const L = s.clip_length_mm * U;
    const x0 = (w - L) / 2;
    const x1 = x0 + L;
    const back = resinT + bodyT;
    const ct = s.clip_thickness_mm * U * k;
    const held = ribbon === 'held';
    const press = back + ct / 2 + (held ? 22 : 3);
    const crest = depth - ct / 2;
    const seg = (L - 70) / 6;
    const wave = [
        `M${x0} ${back + ct / 2}`,
        `L${x0 + 70} ${back + ct / 2}`,
        `Q${x0 + 70 + seg} ${crest} ${x0 + 70 + seg * 2} ${press}`,
        `Q${x0 + 70 + seg * 3} ${crest} ${x0 + 70 + seg * 4} ${press}`,
        `Q${x0 + 70 + seg * 5} ${crest} ${x1 - 6} ${press}`,
        `Q${x1 + 8} ${press + 6} ${x1 + 14} ${crest - 6}`,
    ].join(' ');
    const ribbonW = 300;
    const ribbonX = ribbon === 'inserting' ? x1 - 120 : (w - ribbonW) / 2 + 30;
    const ribbonSvg =
        ribbon === 'none'
            ? ''
            : `<rect x="${ribbonX}" y="${back + 3}" width="${ribbonW}" height="${held ? 18 : 16}" rx="4" fill="url(#${id}-ribbon)"/>
               ${ribbon === 'inserting' ? `<path d="M${x1 + 300} ${back + 12} L${x1 + 205} ${back + 12}" stroke="#171714" stroke-width="5" marker-end="url(#${id}-arrow)"/>` : ''}`;
    const vbH = depth + 120;

    return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="-30 -50 ${w + (ribbon === 'inserting' ? 300 : 120)} ${vbH}" role="img">
        <defs>${plateDefs(id)}
            <marker id="${id}-arrow" viewBox="0 0 10 10" refX="5" refY="5" markerWidth="5" markerHeight="5" orient="auto"><path d="M0 0 L10 5 L0 10 z" fill="#171714"/></marker>
        </defs>
        
        <rect x="0" y="${resinT}" width="${w}" height="${bodyT}" rx="${bodyT * 0.45}" fill="url(#${id}-edge)"/>
        <rect x="0" y="${resinT}" width="${w}" height="${bodyT}" rx="${bodyT * 0.45}" fill="#000" filter="url(#${id}-brush)"/>
        <rect x="${bodyT * 0.4}" y="${resinT * 0.4}" width="${w - bodyT * 0.8}" height="${resinT * 1.6}" rx="${resinT}" fill="#f1ece1" stroke="#cfc8b8" stroke-width="1"/>
        ${ribbonSvg}
        <path d="${wave}" fill="none" stroke="#5c646a" stroke-width="${ct + 3}" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="${wave}" fill="none" stroke="url(#${id}-steel-side)" stroke-width="${ct}" stroke-linecap="round" stroke-linejoin="round"/>
        <circle cx="${x0 + 35}" cy="${back + ct / 2}" r="${ct * 0.45}" fill="#9c9a93"/>
        ${
            showDimension
                ? `<g font-family="Instrument Sans, Arial, sans-serif" font-size="22" font-weight="600" fill="#85662b">
            <line x1="-14" y1="0" x2="-14" y2="${depth}" stroke="#c9a45c" stroke-width="2"/>
            <line x1="-22" y1="0" x2="-6" y2="0" stroke="#c9a45c" stroke-width="2"/>
            <line x1="-22" y1="${depth}" x2="-6" y2="${depth}" stroke="#c9a45c" stroke-width="2"/>
            <text x="-24" y="${depth + 34}">≈ ${s.total_depth_mm} mm</text>
        </g>`
                : ''
        }
    </svg>`;
}

/**
 * The plate worn: clipped onto a vertical medal ribbon, medal below.
 * Used for "Sujeción firme" and the ribbon renders.
 */
export function plateOnRibbonSvg(
    id = 'or',
    data: PlateFrontData = {},
    spec: Partial<PlateSpec> = {},
    attrs = '',
): string {
    const { w, h } = dims(spec);
    const rw = 320;
    const rx = (w - rw) / 2;
    const top = 210;
    const plate = plateSvg(
        'front',
        `${id}p`,
        data,
        spec,
        '',
        `x="0" y="${top}"`,
    );

    return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="-40 0 ${w + 80} 1080" ${attrs}>
        <defs>${plateDefs(id)}
            <radialGradient id="${id}-medal" cx=".35" cy=".3" r=".8"><stop offset="0" stop-color="#f6e7b8"/><stop offset=".5" stop-color="#c9a45c"/><stop offset="1" stop-color="#7d5f2a"/></radialGradient>
        </defs>
        <path d="M${rx} 0 L${rx + rw} 0 L${rx + rw} 820 L${w / 2} 900 L${rx} 820 Z" fill="url(#${id}-ribbon)"/>
        <path d="M${rx} 0 L${rx + rw} 0 L${rx + rw} 820 L${w / 2} 900 L${rx} 820 Z" fill="#000" opacity=".08"/>
        <rect x="${rx - 6}" y="${top - 6}" width="${rw + 12}" height="${h + 12}" fill="#000" opacity=".12" rx="20"/>
        <g filter="url(#${id}-drop)">${plate}</g>
        <circle cx="${w / 2}" cy="935" r="125" fill="url(#${id}-medal)" stroke="#8f6f33" stroke-width="6"/>
        <circle cx="${w / 2}" cy="935" r="95" fill="none" stroke="#fff" stroke-opacity=".35" stroke-width="3"/>
        ${flMark(w / 2 - 60, 906, 120, '#7d5f2a')}
    </svg>`;
}
