/**
 * Pure text rules of the translator (docs/renovacion/idiomas.md §Notas
 * para quien traduce, §Frases con enlaces, §Ordinales, §Dinero). No
 * imports on purpose: the same file is unit-tested with plain Node
 * (`node --test resources/js/i18n/text.test.mjs`) and mirrored in the app
 * (`src/i18n/text.ts`).
 */

/**
 * Separates a source string from its translator note:
 *
 *     t('Nombre||nombre completo del atleta')
 *
 * The note tells the translator WHICH "Nombre" this is. It is part of the
 * key (two contexts → two translations) and is never shown, in any
 * language — Spanish included.
 */
export const NOTE_SEPARATOR = '||';

/** `"Nombre||nombre completo"` → `"Nombre"`. */
export function stripNote(text: string): string {
    const at = text.indexOf(NOTE_SEPARATOR);

    return at === -1 ? text : text.slice(0, at).trimEnd();
}

export type Segment =
    | { type: 'text'; value: string }
    | { type: 'slot'; name: string };

/**
 * Cuts a translated sentence at its `{marker}`s so a template can put a
 * link, a badge or bold text in the middle of ONE translatable string:
 *
 *     splitMarkers('¿Ya tienes cuenta? {link}', ['link'])
 *     → [{ type: 'text', value: '¿Ya tienes cuenta? ' }, { type: 'slot', name: 'link' }]
 *
 * Only the listed names are cut out (any `{marker}` when no list is
 * given); the order is whatever the translation says.
 */
export function splitMarkers(text: string, names?: string[]): Segment[] {
    const segments: Segment[] = [];
    const pattern = /\{\s*([A-Za-z_][\w.]*)\s*\}/g;
    let last = 0;

    for (const match of text.matchAll(pattern)) {
        const name = match[1];

        if (names && !names.includes(name)) {
            continue;
        }

        if (match.index > last) {
            segments.push({ type: 'text', value: text.slice(last, match.index) });
        }

        segments.push({ type: 'slot', name });
        last = match.index + match[0].length;
    }

    if (last < text.length) {
        segments.push({ type: 'text', value: text.slice(last) });
    }

    return segments;
}

/**
 * A place as people write it: Spanish "1.º", English "1st". Other
 * languages get a plain "1." until someone adds their rule here.
 */
export function ordinal(value: number, locale: string): string {
    const language = locale.slice(0, 2).toLowerCase();

    if (language === 'es') {
        return `${value}.º`;
    }

    if (language === 'en') {
        const tens = Math.abs(value) % 100;
        const suffix =
            tens >= 11 && tens <= 13
                ? 'th'
                : (['th', 'st', 'nd', 'rd'][Math.abs(value) % 10] ?? 'th');

        return `${value}${suffix}`;
    }

    return `${value}.`;
}

/**
 * Money from integer minor units (cents): "$1,200.00 MXN" in every
 * language. The digits follow the language's separators; the symbol is the
 * currency's own short one and the ISO code always follows, so "$" is
 * never mistaken for US dollars (and English never shows "MX$").
 */
export function formatMoney(
    minor: number,
    currency: string,
    regional: string,
): string {
    const code = currency.toUpperCase();
    const amount = minor / 100;

    try {
        const parts = new Intl.NumberFormat(regional, {
            style: 'currency',
            currency: code,
            currencyDisplay: 'narrowSymbol',
        }).formatToParts(amount);

        const symbol =
            parts.find((part) => part.type === 'currency')?.value ?? '';
        const digits = parts
            .filter((part) =>
                ['integer', 'group', 'decimal', 'fraction'].includes(part.type),
            )
            .map((part) => part.value)
            .join('');
        const sign = parts.some((part) => part.type === 'minusSign') ? '-' : '';

        // A currency without a symbol of its own comes back as its code.
        return `${sign}${symbol === code ? '' : symbol}${digits} ${code}`;
    } catch {
        return `${amount.toFixed(2)} ${code}`;
    }
}
