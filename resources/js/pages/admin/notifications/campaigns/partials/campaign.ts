/**
 * Shared types and helpers of Notificaciones → Campañas push
 * (docs/renovacion/campanas-push.md): the campaign row the controller
 * sends, the status chips, the brand accent palette and date formatting
 * in America/Mexico_City — the zone every schedule is entered in.
 */

export type CampaignStatus =
    'draft' | 'scheduled' | 'sending' | 'sent' | 'cancelled' | 'failed';

export type CampaignRow = {
    id: number;
    uuid: string;
    name: string | null;
    title: string;
    status: CampaignStatus;
    status_label: string;
    editable: boolean;
    audience_type: string;
    audience_label: string;
    audience_users_count: number;
    audience_devices_count: number;
    sent_count: number;
    inbox_count: number;
    skipped_count: number;
    failed_count: number;
    /** Confirmed by the push receipts (delivered to the phone). */
    delivered_count: number;
    /** Rejected by the provider or failed on its receipt. */
    delivery_failed_count: number;
    /** Queued and still waiting for its receipt. */
    awaiting_receipt_count: number;
    opened_count: number;
    batches_total: number;
    batches_done: number;
    last_error: string | null;
    scheduled_at: string | null;
    started_at: string | null;
    completed_at: string | null;
    cancelled_at: string | null;
    created_at: string | null;
    creator: string | null;
};

export type CampaignContent = {
    body: string;
    image_url: string | null;
    include_logo: boolean;
    color: string | null;
    channel: string;
    category: string;
    category_label: string;
    save_to_inbox: boolean;
    link_type: string;
    link_value: string | null;
    link_label: string | null;
    audience_params: Record<string, unknown>;
};

export type Estimate = {
    users: number;
    devices: number;
    users_with_push: number;
    inbox: number;
    rate_limited_now: boolean;
};

export const TIMEZONE = 'America/Mexico_City';

export const statusChip: Record<CampaignStatus, string> = {
    draft: 'bg-muted text-muted-foreground',
    scheduled: 'bg-sky-100 text-sky-800',
    sending: 'bg-amber-100 text-amber-800',
    sent: 'bg-emerald-100 text-emerald-800',
    cancelled: 'bg-stone-200 text-stone-700',
    failed: 'bg-rose-100 text-rose-800',
};

/**
 * The notification accent (Android small icon + app name). Fixed in the
 * app build (brand gold) — the push provider cannot change it per message,
 * so campaigns do not offer a colour.
 */
export const DEFAULT_ACCENT = '#C9AE8B';

/** "20 nov 2026, 18:30" in Mexico City time. */
export function mxDateTime(iso: string | null | undefined): string {
    if (!iso) {
        return '—';
    }

    return new Intl.DateTimeFormat('es-MX', {
        timeZone: TIMEZONE,
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(iso));
}

export const number = (value: number) =>
    new Intl.NumberFormat('es-MX').format(value);

export const percent = (part: number, total: number) =>
    total > 0 ? `${Math.round((part / total) * 100)}%` : '—';

/** The moment a row is about: scheduled, started or created. */
export function rowMoment(row: CampaignRow): { label: string; at: string } {
    if (row.status === 'scheduled' && row.scheduled_at) {
        return { label: 'Programada', at: mxDateTime(row.scheduled_at) };
    }

    if (row.started_at) {
        return { label: 'Enviada', at: mxDateTime(row.started_at) };
    }

    if (row.cancelled_at) {
        return { label: 'Cancelada', at: mxDateTime(row.cancelled_at) };
    }

    return { label: 'Creada', at: mxDateTime(row.created_at) };
}

/**
 * Flattens `{audience_params: {user_ids: [1, 2]}}` into URL query pairs
 * the Laravel side reads back as nested arrays.
 */
export function toQuery(
    params: Record<string, unknown>,
    prefix = '',
    out = new URLSearchParams(),
): URLSearchParams {
    for (const [key, value] of Object.entries(params)) {
        const name = prefix ? `${prefix}[${key}]` : key;

        if (value === null || value === undefined || value === '') {
            continue;
        }

        if (Array.isArray(value)) {
            value.forEach((item) => out.append(`${name}[]`, String(item)));
        } else if (typeof value === 'object') {
            toQuery(value as Record<string, unknown>, name, out);
        } else if (typeof value === 'boolean') {
            out.append(name, value ? '1' : '0');
        } else {
            out.append(name, String(value));
        }
    }

    return out;
}

export async function getJson<T>(url: string): Promise<T> {
    const response = await fetch(url, {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }

    return (await response.json()) as T;
}
