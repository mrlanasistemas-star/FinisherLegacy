/** Payload of App\Queries\Admin\GetEventWorkspaceHeader. */
export type EventWorkspaceData = {
    id: number;
    base_url: string;
    name: string;
    edition: string;
    event_date: string;
    city: string;
    state: string | null;
    country: string;
    status: string;
    phase: string;
    slug: string;
    organizer: string | null;
    counts: {
        participants: number;
        results: number;
        preregistrations: number;
        media: number;
    };
};

export type EventWorkspaceTabKey =
    | 'summary'
    | 'participants'
    | 'results'
    | 'preregistrations'
    | 'data'
    | 'tracking'
    | 'media';
