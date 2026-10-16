export type MomentVisibility = 'public' | 'followers' | 'private';

/** The six card families of Comunidad (spec §54). */
export type PostKind =
    'moment' | 'race_result' | 'photo' | 'gear' | 'legacy_plate' | 'text';

export type CommunityAthlete = {
    username: string | null;
    name: string;
    photo_url: string | null;
    city: string | null;
    sport?: string | null;
    is_following?: boolean;
};

export type CommunityPost = {
    uuid: string;
    type: string;
    kind: PostKind;
    kind_label: string;
    caption: string | null;
    visibility: MomentVisibility;
    created_at: string;
    is_owner: boolean;
    is_hidden: boolean;
    share_url: string;
    event: {
        name: string | null;
        slug: string | null;
        edition: string | null;
        date: string | null;
        city: string | null;
    } | null;
    author: CommunityAthlete;
    activity: {
        participant_id: number | null;
        event: string | null;
        event_slug: string | null;
        edition: string | null;
        event_date: string | null;
        race: string | null;
        distance: string | null;
        official_time: string | null;
        pace: string | null;
        overall_position: number | null;
    } | null;
    medal: { uuid: string; title: string; image_url: string | null } | null;
    gear: { uuid: string; product_name: string | null } | null;
    metrics: {
        title: string | null;
        distance_km: number | null;
        duration_seconds: number | null;
        pace: string | null;
        is_personal_record: boolean;
    } | null;
    media: Array<{
        type: string;
        url: string;
        width: number | null;
        height: number | null;
    }>;
    reactions: { like: number; cheer: number; total: number };
    my_reactions: string[];
    comments_count: number;
};

/** An Inertia::scroll() prop (cursor-paginated resource collection). */
export type CommunityPostPage = {
    data: CommunityPost[];
    meta?: { next_cursor: string | null };
};

export type CommunityComment = {
    uuid: string;
    body: string;
    created_at: string;
    author: CommunityAthlete;
    can_delete: boolean;
    is_hidden: boolean;
};

export type VisibilityOption = {
    value: MomentVisibility;
    label: string;
    description: string;
};

export type ReportReason = { value: string; label: string };

export type ReportTarget = {
    type: 'moment' | 'comment' | 'profile';
    /** uuid for posts/comments, username for profiles. */
    id: string;
};
