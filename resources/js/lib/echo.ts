/**
 * Realtime client (Laravel Reverb via laravel-echo).
 *
 * Realtime is an enhancement: REST/Inertia props stay the source of truth.
 * `getEcho()` returns null when broadcasting is not configured, so every
 * caller must keep working (fallback polling or plain page data).
 */
export interface RealtimeChannel {
    listen(event: string, callback: (payload: any) => void): RealtimeChannel;
    stopListening(event: string): RealtimeChannel;
}

export interface RealtimeClient {
    channel(name: string): RealtimeChannel;
    private(name: string): RealtimeChannel;
    leave(name: string): void;
}

export function getEcho(): RealtimeClient | null {
    return null;
}
