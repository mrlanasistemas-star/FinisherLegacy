export type DashboardStats = {
    medals: number;
    events: number;
    plates: number;
    legacyCodes: number;
    ownedProducts: number;
    media: number;
};

export type DashboardProfileSummary = {
    username: string;
    profile_visibility: 'public' | 'private';
    completion: number;
};

export type SportOption = {
    id: number;
    name: string;
};

export type AthleteProfileFormData = {
    username: string;
    bio: string | null;
    city: string | null;
    state: string | null;
    country: string | null;
    main_sport_id: number | null;
    profile_visibility: 'public' | 'private';
    profile_photo_url: string | null;
    cover_photo_url: string | null;
};

export type MedalCard = {
    id: number;
    title: string;
    distance_label: string | null;
    event_date: string | null;
    visibility: 'public' | 'private';
    thumbnail_url: string | null;
};

export type MedalGalleryImage = {
    id: number;
    url: string | null;
};

export type MedalDetail = {
    id: number;
    title: string;
    event_name_manual: string | null;
    event_date: string | null;
    distance_label: string | null;
    official_time: string | null;
    pace: string | null;
    city: string | null;
    country: string | null;
    story: string | null;
    visibility: 'public' | 'private';
    is_official: boolean;
    event_name: string | null;
    race_name: string | null;
    front_image_url: string | null;
    back_image_url: string | null;
    gallery_images: MedalGalleryImage[];
    gallery_slots_remaining: number;
    plate: { serial_number: string; status: string } | null;
    legacy_code: { code: string; status: string } | null;
};

export type EventSearchRace = {
    id: number;
    name: string;
};

export type EventSearchResult = {
    event_id: number;
    event_edition_id: number;
    name: string;
    year: number;
    event_date: string;
    city: string;
    country: string;
    races: EventSearchRace[];
};

export type ParticipantMatch =
    | { matched: true; official_time: string | null; pace: string | null }
    | { matched: false };

export type PublicAthleteProfile = {
    name: string;
    username: string;
    bio: string | null;
    city: string | null;
    country: string | null;
    sport: string | null;
    photo_url: string | null;
    cover_url: string | null;
};

export type PublicAthleteMedal = {
    id: number;
    title: string;
    distance_label: string | null;
    event_date: string | null;
    thumbnail_url: string | null;
};
