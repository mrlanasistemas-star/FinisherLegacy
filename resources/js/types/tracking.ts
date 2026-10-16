/** Live tracking shapes (mirror App\Queries\Tracking\*). */

export type TrackingMapConfig = { tile_url: string; attribution: string };

export type LiveSupportMessage = {
    id: string;
    from: string;
    type: 'text' | 'audio';
    text: string | null;
    delivered: boolean;
    created_at: string | null;
};

export type LiveSnapshot = {
    athlete: { display_name: string; avatar_url: string | null };
    event: { name: string | null; race: string | null };
    status: 'waiting' | 'active' | 'stopped';
    last_point: { lat: number; lng: number; recorded_at: string } | null;
    distance_m: number;
    race_distance_m: number | null;
    progress: number | null;
    battery: number | null;
    started_at: string | null;
    stopped_at: string | null;
    path: [number, number][];
    channel: string;
    expires_at: string;
    support: {
        url: string | null;
        accepting: boolean;
        messages: LiveSupportMessage[];
    };
};

/** `.tracking.point` broadcast payload. */
export type TrackingPointPayload = {
    lat: number;
    lng: number;
    recorded_at: string;
    distance_m: number;
    battery: number | null;
};

export type ParticipantTracking = {
    session: {
        uuid: string;
        status: 'active' | 'stopped';
        source: 'mobile_gps' | 'gps_band';
        started_at: string;
        stopped_at: string | null;
        distance_m: number;
        last_point_at: string | null;
        last_battery: number | null;
    } | null;
    race_distance_m: number | null;
    shares: {
        uuid: string;
        created_at: string | null;
        expires_at: string;
        last_viewed_at: string | null;
        pinned_to_session: boolean;
    }[];
    devices: {
        uuid: string;
        name: string;
        status: 'pending' | 'paired';
        battery: number | null;
        last_seen_at: string | null;
        pairing_expires_at: string | null;
    }[];
};

export function formatKm(meters: number): string {
    const km = meters / 1000;

    return `${km.toLocaleString('es-MX', {
        minimumFractionDigits: km < 10 ? 2 : 1,
        maximumFractionDigits: km < 10 ? 2 : 1,
    })} km`;
}
