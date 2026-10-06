export type MomentVisibility = 'public' | 'followers' | 'private';

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
    caption: string | null;
    visibility: MomentVisibility;
    created_at: string;
    is_owner: boolean;
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
    reactions: { like: number; cheer: number };
    my_reactions: string[];
    comments_count: number;
};

export type CommunityComment = {
    uuid: string;
    body: string;
    created_at: string;
    author: CommunityAthlete;
    can_delete: boolean;
};

export type VisibilityOption = {
    value: MomentVisibility;
    label: string;
    description: string;
};
