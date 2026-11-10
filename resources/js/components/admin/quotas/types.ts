/**
 * Shared types and small helpers of Administración → Límites y tarifas
 * (props of App\Http\Controllers\Admin\Quotas\QuotaController::index).
 */

export type LimitColumn =
    | 'included_photos'
    | 'included_videos'
    | 'included_audio'
    | 'max_video_seconds'
    | 'max_audio_seconds'
    | 'cap_photos'
    | 'cap_videos'
    | 'cap_audio';

export const LIMIT_COLUMNS: LimitColumn[] = [
    'included_photos',
    'included_videos',
    'included_audio',
    'max_video_seconds',
    'max_audio_seconds',
    'cap_photos',
    'cap_videos',
    'cap_audio',
];

export type Rule = { id: number; notes: string | null } & Record<
    LimitColumn,
    number | null
>;

export type LimitSource = 'product' | 'type' | 'global' | 'default' | 'none';
export type EffectiveLimits = Record<
    LimitColumn,
    { value: number | null; source: LimitSource }
>;

export type Kind = {
    value: string;
    label: string;
    noun: string;
    included_column: LimitColumn;
    cap_column: LimitColumn;
    max_seconds_column: LimitColumn | null;
};

export type BillingPeriod = {
    value: string;
    label: string;
    months: number | null;
};

export type TypeRow = {
    value: string;
    label: string;
    rule: Rule | null;
    effective_limits: EffectiveLimits;
};

export type ProductRow = {
    id: number;
    name: string;
    type: string | null;
    type_label: string | null;
    active: boolean;
    rule: Rule | null;
    effective_limits: EffectiveLimits;
};

export type PriceStep = {
    id: number;
    from_units: number;
    amount: string;
    apple_product_id: string | null;
    google_product_id: string | null;
};

export type Price = {
    id: number;
    amount: string;
    amount_minor: number;
    currency: string;
    tax_rate: number | null;
    tax_included: boolean;
    starts_at: string | null;
    ends_at: string | null;
    active: boolean;
    state: 'current' | 'scheduled' | 'ended' | 'cancelled';
    used: boolean;
    steps: PriceStep[];
};

export type Pack = {
    id: number;
    kind: string;
    kind_label: string;
    billing_period: string;
    billing_label: string;
    name: string;
    description: string | null;
    photos: number;
    units: number;
    unit_noun: string;
    product_id: number | null;
    product_name: string | null;
    product_type: string | null;
    product_type_label: string | null;
    active: boolean;
    archived: boolean;
    sort_order: number;
    apple_product_id: string | null;
    google_product_id: string | null;
    sold_count: number;
    current_price: Price | null;
    prices: Price[];
    deletable: boolean;
};

export type Entitlement = {
    id: number;
    source: 'purchase' | 'manual';
    story: string | null;
    story_title: string | null;
    account_wide: boolean;
    user: string | null;
    email: string | null;
    pack: string | null;
    kind: string;
    kind_label: string;
    units: number;
    unit_noun: string;
    billing_period: string;
    billing_label: string;
    starts_at: string | null;
    expires_at: string | null;
    expired: boolean;
    price: string | null;
    currency: string | null;
    step_from: number | null;
    held_units: number | null;
    order_number: string | null;
    granted_at: string | null;
    granted_by: string | null;
    grant_reason: string | null;
    revoked: boolean;
    revoked_at: string | null;
    revocation_reason: string | null;
    revocable: boolean;
};

export type Stats = {
    sellable_packs: number;
    rights_sold: number;
    photos_sold: number;
    videos_sold: number;
    audio_sold: number;
    manual_grants: number;
    expiring_soon: number;
};

/** A step row while it is being typed in a price form. */
export type StepDraft = {
    from_units: number | null;
    amount: string;
    apple_product_id: string;
    google_product_id: string;
};

export type FormValue = string | number | null;

export const money = (amount: string | number, currency: string): string =>
    new Intl.NumberFormat('es-MX', { style: 'currency', currency }).format(
        Number(amount),
    );

/** "por 12 meses" / "pago único" — what goes next to a price. */
export const periodSuffix = (months: number | null | undefined): string =>
    months ? `por ${months} meses` : 'pago único';

export const seconds = (value: number): string => {
    if (value < 60) {
        return `${value} s`;
    }

    const rest = value % 60;

    return rest === 0
        ? `${value / 60} min`
        : `${Math.floor(value / 60)}:${String(rest).padStart(2, '0')} min`;
};

export const sourceLabel: Record<LimitSource, string> = {
    product: 'Propio del producto',
    type: 'Heredado del tipo de producto',
    global: 'Heredado de General',
    default: 'Valor de fábrica',
    none: 'Sin tope',
};

/**
 * "Hasta 20 fotos: $49 · de 21 a 50: $79 · más de 50: $99" — the bands a
 * base amount plus its steps describe. Incomplete steps are skipped.
 */
export function stepBands(
    baseAmount: string | number | null,
    steps: { from_units: number | null; amount: string | number }[],
    currency: string,
    noun: string,
): string[] {
    const valid = steps
        .filter(
            (s) =>
                s.from_units !== null &&
                Number(s.from_units) >= 1 &&
                s.amount !== '' &&
                Number(s.amount) > 0,
        )
        .map((s) => ({ from: Number(s.from_units), amount: Number(s.amount) }))
        .sort((a, b) => a.from - b.from);

    if (!valid.length || baseAmount === null || baseAmount === '') {
        return [];
    }

    const bands = [
        `Hasta ${valid[0].from - 1} ${noun}: ${money(baseAmount, currency)}`,
    ];

    valid.forEach((step, i) => {
        const next = valid[i + 1];
        bands.push(
            next
                ? `de ${step.from} a ${next.from - 1}: ${money(step.amount, currency)}`
                : `${step.from} o más: ${money(step.amount, currency)}`,
        );
    });

    return bands;
}
