/**
 * Shapes produced by App\Support\LegacyTags\StoryPresenter — shared by
 * /t, /activar, /legacy, /mi-legacy and GET /api/v1/stories/{publicId}.
 */

export type TagCollectionSummary = {
    name: string;
    slug: string;
    tagline: string | null;
    year: number | null;
    destination: string | null;
    discipline: string | null;
    accent_color: string | null;
    cover_url: string | null;
};

export type TagSummary = {
    product_name: string | null;
    variant_name: string | null;
    image_url: string | null;
    collection: TagCollectionSummary | null;
};

export type StoryRace = {
    source: 'participation' | 'manual' | 'none';
    event_name: string | null;
    edition: string | null;
    race: string | null;
    distance: string | null;
    time: string | null;
    date: string | null;
    location: string | null;
};

export type StoryMediaItem = {
    id: number;
    uuid: string;
    kind: 'image' | 'audio' | 'file' | 'video';
    mime: string;
    status: string;
    caption: string | null;
    width: number | null;
    height: number | null;
    url: string | null;
    thumb_url: string | null;
    download_url: string | null;
    size_bytes: number;
};

export type StoryVisibilityValue = 'public' | 'unlisted' | 'private';

export type StoryView = {
    public_id: string;
    title: string;
    athlete_first_name: string | null;
    race: StoryRace;
    recipient_label: string | null;
    dedication: string | null;
    body: string | null;
    external_url: string | null;
    visibility: StoryVisibilityValue;
    visibility_label: string;
    is_indexable: boolean;
    cover: StoryMediaItem | null;
    photos: StoryMediaItem[];
    audio: StoryMediaItem | null;
    certificate: StoryMediaItem | null;
    tag: TagSummary | null;
    display_code: string | null;
    year: number;
    url: string;
    created_at: string | null;
};

export type StoryShare = {
    id: number;
    label: string;
    url: string;
    open_count: number;
    last_opened_at: string | null;
    active: boolean;
};

export type ParticipationOption = {
    id: number;
    event_name: string | null;
    edition: string | null;
    race: string | null;
    distance: string | null;
    time: string | null;
    date: string | null;
    location: string | null;
};

/** "14 de septiembre de 2026" — dates come as YYYY-MM-DD (no timezone shift). */
export function formatStoryDate(date: string | null): string | null {
    if (!date) {
        return null;
    }

    const [y, m, d] = date.split('-').map(Number);

    return new Date(y, (m ?? 1) - 1, d ?? 1).toLocaleDateString('es-MX', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}
