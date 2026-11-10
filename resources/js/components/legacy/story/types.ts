/**
 * Shapes the public story page reads beyond the base `StoryView`
 * (App\Support\LegacyTags\StoryPresenter): the extended race data with its
 * `official` map, videos and the list of voice messages. Kept optional so
 * the page still renders a payload that predates them.
 */
import type { StoryMediaItem, StoryRace, StoryView } from '@/types/legacy-tags';

export type StoryRaceExtended = StoryRace & {
    bib_number?: string | null;
    athlete_name?: string | null;
    pace?: string | null;
    suggested_pace?: string | null;
    category?: string | null;
    category_position?: string | null;
    gender_position?: string | null;
    finish_status?: 'finished' | 'dnf' | 'dns' | 'dq' | null;
    finish_status_label?: string | null;
    elevation_gain_m?: number | null;
    sport?: string | null;
    official?: Record<string, boolean>;
};

export type StoryMediaExtended = StoryMediaItem & {
    poster_url?: string | null;
    duration_seconds?: number | null;
    role?: string;
    source?: 'upload' | 'purchased';
    sort_order?: number;
};

export type StoryViewExtended = Omit<StoryView, 'race' | 'audio'> & {
    race: StoryRaceExtended;
    audio: StoryMediaExtended | null;
    videos?: StoryMediaExtended[];
    audios?: StoryMediaExtended[];
};

/** "1:05" for 65 seconds; "0:00" when unknown. */
export function clock(seconds: number | null | undefined): string {
    if (
        seconds === null ||
        seconds === undefined ||
        !Number.isFinite(seconds) ||
        seconds <= 0
    ) {
        return '0:00';
    }

    const s = Math.round(seconds);

    return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
}
