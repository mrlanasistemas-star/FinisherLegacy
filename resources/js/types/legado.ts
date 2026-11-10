/**
 * Shapes of the athlete's personal area ("Mi espacio"): the Mi legado
 * timeline (App\Queries\Athletes\GetAthleteLegado), upcoming races and the
 * customer's order journey (App\Support\Commerce\OrderProgress).
 */
import { t } from '@/i18n';

export type LegadoEntry = {
    id: number;
    event: string | null;
    event_slug: string | null;
    edition: string | null;
    race: string | null;
    distance: string | null;
    bib_number: string | null;
    event_date: string | null;
    year: number | null;
    city: string | null;
    upcoming: boolean;
    official_time: string | null;
    pace: string | null;
    position: number | null;
    image_url: string | null;
    cover_url: string | null;
    has_medal: boolean;
    has_legacy_plate: boolean;
    legacy_plate_status: string | null;
    photo_count: number;
    video_count: number;
    gear_count: number;
};

export type UpcomingEntry = {
    /** Participation id — null for a pre-registration not yet confirmed. */
    id: number | null;
    kind: 'registered' | 'preregistered';
    event: string | null;
    event_slug: string | null;
    edition: string | null;
    race: string | null;
    distance: string | null;
    bib_number: string | null;
    event_date: string | null;
    city: string | null;
    cover_url: string | null;
};

export type LegadoTotals = {
    races: number;
    km: number;
    years: number;
    first_year: number | null;
};

export type OrderProgressStep = {
    key: string;
    label: string;
    state: 'done' | 'current' | 'upcoming';
    at: string | null;
};

export type OrderProgress = {
    steps: OrderProgressStep[];
    closed: {
        label: string;
        description: string;
        tone: 'muted' | 'warning';
    } | null;
    headline: string;
};

/** Human label for a Legacy Plate's preparation state (never internal codes). */
export function legacyPlateStatusLabel(status: string): string | undefined {
    const labels: Record<string, string> = {
        pending_payment: t('Pendiente de pago'),
        paid: t('Apartada'),
        linked: t('En preparación'),
        draft: t('En preparación'),
        pending_confirmation: t('En preparación'),
        queued: t('En preparación'),
        processing: t('En preparación'),
        reprint: t('En preparación'),
        produced: t('Personalizada'),
        quality_check: t('Personalizada'),
        ready: t('Lista para entrega'),
        delivered: t('Entregada'),
        cancelled: t('Cancelada'),
    };

    return labels[status];
}
