/**
 * Sales channel labels + badge colours — the ONE place the admin UI reads
 * them from (orders, POS, dashboard, allies, activations). Values mirror
 * App\Enums\SalesChannel (direct / ally / own_event / online / app_store /
 * play_store — the last two are in-app purchases of digital goods).
 */
export type SalesChannelValue =
    'direct' | 'ally' | 'own_event' | 'online' | 'app_store' | 'play_store';

type ChannelMeta = {
    label: string;
    short: string;
    /** Badge classes (light admin: tinted border + ink). */
    badge: string;
    /** Solid swatch for charts/legends (validated, distinguishable). */
    swatch: string;
};

export const SALES_CHANNELS: Record<SalesChannelValue, ChannelMeta> = {
    direct: {
        label: 'Venta directa',
        short: 'Directa',
        badge: 'border-sky-500/30 bg-sky-50 text-sky-800',
        swatch: '#2f6f9f',
    },
    ally: {
        label: 'Aliado',
        short: 'Aliado',
        badge: 'border-violet-500/30 bg-violet-50 text-violet-800',
        swatch: '#7a5aa6',
    },
    own_event: {
        label: 'Presencia propia',
        short: 'Evento',
        badge: 'border-fl-gold/40 bg-fl-cream text-fl-gold-ink',
        swatch: '#b08a3e',
    },
    online: {
        label: 'E-commerce',
        short: 'Online',
        badge: 'border-emerald-500/30 bg-emerald-50 text-emerald-800',
        swatch: '#3f8a63',
    },
    app_store: {
        label: 'App Store',
        short: 'iOS',
        badge: 'border-slate-500/30 bg-slate-50 text-slate-800',
        swatch: '#4b5563',
    },
    play_store: {
        label: 'Google Play',
        short: 'Android',
        badge: 'border-teal-500/30 bg-teal-50 text-teal-800',
        swatch: '#2b7a78',
    },
};

export const SALES_CHANNEL_ORDER: SalesChannelValue[] = [
    'direct',
    'ally',
    'own_event',
    'online',
    'app_store',
    'play_store',
];

function meta(channel: string | null | undefined): ChannelMeta | null {
    return channel && channel in SALES_CHANNELS
        ? SALES_CHANNELS[channel as SalesChannelValue]
        : null;
}

export function channelLabel(channel: string | null | undefined): string {
    return meta(channel)?.label ?? 'Sin canal';
}

export function channelShort(channel: string | null | undefined): string {
    return meta(channel)?.short ?? '—';
}

export function channelBadge(channel: string | null | undefined): string {
    return (
        meta(channel)?.badge ??
        'border-foreground/15 bg-muted text-muted-foreground'
    );
}

export function channelSwatch(channel: string | null | undefined): string {
    return meta(channel)?.swatch ?? '#9a968c';
}
