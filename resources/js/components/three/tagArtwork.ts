/**
 * Canvas artwork for the 3D Finisher Legacy Tag — the collection designs
 * of the strategy deck (pp. 7–8), drawn with the shared layout in
 * components/tag/tagLayout.ts (the SVG TagVisualizer uses the same one):
 *
 * FRONT: the collection's real destination artwork (`tag_design.front_art_url`,
 * cover-fit below the slot and fading into carbon under the header) + the
 * header: the original FL monogram IMAGE (never typed letters), a thin
 * gold rule, the name in spaced caps and the discipline line. Without
 * artwork: the procedural fallback (carbon + gold contour lines + outlined
 * year). BACK: carbon black, palm accents, monogram, "TU HISTORIA SIGUE
 * VIVA" and the NFC mark. No QR anywhere.
 *
 * Images are drawn only once loaded (`loadTagImages`); the model redraws
 * when they arrive. Browser-only: called from the lazily loaded three.js
 * modules.
 */
import type { Measure } from '@/components/tag/tagLayout';
import {
    BACK,
    BACK_PALMS,
    BACK_SPACING,
    CONTOUR_PATHS,
    DISCIPLINE_SPACING,
    FACE_H,
    FACE_W,
    FRONT,
    MONOGRAM_RATIO,
    MONOGRAM_SRC,
    NAME_SPACING,
    NFC_ARCS,
    TAG_COLORS,
    TAG_TEXT_W,
    artPlacement,
    disciplineSize,
    fitLine,
    nameSize,
    tagText,
} from '@/components/tag/tagLayout';

export type TagArtworkOptions = {
    name: string;
    subtitle: string | null;
    year: string | null;
    bodyColor: string;
    backColor: string;
    accent: string;
    /** `tag_design` fields (absent → procedural front). */
    frontArt?: string | null;
    nameLine?: string | null;
    disciplineLine?: string | null;
    backLine?: string | null;
};

export const ART_W = FACE_W;
export const ART_H = FACE_H;
/** 6 px per design unit (1200 × 2460): crisp at hero size. */
export const ART_SCALE = 6;

const SANS =
    'Montserrat, "Instrument Sans", ui-sans-serif, system-ui, sans-serif';

// ---- Images (monogram + artwork) ------------------------------------------
const images = new Map<string, HTMLImageElement | null>();
const pending = new Map<string, Promise<HTMLImageElement | null>>();

function loadImage(src: string): Promise<HTMLImageElement | null> {
    if (images.has(src)) {
        return Promise.resolve(images.get(src) ?? null);
    }

    const inflight = pending.get(src);

    if (inflight) {
        return inflight;
    }

    const promise = new Promise<HTMLImageElement | null>((resolve) => {
        const img = new Image();
        img.decoding = 'async';

        // Cross-origin art (CDN / another host) must be CORS-clean for WebGL.
        try {
            if (
                new URL(src, window.location.href).origin !==
                window.location.origin
            ) {
                img.crossOrigin = 'anonymous';
            }
        } catch {
            // Relative / odd URL: same origin.
        }

        img.onload = () => {
            images.set(src, img);
            pending.delete(src);
            resolve(img);
        };
        img.onerror = () => {
            images.set(src, null);
            pending.delete(src);
            resolve(null);
        };
        img.src = src;
    });
    pending.set(src, promise);

    return promise;
}

function cached(src: string | null | undefined): HTMLImageElement | null {
    return src ? (images.get(src) ?? null) : null;
}

/** True once every image this artwork needs has settled (loaded or failed). */
export function tagImagesReady(options: TagArtworkOptions): boolean {
    return (
        images.has(MONOGRAM_SRC) &&
        (!options.frontArt || images.has(options.frontArt))
    );
}

/** Loads the monogram + the front artwork (never rejects). */
export async function loadTagImages(options: TagArtworkOptions): Promise<void> {
    await Promise.all([
        loadImage(MONOGRAM_SRC),
        options.frontArt ? loadImage(options.frontArt) : Promise.resolve(null),
    ]);
}

// ---- Color helpers ----------------------------------------------------------
type Rgb = [number, number, number];

function toRgb(hex: string): Rgb {
    const clean = hex.replace('#', '');
    const full =
        clean.length === 3
            ? clean
                  .split('')
                  .map((c) => c + c)
                  .join('')
            : clean.padEnd(6, '0').slice(0, 6);
    const value = Number.parseInt(full, 16);

    if (Number.isNaN(value)) {
        return [42, 41, 37];
    }

    return [(value >> 16) & 255, (value >> 8) & 255, value & 255];
}

/** Mix a hex color toward white (amount > 0) or black (amount < 0). */
export function shade(hex: string, amount: number): string {
    const target = amount >= 0 ? 255 : 0;
    const t = Math.abs(amount);
    const [r, g, b] = toRgb(hex).map((c) => Math.round(c + (target - c) * t));

    return `rgb(${r}, ${g}, ${b})`;
}

/** Mix two hex colors (t = share of `b`), returned as hex. */
export function mix(a: string, b: string, t: number): string {
    const ca = toRgb(a);
    const cb = toRgb(b);

    return `#${ca
        .map((c, i) =>
            Math.round(c + (cb[i] - c) * t)
                .toString(16)
                .padStart(2, '0'),
        )
        .join('')}`;
}

/** Relative luminance (0 dark … 1 light). */
export function luminance(hex: string): number {
    const [r, g, b] = toRgb(hex).map((c) => {
        const s = c / 255;

        return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4;
    });

    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

// ---- Drawing primitives -------------------------------------------------------
/** Draws spaced caps centered on x (spacing in design units). */
function spaced(
    ctx: CanvasRenderingContext2D,
    text: string,
    x: number,
    y: number,
    spacing: number,
) {
    const chars = [...text];
    const widths = chars.map((c) => ctx.measureText(c).width);
    const total =
        widths.reduce((sum, w) => sum + w, 0) + spacing * (chars.length - 1);
    let cursor = x - total / 2;
    ctx.textAlign = 'left';

    chars.forEach((c, i) => {
        ctx.fillText(c, cursor, y);
        cursor += widths[i] + spacing;
    });
}

/** Measure at font size 1 for a font template (`{s}` → 100). */
function measurer(ctx: CanvasRenderingContext2D, font: string): Measure {
    return (text) => {
        ctx.font = font.replace('{s}', '100');

        return ctx.measureText(text).width / 100;
    };
}

function prepare(
    scale: number,
    target?: HTMLCanvasElement,
): [HTMLCanvasElement, CanvasRenderingContext2D] {
    const canvas = target ?? document.createElement('canvas');
    canvas.width = Math.round(FACE_W * scale);
    canvas.height = Math.round(FACE_H * scale);
    const ctx = canvas.getContext('2d');

    if (!ctx) {
        throw new Error('2D canvas unavailable');
    }

    ctx.setTransform(scale, 0, 0, scale, 0, 0);
    ctx.textBaseline = 'alphabetic';
    ctx.textAlign = 'left';
    ctx.globalAlpha = 1;
    ctx.shadowColor = 'transparent';
    ctx.shadowBlur = 0;

    return [canvas, ctx];
}

function carbon(ctx: CanvasRenderingContext2D) {
    const bg = ctx.createLinearGradient(0, 0, FACE_W, FACE_H);
    bg.addColorStop(0, TAG_COLORS.carbonLight);
    bg.addColorStop(0.55, TAG_COLORS.carbon);
    bg.addColorStop(1, '#050506');
    ctx.fillStyle = bg;
    ctx.fillRect(0, 0, FACE_W, FACE_H);
}

function grain(
    ctx: CanvasRenderingContext2D,
    seedStart: number,
    amount = 2600,
) {
    let seed = seedStart;
    const rand = () => {
        seed = (seed * 1664525 + 1013904223) >>> 0;

        return seed / 4294967296;
    };

    for (let i = 0; i < amount; i++) {
        ctx.fillStyle =
            rand() > 0.5 ? 'rgba(255,255,255,0.03)' : 'rgba(0,0,0,0.05)';
        ctx.fillRect(rand() * FACE_W, rand() * FACE_H, 0.5, 0.5);
    }
}

/** The original monogram centered on (cx, cy), `width` units wide. */
function monogram(
    ctx: CanvasRenderingContext2D,
    cx: number,
    cy: number,
    width: number,
) {
    const img = cached(MONOGRAM_SRC);

    if (!img || !img.naturalWidth) {
        return;
    }

    const height =
        width * (img.naturalHeight / img.naturalWidth || MONOGRAM_RATIO);
    ctx.drawImage(img, cx - width / 2, cy - height / 2, width, height);
}

function nfcMark(
    ctx: CanvasRenderingContext2D,
    x: number,
    y: number,
    scale: number,
    color: string,
) {
    ctx.save();
    ctx.translate(x, y);
    ctx.scale(scale, scale);
    ctx.strokeStyle = color;
    ctx.lineWidth = 2.2;
    ctx.lineCap = 'round';
    NFC_ARCS.forEach((d) => ctx.stroke(new Path2D(d)));
    ctx.fillStyle = color;
    ctx.beginPath();
    ctx.arc(-8, 0, 2, 0, Math.PI * 2);
    ctx.fill();
    ctx.restore();
}

/** Waits (bounded) for the brand font + monogram so nothing bakes a fallback. */
export async function loadArtworkFonts(timeoutMs = 1500): Promise<void> {
    if (typeof document === 'undefined') {
        return;
    }

    const loads = Promise.all([
        'fonts' in document
            ? document.fonts.load(`600 40px ${SANS}`)
            : Promise.resolve(),
        'fonts' in document
            ? document.fonts.load(`500 20px ${SANS}`)
            : Promise.resolve(),
        loadImage(MONOGRAM_SRC),
    ]).catch(() => undefined);

    await Promise.race([
        loads,
        new Promise((resolve) => setTimeout(resolve, timeoutMs)),
    ]);
}

// ---- Faces ----------------------------------------------------------------------
export function drawFront(
    options: TagArtworkOptions,
    scale = ART_SCALE,
    target?: HTMLCanvasElement,
): HTMLCanvasElement {
    const [canvas, ctx] = prepare(scale, target);
    const accent = mix(options.accent, options.accent, 0);
    const text = tagText(options);
    const art = cached(options.frontArt);

    carbon(ctx);

    if (art && art.naturalWidth) {
        const place = artPlacement(art.naturalWidth, art.naturalHeight);
        ctx.drawImage(art, place.x, place.y, place.w, place.h);

        // Carbon above the art, then a soft fade into it under the header.
        const fadeTop = Math.max(FRONT.artTop, place.y);
        ctx.fillStyle = TAG_COLORS.carbon;
        ctx.fillRect(0, 0, FACE_W, fadeTop);
        const fade = ctx.createLinearGradient(
            0,
            fadeTop,
            0,
            fadeTop + FRONT.artFade,
        );
        fade.addColorStop(0, 'rgba(11,11,12,1)');
        fade.addColorStop(0.55, 'rgba(11,11,12,0.45)');
        fade.addColorStop(1, 'rgba(11,11,12,0)');
        ctx.fillStyle = fade;
        ctx.fillRect(0, fadeTop - 0.5, FACE_W, FRONT.artFade + 1);

        // A gentle edge vignette so the print reads inset in the resin.
        const vignette = ctx.createRadialGradient(100, 250, 80, 100, 250, 240);
        vignette.addColorStop(0, 'rgba(0,0,0,0)');
        vignette.addColorStop(1, 'rgba(0,0,0,0.38)');
        ctx.fillStyle = vignette;
        ctx.fillRect(0, 0, FACE_W, FACE_H);
    } else {
        // Procedural fallback: accent glow + gold contour lines + year.
        const glow = ctx.createRadialGradient(100, 300, 6, 100, 300, 190);
        glow.addColorStop(0, `${accent}30`);
        glow.addColorStop(1, `${accent}00`);
        ctx.fillStyle = glow;
        ctx.fillRect(0, 0, FACE_W, FACE_H);

        ctx.save();
        ctx.strokeStyle = TAG_COLORS.gold;
        ctx.globalAlpha = 0.42;
        ctx.lineWidth = 0.8;
        CONTOUR_PATHS.forEach((d) => ctx.stroke(new Path2D(d)));
        ctx.restore();

        if (options.year) {
            const size = Math.min(
                76,
                TAG_TEXT_W /
                    Math.max(
                        measurer(ctx, `300 {s}px ${SANS}`)(options.year),
                        0.01,
                    ),
            );
            ctx.font = `300 ${size}px ${SANS}`;
            ctx.textAlign = 'center';
            ctx.strokeStyle = TAG_COLORS.gold;
            ctx.lineWidth = 0.9;
            ctx.globalAlpha = 0.85;
            ctx.strokeText(options.year, 100, 236);
            ctx.globalAlpha = 1;
        }
    }

    grain(ctx, 7);

    // Header (deck pp. 7–8): monogram, gold rule, name, discipline line.
    monogram(ctx, 100, FRONT.monogramY, FRONT.monogramW);
    ctx.fillStyle = TAG_COLORS.gold;
    ctx.fillRect(100 - FRONT.ruleW / 2, FRONT.ruleY, FRONT.ruleW, 0.8);

    ctx.shadowColor = 'rgba(0,0,0,0.55)';
    ctx.shadowBlur = 2.5 * scale;
    const caps = measurer(ctx, `600 {s}px ${SANS}`);
    const size = nameSize(text.name, caps);
    ctx.fillStyle = '#f1e6d4';
    ctx.font = `600 ${size}px ${SANS}`;
    spaced(ctx, text.name, 100, FRONT.nameY, size * NAME_SPACING);

    if (text.discipline) {
        const small = disciplineSize(
            text.discipline,
            measurer(ctx, `500 {s}px ${SANS}`),
        );
        ctx.fillStyle = TAG_COLORS.goldSoft;
        ctx.font = `500 ${small}px ${SANS}`;
        spaced(
            ctx,
            text.discipline,
            100,
            FRONT.disciplineY,
            small * DISCIPLINE_SPACING,
        );
    }

    ctx.shadowColor = 'transparent';
    ctx.shadowBlur = 0;

    return canvas;
}

export function drawBack(
    options: TagArtworkOptions,
    scale = ART_SCALE,
    target?: HTMLCanvasElement,
): HTMLCanvasElement {
    const [canvas, ctx] = prepare(scale, target);
    const text = tagText(options);

    carbon(ctx);

    // Subtle palm accents from two corners (deck p. 7).
    BACK_PALMS.forEach(({ d, light }) => {
        ctx.fillStyle = light ? TAG_COLORS.palmLight : TAG_COLORS.palm;
        ctx.globalAlpha = light ? 0.38 : 0.62;
        ctx.fill(new Path2D(d));
    });
    ctx.globalAlpha = 1;
    grain(ctx, 11);

    monogram(ctx, 100, BACK.monogramY, BACK.monogramW);
    ctx.fillStyle = TAG_COLORS.gold;
    ctx.fillRect(100 - BACK.ruleW / 2, BACK.ruleY, BACK.ruleW, 0.8);

    const caps = measurer(ctx, `500 {s}px ${SANS}`);
    const size = Math.min(
        ...text.back.map((line) =>
            fitLine(line, caps, {
                max: 9.5,
                min: 6,
                spacing: BACK_SPACING,
                width: 150,
            }),
        ),
    );
    ctx.fillStyle = TAG_COLORS.goldSoft;
    ctx.font = `500 ${size}px ${SANS}`;
    text.back.forEach((line, i) => {
        spaced(
            ctx,
            line,
            100,
            BACK.lineY + i * BACK.lineGap,
            size * BACK_SPACING,
        );
    });

    nfcMark(ctx, 102, BACK.nfcY, 0.9, TAG_COLORS.gold);

    return canvas;
}

/** Soft radial contact shadow. */
export function drawShadow(): HTMLCanvasElement {
    const canvas = document.createElement('canvas');
    canvas.width = 256;
    canvas.height = 128;
    const ctx = canvas.getContext('2d');

    if (!ctx) {
        return canvas;
    }

    ctx.translate(128, 64);
    ctx.scale(2, 1);
    const g = ctx.createRadialGradient(0, 0, 0, 0, 0, 62);
    g.addColorStop(0, 'rgba(23,23,20,0.55)');
    g.addColorStop(0.45, 'rgba(23,23,20,0.22)');
    g.addColorStop(1, 'rgba(23,23,20,0)');
    ctx.fillStyle = g;
    ctx.beginPath();
    ctx.arc(0, 0, 62, 0, Math.PI * 2);
    ctx.fill();

    return canvas;
}

/** Soft ring sprite for the NFC pulse (white; tinted by the material). */
export function drawPulseRing(): HTMLCanvasElement {
    const canvas = document.createElement('canvas');
    canvas.width = 256;
    canvas.height = 256;
    const ctx = canvas.getContext('2d');

    if (!ctx) {
        return canvas;
    }

    const g = ctx.createRadialGradient(128, 128, 96, 128, 128, 126);
    g.addColorStop(0, 'rgba(255,255,255,0)');
    g.addColorStop(0.55, 'rgba(255,255,255,1)');
    g.addColorStop(1, 'rgba(255,255,255,0)');
    ctx.fillStyle = g;
    ctx.fillRect(0, 0, 256, 256);

    return canvas;
}
