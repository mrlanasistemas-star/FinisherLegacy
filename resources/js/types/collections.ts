/** Shapes sent by App\Queries\Store\PublicCollections. */

export type CollectionCardData = {
    name: string;
    slug: string;
    tagline: string | null;
    discipline: string | null;
    destination: string | null;
    year: number | null;
    edition: string | null;
    season: string | null;
    /** Validated hex (or null) — used only for artwork accents. */
    accent_color: string | null;
    cover_url: string | null;
    hero_url: string | null;
    products_count: number;
    from_price_minor: number | null;
    currency: string;
    available: boolean;
};

export type CollectionProduct = {
    uuid: string;
    name: string;
    slug: string;
    type: string;
    tagline: string | null;
    description: string | null;
    image_url: string | null;
    image_alt: string | null;
    price_minor: number | null;
    compare_at_minor: number | null;
    promotion_label: string | null;
    currency: string;
    is_purchasable: boolean;
    availability: string;
    availability_label: string;
    available: boolean;
    material: string | null;
    strap_type: string | null;
    dimensions: string | null;
};

export type CollectionDetail = CollectionCardData & {
    description: string | null;
    seo_title: string | null;
    seo_description: string | null;
    products: CollectionProduct[];
};

/** "Triatlón · Cozumel" — the small line under a collection name. */
export function collectionSubtitle(c: {
    discipline?: string | null;
    destination?: string | null;
}): string | null {
    const parts = [c.discipline, c.destination].filter(Boolean);

    return parts.length ? parts.join(' · ') : null;
}

/**
 * The collection name without a trailing year when the year is shown on
 * its own ("Cozumel 2026" + 2026 → "Cozumel"), so it never reads twice.
 */
export function collectionBaseName(c: {
    name: string;
    year?: number | null;
}): string {
    const name = c.name.trim();

    if (!c.year || !name.endsWith(String(c.year))) {
        return name;
    }

    return name.slice(0, -String(c.year).length).trim() || name;
}
