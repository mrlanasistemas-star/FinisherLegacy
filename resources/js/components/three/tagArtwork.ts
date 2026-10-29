/**
 * Canvas artwork for the procedural 3D Finisher Legacy Tag.
 *
 * FRONT: the collection artwork — brand line, the collection name set as
 * large as it fits (title-case Fraunces, up to two balanced lines), the
 * subtitle in spaced caps, the year as a big outlined numeral and the NFC
 * mark. BACK (2026-10-29: NFC only, no QR): "Tu historia vive dentro.", a
 * big NFC seal inside engraved guilloche rings, "Acerca tu celular" and
 * the masked unit code.
 *
 * Text is fitted with the real canvas measurements through the shared
 * layout in components/tag/tagLayout.ts (the SVG TagVisualizer uses the
 * same one), at 7 px per design unit (1400 × 2380) so it stays crisp at
 * hero size with anisotropic filtering.
 *
 * Browser-only: called from the lazily loaded three.js modules.
 */
import type { Measure } from '@/components/tag/tagLayout';
import {
    TAG_ART_H,
    TAG_ART_W,
    TAG_TEXT_W,
    displayName,
    fitLine,
    fitName,
    frontLayout,
} from '@/components/tag/tagLayout';

export type TagArtworkOptions = {
    name: string;
    subtitle: string | null;
    year: string | null;
    bodyColor: string;
    backColor: string;
    accent: string;
};

export const ART_W = TAG_ART_W;
export const ART_H = TAG_ART_H;
export const ART_SCALE = 7;

const GOLD = '#c9a45c';
const GOLD_SOFT = '#e8d6ad';
const GOLD_INK = '#85662b';
const INK = '#171714';
const STONE = '#6b675e';

const SERIF = 'Fraunces, Georgia, "Times New Roman", serif';
const SANS = '"Instrument Sans", ui-sans-serif, system-ui, sans-serif';
const MONO = 'ui-monospace, SFMono-Regular, Menlo, monospace';

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

/** Relative luminance (0 dark … 1 light), to pick ink on custom bodies. */
export function luminance(hex: string): number {
    const [r, g, b] = toRgb(hex).map((c) => {
        const s = c / 255;

        return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4;
    });

    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

/** Draws spaced text (spacing in design units). */
function spaced(
    ctx: CanvasRenderingContext2D,
    text: string,
    x: number,
    y: number,
    spacing: number,
    align: 'left' | 'center' = 'center',
) {
    const chars = [...text];
    const widths = chars.map((c) => ctx.measureText(c).width);
    const total =
        widths.reduce((sum, w) => sum + w, 0) + spacing * (chars.length - 1);
    let cursor = align === 'center' ? x - total / 2 : x;
    const previous = ctx.textAlign;
    ctx.textAlign = 'left';

    chars.forEach((c, i) => {
        ctx.fillText(c, cursor, y);
        cursor += widths[i] + spacing;
    });

    ctx.textAlign = previous;
}

/** Measure at font size 1 for the given font template (`${size}` → 100). */
function measurer(ctx: CanvasRenderingContext2D, font: string): Measure {
    return (text) => {
        ctx.font = font.replace('{s}', '100');

        return ctx.measureText(text).width / 100;
    };
}

function nfcWaves(
    ctx: CanvasRenderingContext2D,
    x: number,
    y: number,
    scale: number,
    color: string,
    count = 3,
) {
    ctx.save();
    ctx.translate(x, y);
    ctx.strokeStyle = color;
    ctx.lineWidth = 1.5 * Math.max(scale * 0.75, 1);
    ctx.lineCap = 'round';

    for (let i = 0; i < count; i++) {
        const r = (8 + i * 5.5) * scale;
        ctx.beginPath();
        ctx.arc(-6 * scale, 0, r, -Math.PI / 3.4, Math.PI / 3.4);
        ctx.stroke();
    }

    ctx.fillStyle = color;
    ctx.beginPath();
    ctx.arc(-6 * scale + 2 * scale, 0, 2 * scale, 0, Math.PI * 2);
    ctx.fill();
    ctx.restore();
}

function prepare(
    scale: number,
    target?: HTMLCanvasElement,
): [HTMLCanvasElement, CanvasRenderingContext2D] {
    const canvas = target ?? document.createElement('canvas');
    canvas.width = Math.round(ART_W * scale);
    canvas.height = Math.round(ART_H * scale);
    const ctx = canvas.getContext('2d');

    if (!ctx) {
        throw new Error('2D canvas unavailable');
    }

    ctx.setTransform(scale, 0, 0, scale, 0, 0);
    ctx.textBaseline = 'alphabetic';
    ctx.textAlign = 'left';
    ctx.globalAlpha = 1;

    return [canvas, ctx];
}

function grain(ctx: CanvasRenderingContext2D, seedStart: number) {
    let seed = seedStart;
    const rand = () => {
        seed = (seed * 1664525 + 1013904223) >>> 0;

        return seed / 4294967296;
    };

    for (let i = 0; i < 3200; i++) {
        ctx.fillStyle =
            rand() > 0.5 ? 'rgba(255,255,255,0.035)' : 'rgba(0,0,0,0.05)';
        ctx.fillRect(rand() * ART_W, rand() * ART_H, 0.5, 0.5);
    }
}

/** Waits (bounded) for the brand fonts so the canvas never bakes a fallback. */
export async function loadArtworkFonts(timeoutMs = 1500): Promise<void> {
    if (typeof document === 'undefined' || !('fonts' in document)) {
        return;
    }

    const loads = Promise.all([
        document.fonts.load(`600 40px ${SERIF}`),
        document.fonts.load(`500 40px ${SERIF}`),
        document.fonts.load(`italic 400 40px ${SERIF}`),
        document.fonts.load(`600 12px ${SANS}`),
    ]).catch(() => undefined);

    await Promise.race([
        loads,
        new Promise((resolve) => setTimeout(resolve, timeoutMs)),
    ]);
}

export function drawFront(
    options: TagArtworkOptions,
    scale = ART_SCALE,
    target?: HTMLCanvasElement,
): HTMLCanvasElement {
    const [canvas, ctx] = prepare(scale, target);
    const { bodyColor } = options;
    // Normalized #rrggbb so alpha suffixes below are valid.
    const accent = mix(options.accent, options.accent, 0);
    const light = luminance(bodyColor) > 0.45;
    const ink = light ? INK : '#fbfaf6';
    const brand = light ? GOLD_INK : GOLD_SOFT;

    // Body: a soft diagonal gradient of the collection body color.
    const bg = ctx.createLinearGradient(0, 0, ART_W, ART_H);
    bg.addColorStop(0, shade(bodyColor, light ? 0.25 : 0.14));
    bg.addColorStop(0.45, shade(bodyColor, 0));
    bg.addColorStop(1, shade(bodyColor, light ? -0.08 : -0.32));
    ctx.fillStyle = bg;
    ctx.fillRect(0, 0, ART_W, ART_H);

    // A warm glow of the accent behind the name.
    const glow = ctx.createRadialGradient(100, 170, 4, 100, 170, 140);
    glow.addColorStop(0, `${accent}33`);
    glow.addColorStop(1, `${accent}00`);
    ctx.fillStyle = glow;
    ctx.fillRect(0, 0, ART_W, ART_H);
    grain(ctx, 7);

    // Topographic contour lines in the collection accent.
    ctx.save();
    ctx.translate(-20, 0);
    ctx.strokeStyle = accent;
    ctx.globalAlpha = 0.5;
    ctx.lineWidth = 0.9;
    [
        'M20 300 C 70 262, 120 300, 170 258 S 230 236, 240 220',
        'M20 316 C 76 280, 126 318, 178 274 S 232 254, 240 242',
        'M20 332 C 82 298, 132 336, 186 290 S 234 272, 240 264',
        'M40 350 C 92 316, 140 352, 194 306 S 236 290, 250 286',
        'M20 112 C 60 96, 96 118, 140 98 S 214 84, 240 90',
    ].forEach((d) => ctx.stroke(new Path2D(d)));
    ctx.restore();

    const sansCaps = measurer(ctx, `600 {s}px ${SANS}`);
    const serifName = measurer(ctx, `600 {s}px ${SERIF}`);
    const name = fitName(displayName(options.name, options.year), serifName);
    const layout = frontLayout(name, !!options.subtitle, !!options.year);

    // Brand line + gold rule.
    ctx.fillStyle = brand;
    ctx.font = `600 10px ${SANS}`;
    spaced(ctx, 'FINISHER LEGACY', 100, layout.brandY, 3.4);
    ctx.fillStyle = GOLD;
    ctx.fillRect(86, layout.ruleY, 28, 1);

    // Collection name — as large as it fits.
    ctx.fillStyle = ink;
    ctx.textAlign = 'center';
    ctx.font = `600 ${name.size}px ${SERIF}`;
    name.lines.forEach((line, i) => {
        ctx.fillText(line, 100, layout.baselines[i], TAG_TEXT_W);
    });

    if (options.subtitle) {
        const text = options.subtitle.toUpperCase();
        const size = fitLine(text, sansCaps, { max: 11.5, min: 7.5 });
        ctx.fillStyle = light ? 'rgba(23,23,20,0.72)' : 'rgba(255,255,255,0.8)';
        ctx.font = `600 ${size}px ${SANS}`;
        spaced(ctx, text, 100, layout.subtitleY, size * 0.18);
    }

    if (options.year) {
        const size = Math.min(
            80,
            TAG_TEXT_W /
                Math.max(
                    measurer(ctx, `400 {s}px ${SERIF}`)(options.year),
                    0.01,
                ),
        );
        ctx.font = `400 ${size}px ${SERIF}`;
        ctx.textAlign = 'center';
        ctx.fillStyle = `${accent}26`;
        ctx.fillText(options.year, 100, layout.yearY);
        ctx.strokeStyle = accent;
        ctx.lineWidth = 1.2;
        ctx.strokeText(options.year, 100, layout.yearY);
    }

    // NFC mark + micro copy.
    nfcWaves(ctx, 30, layout.footerY - 3.5, 0.9, brand);
    ctx.fillStyle = brand;
    ctx.font = `600 8px ${SANS}`;
    spaced(ctx, 'ACERCA TU CELULAR', 46, layout.footerY, 1.6, 'left');

    return canvas;
}

export function drawBack(
    options: TagArtworkOptions,
    scale = ART_SCALE,
    target?: HTMLCanvasElement,
): HTMLCanvasElement {
    const [canvas, ctx] = prepare(scale, target);
    const { backColor } = options;
    const light = luminance(backColor) > 0.45;
    const ink = light ? INK : '#f8f7f3';
    const muted = light ? STONE : 'rgba(255,255,255,0.7)';
    const goldText = light ? GOLD_INK : GOLD_SOFT;
    const cx = 100;
    const cy = 168;

    const bg = ctx.createLinearGradient(0, 0, ART_W, ART_H);
    bg.addColorStop(0, shade(backColor, light ? 0.3 : 0.08));
    bg.addColorStop(1, shade(backColor, -0.05));
    ctx.fillStyle = bg;
    ctx.fillRect(0, 0, ART_W, ART_H);
    grain(ctx, 11);

    // Engraved guilloche: fine concentric rings around the NFC seal.
    ctx.save();
    ctx.strokeStyle = light
        ? 'rgba(107,103,94,0.16)'
        : 'rgba(255,255,255,0.09)';
    ctx.lineWidth = 0.35;

    for (let r = 62; r < 160; r += 2.8) {
        ctx.beginPath();

        for (let a = 0; a <= Math.PI * 2 + 0.01; a += 0.035) {
            const wobble = Math.sin(a * 12 + r * 0.3) * 1.1;
            const x = cx + Math.cos(a) * (r + wobble);
            const y = cy + Math.sin(a) * (r + wobble);

            if (a === 0) {
                ctx.moveTo(x, y);
            } else {
                ctx.lineTo(x, y);
            }
        }

        ctx.stroke();
    }

    ctx.restore();

    // Inset hairline frame (engraved border).
    ctx.strokeStyle = light ? 'rgba(133,102,43,0.4)' : 'rgba(232,214,173,0.4)';
    ctx.lineWidth = 0.7;
    ctx.beginPath();
    ctx.roundRect(9, 9, ART_W - 18, ART_H - 18, 18);
    ctx.stroke();

    ctx.textAlign = 'center';
    ctx.fillStyle = ink;
    ctx.font = `italic 400 20px ${SERIF}`;
    ctx.fillText('Tu historia', cx, 78);
    ctx.fillText('vive dentro.', cx, 100);
    ctx.fillStyle = GOLD;
    ctx.fillRect(86, 110, 28, 1);

    // NFC seal — big.
    ctx.fillStyle = light ? 'rgba(255,255,255,0.82)' : 'rgba(0,0,0,0.25)';
    ctx.beginPath();
    ctx.arc(cx, cy, 50, 0, Math.PI * 2);
    ctx.fill();
    ctx.strokeStyle = goldText;
    ctx.lineWidth = 1.6;
    ctx.beginPath();
    ctx.arc(cx, cy, 50, 0, Math.PI * 2);
    ctx.stroke();
    ctx.lineWidth = 0.5;
    ctx.beginPath();
    ctx.arc(cx, cy, 55, 0, Math.PI * 2);
    ctx.stroke();
    nfcWaves(ctx, cx + 4, cy, 2.05, goldText);

    ctx.fillStyle = ink;
    ctx.font = `600 21px ${SERIF}`;
    ctx.fillText('Acerca tu celular', cx, 250, TAG_TEXT_W);
    ctx.fillStyle = muted;
    ctx.font = `600 9.5px ${SANS}`;
    spaced(ctx, 'SIN APPS · SE ABRE SOLO', cx, 268, 1.4);

    // Masked unit code (each tag has its own; never real data here).
    ctx.fillStyle = goldText;
    ctx.font = `600 13px ${MONO}`;
    spaced(ctx, 'N.º •••• ••••', cx, 297, 1.4);

    ctx.fillStyle = muted;
    ctx.font = `600 8.5px ${SANS}`;
    spaced(ctx, 'FINISHER LEGACY', cx, 320, 2.8);

    return canvas;
}

/** Woven strap texture (tileable along the strap length). */
export function drawStrap(color: string, stitch: string): HTMLCanvasElement {
    const canvas = document.createElement('canvas');
    canvas.width = 64;
    canvas.height = 64;
    const ctx = canvas.getContext('2d');

    if (!ctx) {
        return canvas;
    }

    ctx.fillStyle = color;
    ctx.fillRect(0, 0, 64, 64);
    ctx.fillStyle = shade(color.startsWith('#') ? color : '#2a2925', -0.35);

    for (let i = -64; i < 128; i += 6) {
        ctx.save();
        ctx.translate(i, 0);
        ctx.rotate(0.6);
        ctx.fillRect(0, -10, 2.6, 120);
        ctx.restore();
    }

    // Stitch line along both edges.
    ctx.fillStyle = stitch;
    ctx.globalAlpha = 0.85;

    for (let y = 0; y < 64; y += 8) {
        ctx.fillRect(6, y, 2, 4);
        ctx.fillRect(56, y, 2, 4);
    }

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
