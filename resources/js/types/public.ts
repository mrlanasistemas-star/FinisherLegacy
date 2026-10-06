export type EventPhase = 'upcoming' | 'ongoing' | 'finished';

export type EventEditionCard = {
    id: number;
    name: string;
    year: number;
    event_date: string;
    city: string;
    state: string | null;
    country: string;
    phase: EventPhase;
    event: {
        name: string;
        slug: string;
        cover_url: string | null;
        sport: {
            name: string;
            slug: string;
        };
    };
    distances: string[];
};

export type LegacyProfilePreview = {
    username: string;
    name: string;
    bio?: string | null;
    city: string | null;
    country: string | null;
    sport: string | null;
    photo_url: string | null;
    cover_url?: string | null;
    medals_count: number;
    medals: Array<{
        title: string;
        distance_label: string | null;
    }>;
};

export type EventRacePreview = {
    id: number;
    name: string;
    distance_value: string | number | null;
    distance_unit: string | null;
    start_time: string | null;
};

export type LegacyPlateModelOption = {
    id: number;
    name: string;
    description: string | null;
};

export type EventLegacyPlatePresale = {
    product_variant_id: number;
    price_minor: number;
    currency: string;
    price_type: string;
    presale_ends_at: string | null;
    models: LegacyPlateModelOption[];
    already_purchased: boolean;
};

export type EventEditionDetail = {
    id: number;
    name: string;
    year: number;
    event_date: string;
    city: string;
    state: string | null;
    country: string;
    phase: EventPhase;
    registration_open_at: string | null;
    registration_close_at: string | null;
    races: EventRacePreview[];
    legacy_plate: EventLegacyPlatePresale | null;
};

export type EventDetail = {
    name: string;
    slug: string;
    description: string | null;
    cover_url: string | null;
    sport: string;
    organizer: string | null;
};

export type LegacyCodePlate = {
    athlete_name: string | null;
    event_name: string | null;
    race_name: string | null;
    official_time: string | null;
    pace: string | null;
    event_date: string | null;
    status: string;
};

export type LegacyCodeAthlete = {
    username: string;
    city: string | null;
    sport: string | null;
};

export type PublicProductCard = {
    uuid: string;
    name: string;
    slug: string;
    type: string;
    category: string | null;
    from_price_minor: number | null;
    currency: string;
    in_stock: boolean;
    image_url: string | null;
    image_alt?: string | null;
    hover_image_url: string | null;
    variant_count: number;
    category_slug?: string | null;
    tagline?: string | null;
    availability?: ProductAvailability;
    availability_label?: string;
    compare_at_minor?: number | null;
    promotion_label?: string | null;
};

export type ProductAvailability = 'available' | 'coming_soon' | 'concept';

export type PublicSport = {
    name: string;
    slug: string;
};

export type HeroAthlete = {
    username: string;
    name: string;
    sport: string | null;
    city: string | null;
    bio: string | null;
    photo_url: string;
};

export type CompanyMilestone = {
    id: number;
    period: string;
    title: string;
    description: string | null;
    location: string | null;
    image_url: string | null;
};

export type CompanyGalleryItem = {
    id: number;
    image_url: string;
    width: number | null;
    height: number | null;
    title: string | null;
    description: string | null;
};

/** Only channels an admin configured (CompanySetting::contactChannels()). */
export type CompanyChannels = Partial<
    Record<
        | 'email'
        | 'phone'
        | 'whatsapp'
        | 'instagram_url'
        | 'facebook_url'
        | 'tiktok_url'
        | 'youtube_url'
        | 'linkedin_url'
        | 'strava_url',
        string
    >
>;
