/** Shapes sent by App\Queries\Store\PublicCollections. */
import type { TagArt } from '@/components/three/tagStageContext';

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
    /** Primary tag design + every design of the collection (primary first). */
    tag_design?: TagDesign | null;
    tag_designs?: TagDesign[];
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
    /** Tag products only (null otherwise). */
    tag_design?: TagDesign | null;
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

/**
 * `tag_design` (App\Support\Collections\TagDesign): what a Finisher Legacy
 * Tag prints — deck pp. 7–8. Same shape on the web and in /api/v1.
 */
export type TagDesign = {
    front_art_url: string | null;
    accent: string | null;
    name_line: string;
    discipline_line: string | null;
    back_line: string;
    monogram_url: string;
    /** Only in `tag_designs` lists. */
    slug?: string;
    name?: string;
};

/** A collection (or tag product) + its design → the tag renderers' props. */
export function tagArt(
    c: {
        name: string;
        year?: number | string | null;
        discipline?: string | null;
        destination?: string | null;
        accent_color?: string | null;
    },
    design?: TagDesign | null,
): TagArt {
    return {
        name: c.name,
        subtitle: collectionSubtitle(c),
        year: c.year ?? null,
        accent: design?.accent ?? c.accent_color ?? null,
        frontArt: design?.front_art_url ?? null,
        nameLine: design?.name_line ?? null,
        disciplineLine: design?.discipline_line ?? null,
        backLine: design?.back_line ?? null,
    };
}

/** Every design of a collection (primary first) as tag artwork. */
export function collectionTagArts(c: CollectionCardData): TagArt[] {
    const designs = c.tag_designs?.length
        ? c.tag_designs
        : c.tag_design
          ? [c.tag_design]
          : [];

    return designs.length
        ? designs.map((design) => tagArt(c, design))
        : [tagArt(c, null)];
}
