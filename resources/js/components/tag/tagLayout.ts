/**
 * Shared type layout for the Finisher Legacy Tag artwork, used by both the
 * SVG drawing (TagVisualizer.vue) and the canvas textures of the 3D model
 * (three/tagArtwork.ts) so the two always read as the same object.
 *
 * Design space: 200 × 340 units (the tag body). Text is fitted to the
 * usable width — the collection name is set as large as it can be (title
 * case Fraunces, up to 2 balanced lines), never overflowing the body.
 *
 * The canvas passes a real `measure` (ctx.measureText); the SVG, which
 * cannot measure before render, passes the `estimateSerif` /
 * `estimateSans` approximations (slightly generous, so they never overflow).
 */

/** Width of `text` at font size 1 (design units). */
export type Measure = (text: string) => number;

export const TAG_ART_W = 200;
export const TAG_ART_H = 340;

/** Usable text width on the face (leaves ~14u of margin per side). */
export const TAG_TEXT_W = 172;

const NARROW = new Set([..."iljtfrI.,:;!|'’ "]);
const WIDE = new Set([...'mwMWÑQOGD@']);

/** Approximate Fraunces width (em) — generous so the SVG never overflows. */
export const estimateSerif: Measure = (text) =>
    [...text].reduce((sum, c) => {
        if (c === ' ') {
            return sum + 0.26;
        }

        if (NARROW.has(c)) {
            return sum + 0.33;
        }

        if (WIDE.has(c)) {
            return sum + 0.86;
        }

        return (
            sum + (c === c.toUpperCase() && c !== c.toLowerCase() ? 0.7 : 0.53)
        );
    }, 0);

/** Approximate Instrument Sans caps width (em), without letter spacing. */
export const estimateSans: Measure = (text) =>
    [...text].reduce(
        (sum, c) =>
            sum +
            (c === ' '
                ? 0.28
                : NARROW.has(c)
                  ? 0.3
                  : WIDE.has(c)
                    ? 0.85
                    : 0.66),
        0,
    );

/**
 * The year is drawn as the big numeral, so a name that already ends with
 * it ("Cozumel 2026") would print it twice — drop the trailing copy.
 */
export function displayName(name: string, year: string | null): string {
    const trimmed = name.trim() || 'Tu colección';

    if (year && trimmed.endsWith(year)) {
        return trimmed.slice(0, -year.length).trim() || trimmed;
    }

    return trimmed;
}

export type NameLayout = {
    lines: string[];
    /** Font size in design units. */
    size: number;
    /** True when even the minimum size needs horizontal compression. */
    squeeze: boolean;
};

/**
 * Largest size for the collection name: one line, or two balanced lines
 * when that makes the type meaningfully bigger.
 */
export function fitName(
    name: string,
    measure: Measure,
    { width = TAG_TEXT_W, max = 62, maxTwo = 50, min = 22 } = {},
): NameLayout {
    const text = name.trim();
    const one = Math.min(max, width / Math.max(measure(text), 0.01));
    const words = text.split(/\s+/).filter(Boolean);
    let best: NameLayout = {
        lines: [text],
        size: one,
        squeeze: one < min,
    };

    if (words.length > 1) {
        for (let i = 1; i < words.length; i++) {
            const a = words.slice(0, i).join(' ');
            const b = words.slice(i).join(' ');
            const size = Math.min(
                maxTwo,
                width / Math.max(measure(a), measure(b), 0.01),
            );

            // Two lines only when it buys real size (≥ 22% bigger).
            if (size > best.size * (best.lines.length === 1 ? 1.22 : 1)) {
                best = { lines: [a, b], size, squeeze: size < min };
            }
        }
    }

    return { ...best, size: Math.max(min, Math.floor(best.size * 10) / 10) };
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

/** Vertical layout of the front (design units), shared by SVG + canvas. */
export function frontLayout(
    name: NameLayout,
    hasSubtitle: boolean,
    hasYear = true,
) {
    const lineGap = name.size * 1.02;
    const cap = name.size * 0.7;
    const sub = hasSubtitle ? 24 : 0;
    const height = (name.lines.length - 1) * lineGap + cap + sub;
    // Centered in the space above the year (or the whole face without one).
    const middle = hasYear ? 152 : 186;
    const first = middle - height / 2 + cap;
    const baselines = name.lines.map((_, i) => first + i * lineGap);
    const last = baselines[baselines.length - 1] ?? 160;

    return {
        brandY: 76,
        ruleY: 88,
        baselines,
        subtitleY: last + sub,
        yearY: 276,
        footerY: 314,
    };
}
