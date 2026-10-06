/**
 * Legacy Plate geometry shared by every render scene — the same proportions
 * and zones as resources/js/components/public/PlateShowcase.vue:
 *
 *   ZAMAK niquelado body (champagne/nickel), soft radius, ~2.67:1
 *   left  : resin window with the athlete's printed data
 *   right : black resin panel with the FL monogram + discreet NFC mark
 *   back  : printed event data + NFC zone (no QR, no laser)
 */

/** FL monogram traced from public/images/brand/logo/logo-mark-*.png (1400×671). */
export const FL_PATH =
    'M52 618 L205 268 C252 160 300 66 452 52 L915 52 L863 167 L532 168 C470 172 442 196 418 244 L397 294 L752 294 C722 372 676 410 620 411 L348 412 L258 618 Z ' +
    'M956 52 L1157 52 L971 482 L1347 482 C1312 576 1270 612 1190 618 L707 618 Z';

export const PLATE = { w: 560, h: 210, r: 26 };

export function flMark({ x, y, width, fill }) {
    const s = width / 1400;

    return `<g transform="translate(${x} ${y}) scale(${s})"><path d="${FL_PATH}" fill="${fill}"/></g>`;
}

export function nfcGlyph({ x, y, size, color, opacity = 1 }) {
    const s = size / 24;

    return `<g transform="translate(${x} ${y}) scale(${s})" fill="none" stroke="${color}" stroke-width="1.6" stroke-linecap="round" opacity="${opacity}">
        <path d="M6 8.3a6 6 0 0 1 0 7.4"/><path d="M9.6 6a10 10 0 0 1 0 12"/><path d="M13.2 3.6a14 14 0 0 1 0 16.8"/><circle cx="3.2" cy="12" r="1.2" fill="${color}" stroke="none"/>
    </g>`;
}

/** Shared <defs>: metal, resin, gloss. `id` keeps several plates in one SVG unique. */
export function plateDefs(id = 'p') {
    return `
    <linearGradient id="${id}-edge" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0" stop-color="#f6eedc"/><stop offset=".18" stop-color="#cbb791"/>
        <stop offset=".55" stop-color="#8e7a56"/><stop offset=".85" stop-color="#5f5038"/><stop offset="1" stop-color="#3b3122"/>
    </linearGradient>
    <linearGradient id="${id}-face" x1="0" y1="0" x2="1" y2=".55">
        <stop offset="0" stop-color="#efe3c8"/><stop offset=".2" stop-color="#d6c39d"/>
        <stop offset=".38" stop-color="#f7efdd"/><stop offset=".56" stop-color="#c9b48c"/>
        <stop offset=".78" stop-color="#e9dcbf"/><stop offset="1" stop-color="#b59f76"/>
    </linearGradient>
    <linearGradient id="${id}-bevel" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0" stop-color="#fffaf0" stop-opacity=".95"/><stop offset=".5" stop-color="#fffaf0" stop-opacity="0"/>
        <stop offset="1" stop-color="#4a3d29" stop-opacity=".55"/>
    </linearGradient>
    <linearGradient id="${id}-resin" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0" stop-color="#fbf7ee"/><stop offset="1" stop-color="#efe6d3"/>
    </linearGradient>
    <linearGradient id="${id}-black" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0" stop-color="#262522"/><stop offset=".45" stop-color="#0d0d0c"/><stop offset="1" stop-color="#1b1a18"/>
    </linearGradient>
    <linearGradient id="${id}-gold" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0" stop-color="#f3dfa6"/><stop offset=".45" stop-color="#c9a45c"/><stop offset="1" stop-color="#8f6f33"/>
    </linearGradient>
    <linearGradient id="${id}-gloss" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0" stop-color="#fff" stop-opacity=".55"/><stop offset=".32" stop-color="#fff" stop-opacity=".08"/>
        <stop offset=".33" stop-color="#fff" stop-opacity="0"/><stop offset="1" stop-color="#fff" stop-opacity="0"/>
    </linearGradient>
    <linearGradient id="${id}-sheen" x1="0" y1="0" x2="1" y2="0">
        <stop offset="0" stop-color="#fff" stop-opacity="0"/><stop offset=".42" stop-color="#fff" stop-opacity="0"/>
        <stop offset=".5" stop-color="#fff" stop-opacity=".42"/><stop offset=".58" stop-color="#fff" stop-opacity="0"/>
        <stop offset="1" stop-color="#fff" stop-opacity="0"/>
    </linearGradient>
    <filter id="${id}-inset" x="-5%" y="-5%" width="110%" height="110%">
        <feOffset dx="0" dy="2"/><feGaussianBlur stdDeviation="2.2" result="b"/>
        <feComposite in="SourceGraphic" in2="b" operator="arithmetic" k2="-1" k3="1" result="shadow"/>
        <feColorMatrix in="shadow" type="matrix" values="0 0 0 0 .12  0 0 0 0 .1  0 0 0 0 .06  0 0 0 .55 0"/>
        <feComposite in2="SourceGraphic" operator="in"/>
    </filter>`;
}

const esc = (s) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;');

/**
 * Front face. `data` = { name, event, distance, time } — sample values,
 * never presented as a real member.
 */
export function plateFront(id = 'p', data = {}) {
    const { w, h, r } = PLATE;
    const d = {
        name: 'ALEX RIVERA',
        event: 'TRAIL DE LA SIERRA',
        distance: '42 KM',
        time: '04:28:15',
        ...data,
    };

    return `
    <g>
        <rect width="${w}" height="${h}" rx="${r}" fill="url(#${id}-edge)"/>
        <rect x="5" y="4" width="${w - 10}" height="${h - 11}" rx="${r - 4}" fill="url(#${id}-face)"/>
        <rect x="5" y="4" width="${w - 10}" height="${h - 11}" rx="${r - 4}" fill="none" stroke="url(#${id}-bevel)" stroke-width="3"/>

        <!-- resin window (printed athlete data) -->
        <rect x="24" y="24" width="322" height="158" rx="13" fill="url(#${id}-resin)"/>
        <rect x="24" y="24" width="322" height="158" rx="13" fill="#000" filter="url(#${id}-inset)"/>
        <rect x="24" y="24" width="322" height="158" rx="13" fill="none" stroke="#9d8762" stroke-opacity=".55" stroke-width="1.2"/>
        <text x="48" y="64" font-family="Instrument Sans, Arial, sans-serif" font-size="11" letter-spacing="3.2" font-weight="600" fill="#85662b">${esc(d.event)}</text>
        <text x="46" y="112" font-family="Fraunces, Georgia, serif" font-size="38" font-weight="500" letter-spacing=".5" fill="#171714">${esc(d.name)}</text>
        <line x1="48" y1="132" x2="96" y2="132" stroke="#c9a45c" stroke-width="2"/>
        <text x="48" y="160" font-family="Instrument Sans, Arial, sans-serif" font-size="17" font-weight="600" letter-spacing="1.4" fill="#171714">${esc(d.distance)}  ·  ${esc(d.time)}</text>
        <rect x="24" y="24" width="322" height="158" rx="13" fill="url(#${id}-gloss)" opacity=".75"/>

        <!-- black resin panel -->
        <rect x="360" y="24" width="176" height="158" rx="13" fill="url(#${id}-black)"/>
        <rect x="360" y="24" width="176" height="158" rx="13" fill="none" stroke="#000" stroke-opacity=".6" stroke-width="1.2"/>
        ${flMark({ x: 392, y: 70, width: 112, fill: `url(#${id}-gold)` })}
        ${nfcGlyph({ x: 506, y: 152, size: 18, color: '#c9a45c', opacity: 0.7 })}
        <rect x="360" y="24" width="176" height="158" rx="13" fill="url(#${id}-gloss)" opacity=".55"/>

        <!-- polished sheen across the metal -->
        <rect x="5" y="4" width="${w - 10}" height="${h - 11}" rx="${r - 4}" fill="url(#${id}-sheen)" opacity=".55"/>
    </g>`;
}

export function plateBack(id = 'p', data = {}) {
    const { w, h, r } = PLATE;
    const d = {
        event: 'TRAIL DE LA SIERRA',
        date: '14 · 09 · 2026',
        distance: '42 KM',
        position: '27 / 412',
        bib: '1043',
        serial: 'FL · 000127',
        ...data,
    };
    const rows = [
        ['EVENTO', d.event],
        ['FECHA', d.date],
        ['DISTANCIA', d.distance],
        ['POSICIÓN', d.position],
        ['DORSAL', d.bib],
    ];

    return `
    <g>
        <rect width="${w}" height="${h}" rx="${r}" fill="url(#${id}-edge)"/>
        <rect x="5" y="4" width="${w - 10}" height="${h - 11}" rx="${r - 4}" fill="url(#${id}-face)"/>
        <rect x="5" y="4" width="${w - 10}" height="${h - 11}" rx="${r - 4}" fill="none" stroke="url(#${id}-bevel)" stroke-width="3"/>
        <rect x="24" y="24" width="${w - 48}" height="158" rx="13" fill="#141413"/>
        <rect x="24" y="24" width="${w - 48}" height="158" rx="13" fill="#000" filter="url(#${id}-inset)"/>
        ${flMark({ x: 48, y: 44, width: 42, fill: `url(#${id}-gold)` })}
        <text x="100" y="60" font-family="Instrument Sans, Arial, sans-serif" font-size="10" letter-spacing="3" font-weight="600" fill="#c9a45c">FINISHER LEGACY</text>
        ${rows
            .map(
                ([k, v], i) => `
        <text x="48" y="${96 + i * 18}" font-family="Instrument Sans, Arial, sans-serif" font-size="8.5" letter-spacing="2" fill="#8b867c">${k}</text>
        <text x="132" y="${96 + i * 18}" font-family="Instrument Sans, Arial, sans-serif" font-size="11" font-weight="600" letter-spacing="1" fill="#f3efe6">${esc(v)}</text>`,
            )
            .join('')}
        <!-- route line (decorative). The NFC inlay lives under the FRONT black panel. -->
        <path d="M340 150 C372 118 392 160 420 128 S470 88 512 112" fill="none" stroke="#c9a45c" stroke-opacity=".55" stroke-width="1.4" stroke-dasharray="3 4"/>
        <circle cx="340" cy="150" r="3" fill="#c9a45c"/><circle cx="512" cy="112" r="3" fill="none" stroke="#c9a45c" stroke-width="1.4"/>
        <text x="512" y="166" text-anchor="end" font-family="Instrument Sans, Arial, sans-serif" font-size="8" letter-spacing="2.4" fill="#8b867c">TU ESFUERZO · TU HISTORIA</text>
        <text x="${w - 40}" y="56" text-anchor="end" font-family="Instrument Sans, Arial, sans-serif" font-size="8" letter-spacing="1.6" fill="#6f6a60">${esc(d.serial)}</text>
        <rect x="24" y="24" width="${w - 48}" height="158" rx="13" fill="url(#${id}-gloss)" opacity=".45"/>
        <rect x="5" y="4" width="${w - 10}" height="${h - 11}" rx="${r - 4}" fill="url(#${id}-sheen)" opacity=".4"/>
    </g>`;
}

export function plateSvg(face = 'front', id = 'p', data = {}, extra = '') {
    const { w, h } = PLATE;

    return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${w} ${h}" width="${w}" height="${h}"><defs>${plateDefs(id)}</defs>${face === 'front' ? plateFront(id, data) : plateBack(id, data)}${extra}</svg>`;
}
