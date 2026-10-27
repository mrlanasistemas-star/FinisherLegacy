/**
 * Storefront helpers shared by the store pages, the Home store band and
 * search: card props mapping, color swatches and product type labels.
 */
import type { ProductColor, PublicProductCard } from '@/types';

export const PRODUCT_TYPE_LABELS: Record<string, string> = {
    legacy_tag: 'Finisher Legacy Tag',
    legacy_plate: 'Legacy Plate',
    apparel: 'Ropa',
    accessory: 'Accesorio',
    equipment: 'Equipo',
};

/** Maps a summarize() row to <ProductCard> props. */
export function productCardProps(p: PublicProductCard) {
    return {
        name: p.name,
        slug: p.slug,
        type: p.type,
        category: p.category,
        fromPriceMinor: p.from_price_minor,
        currency: p.currency,
        inStock: p.in_stock,
        imageUrl: p.image_url,
        imageAlt: p.image_alt ?? null,
        hoverImageUrl: p.hover_image_url,
        variantCount: p.variant_count,
        tagline: p.tagline ?? null,
        availability: p.availability ?? 'available',
        availabilityLabel: p.availability_label,
        compareAtMinor: p.compare_at_minor ?? null,
        promotionLabel: p.promotion_label ?? null,
        colors: p.colors ?? [],
        sizes: p.sizes ?? [],
        stockLevel: p.stock_level ?? null,
        isNfc: p.is_nfc ?? false,
        isNew: p.is_new ?? false,
        isPresale: p.is_presale ?? false,
    };
}

/*
 * Variant colors are free text in admin ("Ice Stealth", "Negro mate"…).
 * An explicit `color_hex` attribute always wins; otherwise the first
 * recognizable word picks a tone, and anything unknown gets a neutral
 * steel swatch (the name is always in the tooltip / sr text).
 */
const COLOR_WORDS: [RegExp, string][] = [
    [/\bice\b|hielo/i, '#d9e3e8'],
    [/negro|black|stealth|onyx|carb[oó]n|carbon/i, '#1d1d1b'],
    [/blanc|white|ivory|marfil/i, '#f7f6f2'],
    [/crimson|rojo|red|carmes[ií]|cherry/i, '#a3242e'],
    [/rosa|pink|frost pink|blush/i, '#e8b4bf'],
    [/azul|blue|navy|marino|glacier/i, '#5d8fb8'],
    [/verde|green|olive|olivo|forest/i, '#4e6b4a'],
    [/gris|gray|grey|graphite|grafito|steel|acero/i, '#8a8a86'],
    [/oro|gold|dorado/i, '#c9a45c'],
    [/plata|silver|n[ií]quel|nickel/i, '#c9cbcc'],
    [/naranja|orange/i, '#d9772b'],
    [/amarill|yellow/i, '#e2c044'],
    [/morado|purple|violet|lila/i, '#6f5a9e'],
    [/caf[eé]|brown|marr[oó]n|\btan\b|arena|sand/i, '#9a7b5b'],
];

export function swatchColor(color: ProductColor): string {
    if (color.hex && /^#[0-9a-f]{3,8}$/i.test(color.hex)) {
        return color.hex;
    }

    for (const [pattern, hex] of COLOR_WORDS) {
        if (pattern.test(color.name)) {
            return hex;
        }
    }

    return '#b9b5ab';
}

export const STOCK_LABELS: Record<'ok' | 'low' | 'out', string> = {
    ok: 'Disponible',
    low: 'Últimas piezas',
    out: 'Agotado',
};
