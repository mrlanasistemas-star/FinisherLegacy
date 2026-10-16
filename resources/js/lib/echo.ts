/**
 * Realtime client (Laravel Reverb via laravel-echo + pusher-js).
 *
 * Realtime is an enhancement: REST/Inertia props stay the source of truth.
 * `getEcho()` returns null when broadcasting is not configured
 * (no VITE_REVERB_APP_KEY) or during SSR, so every caller must keep working
 * (fallback polling or plain page data).
 *
 * The heavy libraries (laravel-echo + pusher-js) are loaded lazily on the
 * first `getEcho()` call — pages that never subscribe never download them.
 * Subscriptions made before the libraries arrive are buffered and replayed,
 * so callers can use the client synchronously.
 *
 * Conventions (docs/ARCHITECTURE.md §Realtime): server events use dotted
 * `broadcastAs()` names, so listen with a leading dot:
 *   getEcho()?.private(`App.Models.User.${id}`).listen('.notification.created', cb)
 *   getEcho()?.channel(`live.${token}`).listen('.tracking.point', cb)
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

export type RealtimeState =
    | 'initialized'
    | 'connecting'
    | 'connected'
    | 'unavailable'
    | 'failed'
    | 'disconnected';

type PendingOp =
    | { op: 'listen'; event: string; callback: (payload: any) => void }
    | { op: 'stopListening'; event: string };

type EchoChannelLike = {
    listen(event: string, callback: (payload: any) => void): unknown;
    stopListening(event: string): unknown;
};

type EchoLike = {
    channel(name: string): EchoChannelLike;
    private(name: string): EchoChannelLike;
    leave(name: string): void;
    connector: {
        pusher?: {
            connection?: {
                state?: string;
                bind(event: string, cb: (s: any) => void): void;
            };
        };
    };
};

class LazyChannel implements RealtimeChannel {
    private real: EchoChannelLike | null = null;
    private pending: PendingOp[] = [];

    constructor(
        readonly name: string,
        readonly kind: 'public' | 'private',
    ) {}

    listen(event: string, callback: (payload: any) => void): RealtimeChannel {
        if (this.real) {
            this.real.listen(event, callback);
        } else {
            this.pending.push({ op: 'listen', event, callback });
        }

        return this;
    }

    stopListening(event: string): RealtimeChannel {
        if (this.real) {
            this.real.stopListening(event);
        } else {
            this.pending = this.pending.filter((p) => p.event !== event);
        }

        return this;
    }

    attach(echo: EchoLike): void {
        this.real =
            this.kind === 'private'
                ? echo.private(this.name)
                : echo.channel(this.name);

        for (const p of this.pending) {
            if (p.op === 'listen') {
                this.real.listen(p.event, p.callback);
            } else {
                this.real.stopListening(p.event);
            }
        }

        this.pending = [];
    }
}

class LazyRealtimeClient implements RealtimeClient {
    private echo: EchoLike | null = null;
    private channels = new Map<string, LazyChannel>();
    private stateListeners = new Set<(state: RealtimeState) => void>();
    state: RealtimeState = 'initialized';

    constructor(private readonly config: Record<string, unknown>) {
        void this.load();
    }

    channel(name: string): RealtimeChannel {
        return this.get(name, 'public');
    }

    private(name: string): RealtimeChannel {
        return this.get(name, 'private');
    }

    leave(name: string): void {
        this.channels.delete(`public:${name}`);
        this.channels.delete(`private:${name}`);
        this.echo?.leave(name);
    }

    onStateChange(listener: (state: RealtimeState) => void): () => void {
        this.stateListeners.add(listener);
        listener(this.state);

        return () => this.stateListeners.delete(listener);
    }

    private get(name: string, kind: 'public' | 'private'): LazyChannel {
        const key = `${kind}:${name}`;
        let channel = this.channels.get(key);

        if (!channel) {
            channel = new LazyChannel(name, kind);
            this.channels.set(key, channel);

            if (this.echo) {
                channel.attach(this.echo);
            }
        }

        return channel;
    }

    private setState(state: RealtimeState): void {
        this.state = state;
        this.stateListeners.forEach((l) => l(state));
    }

    private async load(): Promise<void> {
        try {
            const [{ default: Echo }, { default: Pusher }] = await Promise.all([
                import('laravel-echo'),
                import('pusher-js'),
            ]);

            const echo = new Echo({
                broadcaster: 'reverb',
                Pusher,
                ...this.config,
            } as any) as unknown as EchoLike;

            this.echo = echo;

            const connection = echo.connector.pusher?.connection;
            connection?.bind('state_change', (s: { current: RealtimeState }) =>
                this.setState(s.current),
            );

            if (connection?.state) {
                this.setState(connection.state as RealtimeState);
            }

            this.channels.forEach((channel) => channel.attach(echo));
        } catch {
            // Realtime is optional — callers fall back to polling.
            this.setState('failed');
        }
    }
}

let client: LazyRealtimeClient | null | undefined;

function readConfig(): Record<string, unknown> | null {
    const env = import.meta.env;
    const key = env.VITE_REVERB_APP_KEY;

    if (typeof key !== 'string' || key === '') {
        return null;
    }

    const scheme = String(env.VITE_REVERB_SCHEME || 'https');
    const host = String(env.VITE_REVERB_HOST || window.location.hostname);
    const port = Number(
        env.VITE_REVERB_PORT || (scheme === 'https' ? 443 : 80),
    );

    return {
        key,
        wsHost: host,
        wsPort: port,
        wssPort: port,
        forceTLS: scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        // Session-authenticated private channels (routes/channels.php).
        authEndpoint: '/broadcasting/auth',
    };
}

export function getEcho(): RealtimeClient | null {
    if (typeof window === 'undefined') {
        return null;
    }

    if (client === undefined) {
        const config = readConfig();
        client = config ? new LazyRealtimeClient(config) : null;
    }

    return client;
}

/**
 * Subscribe to connection state (connected / unavailable / …) so callers
 * can turn their polling fallback on while the socket is down. Calls the
 * listener immediately with the current state; with no realtime configured
 * it reports 'unavailable' once. Returns an unsubscribe function.
 */
export function onRealtimeStateChange(
    listener: (state: RealtimeState) => void,
): () => void {
    const echo = getEcho();

    if (!(echo instanceof LazyRealtimeClient)) {
        listener('unavailable');

        return () => {};
    }

    return echo.onStateChange(listener);
}
