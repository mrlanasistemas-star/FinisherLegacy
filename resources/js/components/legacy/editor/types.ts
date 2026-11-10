/**
 * Shapes the story editor (`/mi-legacy/{story}/editar`) receives beyond
 * the shared StoryView: per-kind quota (App\Services\Media\StoryMediaQuota
 * ::summaryAll), editor media items (StoryPresenter::media) and the race
 * data with its "official" map (App\Support\LegacyTags\StoryRaceData).
 */
import type { StoryMediaItem, StoryView } from '@/types/legacy-tags';

export type QuotaKind = 'photos' | 'videos' | 'audio';

export type ExpansionOffer = {
    id: number;
    kind: QuotaKind;
    name: string;
    description: string | null;
    units: number;
    price: string;
    currency: string;
    billing_period: string;
    billing_label: string;
    months: number | null;
    /** Already says "por 12 meses" / "pago único". */
    price_label: string;
    step: {
        id: number | null;
        from: number;
        to: number | null;
        held: number;
    } | null;
};

export type QuotaPlan = {
    billing_period: string;
    expires_at: string | null;
    expired: boolean;
    renewable: boolean;
    expires_in_days: number | null;
    units: number;
    pack_id: number | null;
    pack_name: string | null;
    renew_price_label: string | null;
};

export type KindQuota = {
    limit: number;
    used: number;
    remaining: number;
    can_expand: boolean;
    max_seconds: number | null;
    cap: number | null;
    expansion: ExpansionOffer | null;
    expansions: ExpansionOffer[];
    plan: QuotaPlan | null;
};

export type StoryQuota = Record<QuotaKind, KindQuota>;

export type EditorMediaItem = StoryMediaItem & {
    role?: string;
    source?: 'upload' | 'purchased';
    poster_url?: string | null;
    duration_seconds?: number | null;
    sort_order?: number;
};

export type EditorStory = Omit<StoryView, 'photos' | 'audio'> & {
    photos: EditorMediaItem[];
    videos?: EditorMediaItem[];
    audio: EditorMediaItem | null;
    audios?: EditorMediaItem[];
};

export type RaceData = {
    official: Record<string, boolean>;
    values: Record<string, string | number | null>;
    suggested_pace: string | null;
    sport: { id: number; name: string; slug: string } | null;
};

export type PurchasedPhoto = {
    sale_id: string;
    thumb_url: string | null;
    preview_url: string | null;
    event_name: string | null;
    purchased_at: string;
    in_story: boolean;
};

/** Spanish nouns per kind: [singular, plural]. */
export const KIND_NOUNS: Record<QuotaKind, [string, string]> = {
    photos: ['foto', 'fotos'],
    videos: ['video', 'videos'],
    audio: ['mensaje de voz', 'mensajes de voz'],
};

export function kindNoun(kind: QuotaKind, count: number): string {
    return KIND_NOUNS[kind][count === 1 ? 0 : 1];
}

/** "1:05" from seconds. */
export function clock(seconds: number | null | undefined): string | null {
    if (seconds === null || seconds === undefined) {
        return null;
    }

    const total = Math.max(0, Math.round(seconds));

    return `${Math.floor(total / 60)}:${String(total % 60).padStart(2, '0')}`;
}

/** "60 segundos" / "3 minutos" / "1:30 min". */
export function humanSeconds(seconds: number): string {
    if (seconds < 60) {
        return `${seconds} segundos`;
    }

    const minutes = Math.floor(seconds / 60);
    const rest = seconds % 60;

    if (rest === 0) {
        return minutes === 1 ? '1 minuto' : `${minutes} minutos`;
    }

    return `${minutes}:${String(rest).padStart(2, '0')} min`;
}
