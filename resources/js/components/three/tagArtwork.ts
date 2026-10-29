/**
 * Canvas artwork for the procedural 3D Finisher Legacy Tag (LegacyTag3D).
 *
 * Draws the FRONT (collection artwork) and BACK (NFC + "Toca para ver mi
 * historia", engraved guilloche, illustrative non-scannable QR and a
 * masked code) onto 2D canvases that the scene turns into textures. The
 * layout mirrors the illustrative SVG in components/tag/TagVisualizer.vue
 * (same 200×340 design space, same type hierarchy) so the poster and the
 * 3D model read as the same object.
 *
 * Browser-only: called from tagScene.ts, which is loaded lazily on mount.
 */

export type TagArtworkOptions = {
    name: string;
    subtitle: string | null;
    year: string | null;
    bodyColor: string;
    backColor: string;
    accent: string;
};

/** Design space (matches TagVisualizer's body, minus its 20px margin). */
export const ART_W = 200;
export const ART_H = 340;

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

/** Relative luminance (0 dark … 1 light), to pick ink on custom bodies. */
export function luminance(hex: string): number {
    const [r, g, b] = toRgb(hex).map((c) => {
        const s = c / 255;

        return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4;
    });

    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

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
    ctx.lineWidth = 1.2 * Math.max(scale * 0.8, 1);
    ctx.lineCap = 'round';

    for (let i = 0; i < count; i++) {
        const r = (8 + i * 5) * scale;
        ctx.beginPath();
        ctx.arc(-6 * scale, 0, r, -Math.PI / 3.2, Math.PI / 3.2);
        ctx.stroke();
    }

    ctx.restore();
}

function nameLines(name: string): string[] {
    const upper = name.trim().toUpperCase();
    const words = upper.split(/\s+/).filter(Boolean);

    if (words.length <= 1 || upper.length <= 11) {
        return [upper];
    }

    const half = Math.ceil(upper.length / 2);
    let first = '';
    const rest: string[] = [];

    for (const word of words) {
        if (!rest.length && `${first} ${word}`.trim().length <= half + 3) {
            first = `${first} ${word}`.trim();
        } else {
            rest.push(word);
        }
    }

    return rest.length ? [first, rest.join(' ')] : [first];
}

function displayName(name: string, year: string | null): string {
    const trimmed = name.trim();

    if (year && trimmed.endsWith(year)) {
        return trimmed.slice(0, -year.length).trim() || trimmed;
    }

    return trimmed;
}

function makeCanvas(
    scale: number,
): [HTMLCanvasElement, CanvasRenderingContext2D] {
    const canvas = document.createElement('canvas');
    canvas.width = Math.round(ART_W * scale);
    canvas.height = Math.round(ART_H * scale);
    const ctx = canvas.getContext('2d');

    if (!ctx) {
        throw new Error('2D canvas unavailable');
    }

    ctx.scale(scale, scale);
    ctx.textBaseline = 'alphabetic';

    return [canvas, ctx];
}

/** Waits (bounded) for the brand fonts so the canvas never bakes a fallback. */
export async function loadArtworkFonts(timeoutMs = 1500): Promise<void> {
    if (typeof document === 'undefined' || !('fonts' in document)) {
        return;
    }

    const loads = Promise.all([
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
    scale = 5.12,
): HTMLCanvasElement {
    const [canvas, ctx] = makeCanvas(scale);
    const { bodyColor, accent } = options;
    const light = luminance(bodyColor) > 0.45;
    const ink = light ? INK : '#f8f7f3';
    const brand = light ? GOLD_INK : GOLD_SOFT;

    // Body: a soft diagonal gradient of the collection body color.
    const bg = ctx.createLinearGradient(0, 0, ART_W, ART_H);
    bg.addColorStop(0, shade(bodyColor, light ? 0.25 : 0.12));
    bg.addColorStop(0.45, shade(bodyColor, 0));
    bg.addColorStop(1, shade(bodyColor, light ? -0.08 : -0.35));
    ctx.fillStyle = bg;
    ctx.fillRect(0, 0, ART_W, ART_H);

    // Fine grain so the resin never looks like flat plastic.
    let seed = 7;
    const rand = () => {
        seed = (seed * 1664525 + 1013904223) >>> 0;

        return seed / 4294967296;
    };

    for (let i = 0; i < 2600; i++) {
        ctx.fillStyle =
            rand() > 0.5 ? 'rgba(255,255,255,0.035)' : 'rgba(0,0,0,0.05)';
        ctx.fillRect(rand() * ART_W, rand() * ART_H, 0.6, 0.6);
    }

    // Topographic contour lines in the collection accent.
    ctx.save();
    ctx.translate(-20, 0);
    ctx.strokeStyle = accent;
    ctx.globalAlpha = 0.42;
    ctx.lineWidth = 0.9;
    [
        'M20 300 C 70 262, 120 300, 170 258 S 230 236, 240 220',
        'M20 316 C 76 280, 126 318, 178 274 S 232 254, 240 242',
        'M20 332 C 82 298, 132 336, 186 290 S 234 272, 240 264',
        'M40 350 C 92 316, 140 352, 194 306 S 236 290, 250 286',
        'M20 120 C 60 104, 96 126, 140 106 S 214 92, 240 98',
    ].forEach((d) => ctx.stroke(new Path2D(d)));
    ctx.restore();

    // Brand line + gold rule.
    ctx.fillStyle = brand;
    ctx.font = `600 8.5px ${SANS}`;
    spaced(ctx, 'FINISHER LEGACY', 100, 76, 3.2);
    ctx.fillStyle = GOLD;
    ctx.fillRect(84, 86, 32, 0.8);

    // Collection name.
    const lines = nameLines(displayName(options.name, options.year));
    const longest = Math.max(...lines.map((l) => l.length));
    const size = Math.max(
        15,
        Math.min(32, Math.floor(290 / Math.max(longest, 6))),
    );
    ctx.fillStyle = ink;
    ctx.textAlign = 'center';
    ctx.font = `500 ${size}px ${SERIF}`;
    lines.forEach((line, i) => {
        ctx.fillText(
            line,
            100,
            (lines.length === 1 ? 160 : 146) + i * (size + 4),
            176,
        );
    });

    if (options.subtitle) {
        ctx.fillStyle = light
            ? 'rgba(23,23,20,0.62)'
            : 'rgba(255,255,255,0.62)';
        ctx.font = `600 7.5px ${SANS}`;
        spaced(
            ctx,
            options.subtitle.toUpperCase(),
            100,
            lines.length === 1 ? 184 : 184 + size / 2,
            2.2,
        );
    }

    if (options.year) {
        ctx.strokeStyle = accent;
        ctx.lineWidth = 0.9;
        ctx.font = `400 58px ${SERIF}`;
        ctx.strokeText(options.year, 100, 262);
    }

    // NFC mark + micro copy.
    nfcWaves(ctx, 26, 306, 1, brand);
    ctx.fillStyle = brand;
    ctx.font = `600 6.5px ${SANS}`;
    spaced(ctx, 'TOCA · TU HISTORIA', 42, 309, 1.8, 'left');

    return canvas;
}

export function drawBack(
    options: TagArtworkOptions,
    scale = 5.12,
): HTMLCanvasElement {
    const [canvas, ctx] = makeCanvas(scale);
    const { backColor } = options;
    const light = luminance(backColor) > 0.45;
    const ink = light ? INK : '#f8f7f3';
    const muted = light ? STONE : 'rgba(255,255,255,0.6)';
    const goldText = light ? GOLD_INK : GOLD_SOFT;

    const bg = ctx.createLinearGradient(0, 0, ART_W, ART_H);
    bg.addColorStop(0, shade(backColor, light ? 0.3 : 0.08));
    bg.addColorStop(1, shade(backColor, -0.05));
    ctx.fillStyle = bg;
    ctx.fillRect(0, 0, ART_W, ART_H);

    // Engraved guilloche: fine concentric rings around the NFC seal.
    ctx.save();
    ctx.strokeStyle = light
        ? 'rgba(107,103,94,0.13)'
        : 'rgba(255,255,255,0.08)';
    ctx.lineWidth = 0.35;

    for (let r = 46; r < 150; r += 2.6) {
        ctx.beginPath();

        for (let a = 0; a <= Math.PI * 2 + 0.01; a += 0.04) {
            const wobble = Math.sin(a * 12 + r * 0.3) * 1.1;
            const x = 100 + Math.cos(a) * (r + wobble);
            const y = 168 + Math.sin(a) * (r + wobble);

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
    ctx.strokeStyle = light
        ? 'rgba(133,102,43,0.35)'
        : 'rgba(232,214,173,0.35)';
    ctx.lineWidth = 0.6;
    ctx.beginPath();
    ctx.roundRect(9, 9, ART_W - 18, ART_H - 18, 18);
    ctx.stroke();

    ctx.textAlign = 'center';
    ctx.fillStyle = ink;
    ctx.font = `italic 400 15px ${SERIF}`;
    ctx.fillText('Tu historia vive dentro.', 100, 84);
    ctx.fillStyle = GOLD;
    ctx.fillRect(84, 94, 32, 0.8);

    // NFC seal.
    ctx.fillStyle = light ? 'rgba(255,255,255,0.75)' : 'rgba(0,0,0,0.25)';
    ctx.beginPath();
    ctx.arc(100, 168, 38, 0, Math.PI * 2);
    ctx.fill();
    ctx.strokeStyle = goldText;
    ctx.lineWidth = 1.1;
    ctx.beginPath();
    ctx.arc(100, 168, 38, 0, Math.PI * 2);
    ctx.stroke();
    ctx.lineWidth = 0.4;
    ctx.beginPath();
    ctx.arc(100, 168, 42, 0, Math.PI * 2);
    ctx.stroke();
    nfcWaves(ctx, 98, 168, 1.55, goldText);
    ctx.fillStyle = goldText;
    ctx.beginPath();
    ctx.arc(88, 168, 2.2, 0, Math.PI * 2);
    ctx.fill();

    ctx.fillStyle = ink;
    ctx.font = `500 13px ${SERIF}`;
    ctx.fillText('Toca para ver mi historia', 100, 232);
    ctx.fillStyle = muted;
    ctx.font = `600 5.6px ${SANS}`;
    spaced(ctx, 'ACERCA TU TELÉFONO', 100, 245, 1.6);

    // Illustrative QR (NOT scannable — no URL is encoded).
    const qx = 30;
    const qy = 262;
    const cell = 2;
    ctx.fillStyle = '#ffffff';
    ctx.beginPath();
    ctx.roundRect(qx - 3, qy - 3, 21 * cell + 6, 21 * cell + 6, 3);
    ctx.fill();
    ctx.fillStyle = INK;
    let seed = 0;

    for (const ch of options.name + (options.year ?? '')) {
        seed = (seed * 31 + ch.charCodeAt(0)) >>> 0;
    }

    const rand = () => {
        seed = (seed * 1664525 + 1013904223) >>> 0;

        return seed / 4294967296;
    };

    const inFinder = (x: number, y: number) =>
        (x < 8 && y < 8) || (x > 12 && y < 8) || (x < 8 && y > 12);

    for (let y = 0; y < 21; y++) {
        for (let x = 0; x < 21; x++) {
            if (!inFinder(x, y) && rand() > 0.52) {
                ctx.fillRect(qx + x * cell, qy + y * cell, cell, cell);
            }
        }
    }

    for (const [fx, fy] of [
        [0, 0],
        [14, 0],
        [0, 14],
    ]) {
        ctx.fillStyle = INK;
        ctx.fillRect(qx + fx * cell, qy + fy * cell, 7 * cell, 7 * cell);
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(
            qx + (fx + 1) * cell,
            qy + (fy + 1) * cell,
            5 * cell,
            5 * cell,
        );
        ctx.fillStyle = INK;
        ctx.fillRect(
            qx + (fx + 2) * cell,
            qy + (fy + 2) * cell,
            3 * cell,
            3 * cell,
        );
    }

    ctx.textAlign = 'left';
    ctx.fillStyle = muted;
    ctx.font = `600 5.4px ${SANS}`;
    spaced(ctx, '¿SIN NFC?', 84, 272, 1.4, 'left');
    ctx.fillStyle = ink;
    ctx.font = `400 7px ${SANS}`;
    ctx.fillText('Escanea el QR o', 84, 284);
    ctx.fillText('escribe tu código.', 84, 293);
    ctx.font = `400 8.5px ${MONO}`;
    spaced(ctx, '•••• ••••', 84, 306, 1.4, 'left');

    ctx.textAlign = 'center';
    ctx.fillStyle = muted;
    ctx.font = `600 5.6px ${SANS}`;
    spaced(ctx, 'FINISHER LEGACY', 100, 324, 2.6);

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
