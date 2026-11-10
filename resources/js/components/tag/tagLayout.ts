/**
 * Shared layout of the Finisher Legacy Tag artwork, used by both the SVG
 * drawing (TagVisualizer.vue) and the canvas textures of the 3D model
 * (three/tagArtwork.ts) so the two always read as the same object.
 *
 * It follows the collection designs of the strategy deck (pp. 7–8):
 *
 * - FACE: a vertical rounded card ≈ 1 : 2 (200 × 410 design units) with a
 *   horizontal slot near the top for the black silicone loop.
 * - FRONT: full-bleed destination artwork (cover-fit, anchored to the
 *   bottom, fading into carbon under the header) + header: the official
 *   FL monogram image, a thin gold rule, the name in spaced caps and the
 *   discipline line ("TRIATLÓN 2026"). Without artwork: carbon with gold
 *   contour lines and an outlined year (the procedural fallback).
 * - BACK: carbon black, subtle palm accents, the monogram, the back line
 *   ("TU HISTORIA SIGUE VIVA") and the NFC mark. Never a QR.
 *
 * The canvas passes a real `measure` (ctx.measureText); the SVG, which
 * cannot measure before render, passes the `estimateSans` approximation
 * (slightly generous, so it never overflows).
 */

/** Width of `text` at font size 1 (design units). */
export type Measure = (text: string) => number;

/** Face size in design units (≈ 1 : 2, deck pp. 7–8). */
export const FACE_W = 200;
export const FACE_H = 410;
export const FACE_R = 30;

/** Horizontal slot (stadium) near the top edge. */
export const SLOT = { cx: 100, cy: 22, halfW: 30, halfH: 8.5 } as const;

/** Usable text width on the face. */
export const TAG_TEXT_W = 168;

/** Brand manual §5.2 palette. */
export const TAG_COLORS = {
    gold: '#c9ae8b',
    goldSoft: '#e6d1b5',
    carbon: '#0b0b0c',
    carbonLight: '#18181a',
    palm: '#1d4a47',
    palmLight: '#2f6b64',
} as const;

export const DEFAULT_BACK_LINE = 'TU HISTORIA SIGUE VIVA';
export const MONOGRAM_SRC = '/images/brand/logo/fl-monograma.png';
/** fl-monograma.png is 800 × 361. */
export const MONOGRAM_RATIO = 361 / 800;

/** Front header + artwork region (design units, origin top-left). */
export const FRONT = {
    monogramY: 57,
    monogramW: 46,
    ruleY: 76,
    ruleW: 20,
    nameY: 94,
    disciplineY: 108,
    /** Artwork region (below the header). */
    artTop: 112,
    /** Height of the carbon → artwork fade at the top of the art. */
    artFade: 58,
} as const;

/** Back layout. */
export const BACK = {
    monogramY: 168,
    monogramW: 74,
    ruleY: 196,
    ruleW: 22,
    lineY: 222,
    lineGap: 15,
    nfcY: 300,
} as const;

const NARROW = new Set([..."iljtfrI.,:;!|'’ "]);
const WIDE = new Set([...'mwMWÑQOGD@']);

/** Approximate Montserrat caps width (em), without letter spacing. */
export const estimateSans: Measure = (text) =>
    [...text].reduce(
        (sum, c) =>
            sum +
            (c === ' ' ? 0.3 : NARROW.has(c) ? 0.32 : WIDE.has(c) ? 0.9 : 0.7),
        0,
    );

/**
 * The collection name without a trailing year ("Cozumel 2026" → "Cozumel")
 * — the year belongs to the discipline line.
 */
export function displayName(name: string, year: string | null): string {
    const trimmed = name.trim() || 'Tu colección';

    if (year && trimmed.endsWith(year)) {
        return trimmed.slice(0, -year.length).trim() || trimmed;
    }

    return trimmed;
}

/** Size that fits one line of spaced caps (letter spacing in em). */
export function fitLine(
    text: string,
    measure: Measure,
    { width = TAG_TEXT_W, max = 12, min = 7, spacing = 0.18 } = {},
): number {
    const chars = [...text].length;
    const em = measure(text) + spacing * Math.max(chars - 1, 0);

    return Math.max(min, Math.min(max, width / Math.max(em, 0.01)));
}

/** Name line: spaced caps, as large as fits (max 14). */
export const NAME_SPACING = 0.26;
export const DISCIPLINE_SPACING = 0.32;
export const BACK_SPACING = 0.32;

export function nameSize(text: string, measure: Measure): number {
    return fitLine(text, measure, {
        max: 14,
        min: 7,
        spacing: NAME_SPACING,
    });
}

export function disciplineSize(text: string, measure: Measure): number {
    return fitLine(text, measure, {
        max: 7.2,
        min: 5,
        spacing: DISCIPLINE_SPACING,
    });
}

/** "TU HISTORIA SIGUE VIVA" → ["TU HISTORIA", "SIGUE VIVA"] (balanced). */
export function splitBackLine(text: string): string[] {
    const words = text.trim().split(/\s+/).filter(Boolean);

    if (words.length < 3 || text.length <= 14) {
        return [text.trim()];
    }

    let best = 1;
    let bestDiff = Infinity;

    for (let i = 1; i < words.length; i++) {
        const diff = Math.abs(
            words.slice(0, i).join(' ').length -
                words.slice(i).join(' ').length,
        );

        if (diff < bestDiff) {
            bestDiff = diff;
            best = i;
        }
    }

    return [words.slice(0, best).join(' '), words.slice(best).join(' ')];
}

/**
 * Where the artwork goes: cover the art region, but never shrink the art
 * below 85% of the region height (wide art keeps its subject), anchored
 * to the bottom and centered horizontally.
 */
export function artPlacement(
    imageW: number,
    imageH: number,
): { x: number; y: number; w: number; h: number } {
    const regionH = FACE_H - FRONT.artTop;
    const scale = Math.max(
        FACE_W / Math.max(imageW, 1),
        (regionH * 0.85) / Math.max(imageH, 1),
    );
    const w = imageW * scale;
    const h = imageH * scale;

    return { x: (FACE_W - w) / 2, y: FACE_H - h, w, h };
}

/** SVG path of the face outline (rounded rect), optionally with the slot. */
export function facePath(withSlot = true): string {
    const r = FACE_R;
    const outline = `M${r} 0 H${FACE_W - r} Q${FACE_W} 0 ${FACE_W} ${r} V${FACE_H - r} Q${FACE_W} ${FACE_H} ${FACE_W - r} ${FACE_H} H${r} Q0 ${FACE_H} 0 ${FACE_H - r} V${r} Q0 0 ${r} 0 Z`;

    return withSlot ? `${outline} ${slotPath()}` : outline;
}

/** The slot as a closed stadium path (drawn as a hole with evenodd). */
export function slotPath(): string {
    const { cx, cy, halfW, halfH } = SLOT;
    const c = halfW - halfH;

    return `M${cx - c} ${cy - halfH} H${cx + c} A${halfH} ${halfH} 0 0 1 ${cx + c} ${cy + halfH} H${cx - c} A${halfH} ${halfH} 0 0 1 ${cx - c} ${cy - halfH} Z`;
}

/** Gold contour lines of the procedural fallback front. */
export const CONTOUR_PATHS = [
    'M-10 300 C 40 268, 90 304, 140 266 S 200 246, 214 236',
    'M-10 322 C 46 288, 96 326, 148 286 S 204 266, 214 258',
    'M-10 344 C 52 310, 102 348, 156 306 S 206 288, 214 282',
    'M-10 366 C 58 332, 108 370, 164 326 S 208 312, 214 306',
    'M-10 388 C 64 354, 114 392, 172 348 S 210 336, 214 330',
];

/**
 * One palm frond as a filled SVG path: a curved rachis from (x, y) at
 * `angle` (radians) with `count` narrow leaflets on both sides.
 */
export function palmFrond(
    x: number,
    y: number,
    angle: number,
    length: number,
    count = 16,
    curl = 0.35,
): string {
    const parts: string[] = [];
    // Rachis (a thin tapered sliver).
    const steps = 18;
    const left: string[] = [];
    const right: string[] = [];

    for (let i = 0; i <= steps; i++) {
        const t = i / steps;
        const bend = curl * t * t * length * 0.5;
        const px = x + Math.cos(angle) * length * t - Math.sin(angle) * bend;
        const py = y + Math.sin(angle) * length * t + Math.cos(angle) * bend;
        const w = 0.9 * (1 - t) + 0.2;
        left.push(
            `${(px - Math.sin(angle) * w).toFixed(1)} ${(py + Math.cos(angle) * w).toFixed(1)}`,
        );
        right.unshift(
            `${(px + Math.sin(angle) * w).toFixed(1)} ${(py - Math.cos(angle) * w).toFixed(1)}`,
        );
    }

    parts.push(`M${left.join(' L')} L${right.join(' L')} Z`);

    for (let i = 1; i <= count; i++) {
        const t = 0.08 + (i / count) * 0.88;
        const bend = curl * t * t * length * 0.5;
        const bx = x + Math.cos(angle) * length * t - Math.sin(angle) * bend;
        const by = y + Math.sin(angle) * length * t + Math.cos(angle) * bend;
        const leaf = length * 0.42 * Math.sin(Math.PI * (0.18 + t * 0.7));
        const width = 1.6 + 1.4 * (1 - t);

        for (const side of [-1, 1]) {
            const la = angle + side * (0.95 - t * 0.35) + curl * t * 0.6;
            const tx = bx + Math.cos(la) * leaf;
            const ty = by + Math.sin(la) * leaf;
            const nx = -Math.sin(la) * width;
            const ny = Math.cos(la) * width;
            const mx = (bx + tx) / 2 + Math.cos(la + side * 0.5) * leaf * 0.06;
            const my = (by + ty) / 2 + Math.sin(la + side * 0.5) * leaf * 0.06;
            parts.push(
                `M${bx.toFixed(1)} ${by.toFixed(1)} Q${(mx + nx).toFixed(1)} ${(my + ny).toFixed(1)} ${tx.toFixed(1)} ${ty.toFixed(1)} Q${(mx - nx).toFixed(1)} ${(my - ny).toFixed(1)} ${bx.toFixed(1)} ${by.toFixed(1)} Z`,
            );
        }
    }

    return parts.join(' ');
}

/** The back's palm accents: fronds entering from two corners (deck p. 7). */
export const BACK_PALMS: { d: string; light: boolean }[] = [
    { d: palmFrond(-8, -4, 0.72, 96, 15, 0.5), light: false },
    { d: palmFrond(-12, 40, 0.28, 78, 13, 0.6), light: true },
    { d: palmFrond(212, 420, -2.3, 104, 16, 0.45), light: false },
    { d: palmFrond(214, 372, -2.7, 80, 13, 0.55), light: true },
];

/** NFC mark (three arcs + dot), centered on (0, 0), ~ 26 units wide. */
export const NFC_ARCS = [
    'M-4 -6 A 8 8 0 0 1 -4 6',
    'M1 -11 A 14 14 0 0 1 1 11',
    'M6 -16 A 20 20 0 0 1 6 16',
];

/** Resolved header/back text for an artwork. */
export function tagText(options: {
    name: string;
    subtitle?: string | null;
    year?: string | number | null;
    nameLine?: string | null;
    disciplineLine?: string | null;
    backLine?: string | null;
}): { name: string; discipline: string | null; back: string[] } {
    const year =
        options.year === null ||
        options.year === undefined ||
        options.year === ''
            ? null
            : String(options.year);
    const name = (
        options.nameLine?.trim() || displayName(options.name, year)
    ).toLocaleUpperCase('es-MX');
    const fallbackDiscipline = [options.subtitle?.split('·')[0]?.trim(), year]
        .filter(Boolean)
        .join(' ');
    const discipline =
        options.disciplineLine?.trim() || fallbackDiscipline || null;

    return {
        name,
        discipline: discipline ? discipline.toLocaleUpperCase('es-MX') : null,
        back: splitBackLine(
            (options.backLine?.trim() || DEFAULT_BACK_LINE).toLocaleUpperCase(
                'es-MX',
            ),
        ),
    };
}
