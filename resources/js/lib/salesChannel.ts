/**
 * Sales channel labels + badge colours — the ONE place the admin UI reads
 * them from (orders, POS, dashboard, allies, activations). Values mirror
 * App\Enums\SalesChannel (direct / ally / own_event / online).
 */
export type SalesChannelValue = 'direct' | 'ally' | 'own_event' | 'online';

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
};

export const SALES_CHANNEL_ORDER: SalesChannelValue[] = [
    'direct',
    'ally',
    'own_event',
    'online',
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
