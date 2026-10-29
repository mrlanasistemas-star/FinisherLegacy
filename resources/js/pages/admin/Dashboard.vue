<script setup lang="ts">
/**
 * Administración → Inicio (Commerce + Activation OS). Every figure is a
 * live aggregate from App\Queries\Admin\CommerceDashboardMetrics (via
 * Admin\DashboardController): ventas, canales, inventario, activación,
 * producto, colecciones y el KPI interno de venta rápida. A value the
 * system cannot measure yet arrives as `null` and renders "—" with
 * "Sin información todavía" — never a made-up number. The "Hoy" strip
 * (eventos de la semana, por atender, pedidos recientes) is kept.
 */
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    Activity,
    ArrowRight,
    Boxes,
    CheckCircle2,
    ChevronRight,
    Clock,
    Inbox,
    LifeBuoy,
    Link2,
    Nfc,
    Receipt,
    Sparkles,
    Timer,
    Type,
    Zap,
} from '@lucide/vue';
import { computed } from 'vue';
import SalesChannelBadge from '@/components/admin/SalesChannelBadge.vue';
import Money from '@/components/shared/Money.vue';
import { SALES_CHANNEL_ORDER, channelSwatch } from '@/lib/salesChannel';
import { paymentStatus, statusClass, statusLabel } from '@/lib/statusLabels';

defineOptions({ inheritAttrs: false });

type SkuRow = {
    variant_id: number;
    sku: string;
    name: string;
    available: number;
    minimum: number;
};

type Commerce = {
    range: string;
    currency: string;
    sales: {
        total_minor: number | null;
        orders: number;
        average_ticket_minor: number | null;
        units: number;
        other_currency_orders: number;
    };
    channels: {
        channel: string;
        label: string;
        orders: number;
        total_minor: number;
        units: number;
        share: number | null;
    }[];
    inventory: {
        tracked_skus: number;
        units_available: number;
        units_reserved: number;
        low_stock_count: number;
        sold_out_count: number;
        low_stock: SkuRow[];
        sold_out: SkuRow[];
    };
    activation: {
        sold: number;
        activated: number;
        pending: number;
        rate: number | null;
        window_hours: number;
        mature_sold: number;
        within_window_rate: number | null;
        median_seconds: number | null;
        activations_in_range: number;
    };
    product: {
        opens: { nfc: number; code: number; link: number };
        opens_total: number;
        tags_with_incidents: number;
        support_cases: number;
        defects: number;
        returns: number;
        replacements: number;
    };
    collections: {
        top: CollectionRow | null;
        ranking: CollectionRow[];
    };
    pos: {
        target_seconds: number;
        sales: number;
        median_seconds: number | null;
        within_target_rate: number | null;
    };
};

type CollectionRow = {
    id: number;
    name: string;
    slug: string;
    units: number;
    activated: number;
    activation_rate: number | null;
    available: number;
};

const props = defineProps<{
    range: string;
    ranges: { value: string; label: string }[];
    commerce: Commerce;
    recentOrders: {
        id: number;
        uuid: string;
        number: string;
        customer: string;
        total_minor: number;
        currency: string;
        status: string;
        payment_status: string;
        items: number;
        created_at: string | null;
    }[];
    general: { new_messages: number; open_incidents: number };
    today: {
        date: string;
        events: {
            id: number;
            name: string;
            edition: string;
            event_date: string;
            is_today: boolean;
            city: string;
            participants: number;
        }[];
        preparation_queue: number;
        community_reports: number;
    };
    kpis: { photos_pending: number; pending_orders: number };
    /** null when the viewer cannot open Legacy → Activaciones. */
    recentActivations:
        | {
              uuid: string;
              display_code: string;
              product: string | null;
              owner: string | null;
              activated_at: string | null;
          }[]
        | null;
    /** null when the viewer cannot open Soporte. */
    support: {
        open: number;
        in_progress: number;
        recent: {
            uuid: string;
            number: string;
            subject: string;
            type_label: string;
            status: string;
            status_label: string;
            priority: string;
            created_at: string | null;
        }[];
    } | null;
}>();

const page = usePage();
const canSell = computed(() =>
    (page.props.auth?.permissions ?? []).includes('pos.sell'),
);

const NA = '—';
const nf = new Intl.NumberFormat('es-MX');
const num = (n: number) => nf.format(n);
const pct = (r: number | null) =>
    r === null
        ? NA
        : `${(r * 100).toLocaleString('es-MX', { maximumFractionDigits: 1 })}%`;

const relativeFormatter = new Intl.RelativeTimeFormat('es-MX', {
    numeric: 'auto',
});

/** "hace 3 h" style timestamps for the activity lists. */
function ago(iso: string | null): string {
    if (!iso) {
        return NA;
    }

    const seconds = Math.round((new Date(iso).getTime() - Date.now()) / 1000);
    const abs = Math.abs(seconds);

    if (abs < 3600) {
        return relativeFormatter.format(Math.round(seconds / 60), 'minute');
    }

    if (abs < 86400) {
        return relativeFormatter.format(Math.round(seconds / 3600), 'hour');
    }

    return relativeFormatter.format(Math.round(seconds / 86400), 'day');
}
function duration(seconds: number | null): string {
    if (seconds === null) {
        return NA;
    }

    if (seconds < 90) {
        return `${seconds} s`;
    }

    if (seconds < 3600) {
        return `${Math.round(seconds / 60)} min`;
    }

    if (seconds < 86400 * 2) {
        return `${(seconds / 3600).toLocaleString('es-MX', { maximumFractionDigits: 1 })} h`;
    }

    return `${(seconds / 86400).toLocaleString('es-MX', { maximumFractionDigits: 1 })} días`;
}

const rangeLabel = computed(
    () =>
        props.ranges
            .find((r) => r.value === props.range)
            ?.label.toLowerCase() ?? '',
);

function setRange(value: string) {
    router.get(
        '/admin',
        { rango: value },
        { preserveScroll: true, preserveState: true, replace: true },
    );
}

const c = computed(() => props.commerce);

const channels = computed(() =>
    SALES_CHANNEL_ORDER.map(
        (key) =>
            c.value.channels.find((row) => row.channel === key) ?? {
                channel: key,
                label: key,
                orders: 0,
                total_minor: 0,
                units: 0,
                share: null,
            },
    ),
);
const hasChannelSales = computed(() =>
    channels.value.some((row) => row.orders > 0),
);

const opens = computed(() => [
    { key: 'nfc', label: 'NFC', icon: Nfc, value: c.value.product.opens.nfc },
    {
        key: 'code',
        label: 'Código manual',
        icon: Type,
        value: c.value.product.opens.code,
    },
    {
        key: 'link',
        label: 'Enlace',
        icon: Link2,
        value: c.value.product.opens.link,
    },
]);
const opensMax = computed(() =>
    Math.max(...opens.value.map((o) => o.value), 1),
);

/** Meter for the POS KPI: median vs target (target at 2/3 of the track). */
const posMeter = computed(() => {
    const median = c.value.pos.median_seconds;

    if (median === null) {
        return null;
    }

    const track = c.value.pos.target_seconds * 1.5;

    return {
        width: Math.min(100, (median / track) * 100),
        target: (c.value.pos.target_seconds / track) * 100,
        ok: median <= c.value.pos.target_seconds,
    };
});

const attention = computed(() =>
    [
        {
            label: 'Pedidos por atender',
            value: props.kpis.pending_orders,
            href: '/admin/orders',
        },
        {
            label: 'Incidencias abiertas (eventos)',
            value: props.general.open_incidents,
            href: '/admin/incidents',
        },
        {
            label: 'Fotos por revisar',
            value: props.kpis.photos_pending,
            href: '/admin/photos?status=review',
        },
        {
            label: 'Reportes de comunidad',
            value: props.today.community_reports,
            href: '/admin/community',
        },
    ].filter((row) => row.value > 0),
);

const shortDate = (iso: string) =>
    new Date(`${iso}T12:00:00`).toLocaleDateString('es-MX', {
        day: 'numeric',
        month: 'short',
    });

const todayLabel = computed(() =>
    new Date(`${props.today.date}T12:00:00`).toLocaleDateString('es-MX', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
    }),
);
</script>

<template>
    <Head title="Inicio · Administración" />

    <div class="w-full space-y-8 px-4 py-6 md:px-6 md:py-8 xl:px-8">
        <!-- Header -->
        <div
            class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
        >
            <div>
                <p class="fl-eyebrow first-letter:uppercase">
                    {{ todayLabel }}
                </p>
                <h1 class="fl-display mt-2 text-3xl sm:text-4xl">Inicio</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Ventas, inventario y activación de Finisher Legacy Tags.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <Link
                    v-if="general.new_messages > 0"
                    href="/admin/messages"
                    class="inline-flex items-center gap-2 rounded-full border border-border bg-card px-4 py-2 text-sm hover:border-foreground/20"
                >
                    <Inbox class="size-4 text-fl-gold-ink" />
                    {{ general.new_messages }} mensaje{{
                        general.new_messages === 1 ? '' : 's'
                    }}
                </Link>
                <div
                    class="inline-flex rounded-full border border-border bg-card p-1"
                    role="group"
                    aria-label="Periodo"
                >
                    <button
                        v-for="option in ranges"
                        :key="option.value"
                        type="button"
                        class="min-h-9 rounded-full px-3.5 text-xs font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-fl-gold"
                        :class="
                            range === option.value
                                ? 'bg-foreground text-background'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                        :aria-pressed="range === option.value"
                        @click="setRange(option.value)"
                    >
                        {{ option.label }}
                    </button>
                </div>
                <Link
                    v-if="canSell"
                    href="/admin/venta-rapida"
                    class="inline-flex min-h-10 items-center gap-2 rounded-full bg-foreground px-4 text-sm font-semibold text-background transition-opacity hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-fl-gold"
                >
                    <Zap class="size-4" />
                    Venta rápida
                </Link>
            </div>
        </div>

        <!-- VENTAS -->
        <section aria-labelledby="h-ventas">
            <div class="mb-3 flex items-center justify-between gap-2">
                <h2 id="h-ventas" class="fl-eyebrow">
                    Ventas · {{ rangeLabel }}
                </h2>
                <Link
                    href="/admin/comercio/ventas"
                    class="inline-flex items-center gap-1 text-xs font-medium text-fl-gold-ink hover:underline"
                >
                    Detalle de ventas <ArrowRight class="size-3.5" />
                </Link>
            </div>
            <div class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
                <div class="fl-card col-span-2 p-5 xl:col-span-1">
                    <p class="text-xs font-medium text-muted-foreground">
                        Total vendido
                    </p>
                    <p
                        class="legacy-numeric mt-2 text-3xl font-semibold text-foreground"
                    >
                        <Money
                            v-if="c.sales.total_minor !== null"
                            :minor="c.sales.total_minor"
                            :currency="c.currency"
                        />
                        <template v-else>{{ NA }}</template>
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        <template v-if="c.sales.total_minor === null"
                            >Sin ventas pagadas en el periodo.</template
                        >
                        <template v-else
                            >Pedidos pagados en {{ c.currency }}.</template
                        >
                        <template v-if="c.sales.other_currency_orders > 0">
                            + {{ c.sales.other_currency_orders }} en otra
                            moneda.
                        </template>
                    </p>
                </div>
                <div class="fl-card p-5">
                    <p class="text-xs font-medium text-muted-foreground">
                        Pedidos
                    </p>
                    <p
                        class="legacy-numeric mt-2 text-2xl font-semibold text-foreground"
                    >
                        {{ num(c.sales.orders) }}
                    </p>
                </div>
                <div class="fl-card p-5">
                    <p class="text-xs font-medium text-muted-foreground">
                        Ticket promedio
                    </p>
                    <p
                        class="legacy-numeric mt-2 text-2xl font-semibold text-foreground"
                    >
                        <Money
                            v-if="c.sales.average_ticket_minor !== null"
                            :minor="c.sales.average_ticket_minor"
                            :currency="c.currency"
                        />
                        <template v-else>{{ NA }}</template>
                    </p>
                </div>
                <div class="fl-card col-span-2 p-5 sm:col-span-1">
                    <p class="text-xs font-medium text-muted-foreground">
                        Piezas vendidas
                    </p>
                    <p
                        class="legacy-numeric mt-2 text-2xl font-semibold text-foreground"
                    >
                        {{ num(c.sales.units) }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Productos físicos (sin fotos digitales).
                    </p>
                </div>
            </div>
        </section>

        <!-- HOY: pedidos recientes + por atender + eventos -->
        <section
            class="grid gap-3 sm:gap-4 xl:grid-cols-3"
            aria-label="Operación de hoy"
        >
            <div class="fl-card overflow-hidden xl:col-span-2">
                <div
                    class="flex items-center justify-between gap-2 border-b border-border px-5 py-4"
                >
                    <h2 class="text-sm font-semibold text-foreground">
                        Pedidos recientes
                    </h2>
                    <Link
                        href="/admin/orders"
                        class="inline-flex items-center gap-1 text-xs font-medium text-fl-gold-ink hover:underline"
                    >
                        Ver pedidos <ArrowRight class="size-3.5" />
                    </Link>
                </div>
                <div v-if="recentOrders.length" class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr
                                class="border-b border-border text-left text-xs text-muted-foreground"
                            >
                                <th class="px-5 py-2.5 font-medium">Pedido</th>
                                <th class="px-3 py-2.5 font-medium">Cliente</th>
                                <th
                                    class="hidden px-3 py-2.5 font-medium md:table-cell"
                                >
                                    Fecha
                                </th>
                                <th class="px-3 py-2.5 font-medium">Pago</th>
                                <th class="px-5 py-2.5 text-right font-medium">
                                    Total
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr
                                v-for="order in recentOrders"
                                :key="order.id"
                                class="group transition-colors hover:bg-foreground/[0.02]"
                            >
                                <td class="px-5 py-2.5">
                                    <Link
                                        :href="`/admin/orders/${order.uuid}`"
                                        class="legacy-numeric font-medium text-foreground group-hover:text-fl-gold-ink"
                                    >
                                        {{ order.number }}
                                    </Link>
                                    <span
                                        class="block text-xs text-muted-foreground"
                                        >{{ order.items }} artículo{{
                                            order.items === 1 ? '' : 's'
                                        }}</span
                                    >
                                </td>
                                <td
                                    class="max-w-[14rem] truncate px-3 py-2.5 text-foreground"
                                >
                                    {{ order.customer }}
                                </td>
                                <td
                                    class="hidden px-3 py-2.5 whitespace-nowrap text-muted-foreground md:table-cell"
                                >
                                    {{ ago(order.created_at) }}
                                </td>
                                <td class="px-3 py-2.5">
                                    <span
                                        class="inline-flex rounded-full border px-2 py-0.5 text-[11px] whitespace-nowrap"
                                        :class="
                                            statusClass(
                                                paymentStatus,
                                                order.payment_status,
                                            )
                                        "
                                        >{{
                                            statusLabel(
                                                paymentStatus,
                                                order.payment_status,
                                            )
                                        }}</span
                                    >
                                </td>
                                <td
                                    class="legacy-numeric px-5 py-2.5 text-right whitespace-nowrap"
                                >
                                    <Money
                                        :minor="order.total_minor"
                                        :currency="order.currency"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div
                    v-else
                    class="flex flex-col items-center gap-2 px-6 py-12 text-center"
                >
                    <Receipt class="size-6 text-muted-foreground/70" />
                    <p class="text-sm text-muted-foreground">
                        Aún no hay pedidos.
                    </p>
                </div>
            </div>

            <div class="grid content-start gap-3 sm:gap-4">
                <div class="fl-card p-5">
                    <h2 class="text-sm font-semibold text-foreground">
                        Por atender
                    </h2>
                    <ul v-if="attention.length" class="mt-3 space-y-1">
                        <li v-for="row in attention" :key="row.label">
                            <Link
                                :href="row.href"
                                class="-mx-2 flex min-h-11 items-center gap-3 rounded-lg px-2 text-sm hover:bg-foreground/[0.03]"
                            >
                                <Clock class="size-4 text-fl-gold-ink" />
                                <span class="flex-1 text-foreground">{{
                                    row.label
                                }}</span>
                                <span
                                    class="legacy-numeric rounded-full bg-fl-cream px-2 py-0.5 text-xs font-semibold text-fl-gold-ink"
                                    >{{ num(row.value) }}</span
                                >
                            </Link>
                        </li>
                    </ul>
                    <p
                        v-else
                        class="mt-3 flex items-center gap-2 text-sm text-muted-foreground"
                    >
                        <CheckCircle2 class="size-4 text-emerald-600" />
                        Todo al día.
                    </p>
                </div>

                <div class="fl-card p-5">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="text-sm font-semibold text-foreground">
                            Eventos de esta semana
                        </h2>
                        <Timer class="size-4 text-fl-gold-ink" />
                    </div>
                    <ul
                        v-if="today.events.length"
                        class="mt-3 divide-y divide-border"
                    >
                        <li v-for="event in today.events" :key="event.id">
                            <Link
                                :href="`/admin/events/${event.id}`"
                                class="flex min-h-11 items-center gap-3 py-1.5 text-sm hover:text-fl-gold-ink"
                            >
                                <span
                                    class="w-14 shrink-0 text-xs font-semibold uppercase"
                                    :class="
                                        event.is_today
                                            ? 'text-emerald-700'
                                            : 'text-muted-foreground'
                                    "
                                >
                                    {{
                                        event.is_today
                                            ? 'Hoy'
                                            : shortDate(event.event_date)
                                    }}
                                </span>
                                <span class="min-w-0 flex-1 truncate">{{
                                    event.name
                                }}</span>
                                <ChevronRight
                                    class="size-4 shrink-0 text-muted-foreground"
                                />
                            </Link>
                        </li>
                    </ul>
                    <p v-else class="mt-3 text-sm text-muted-foreground">
                        No hay eventos en los próximos 7 días.
                    </p>
                </div>
            </div>
        </section>

        <!-- POR CANAL + POS -->
        <section class="grid gap-3 sm:gap-4 lg:grid-cols-3">
            <div class="fl-card p-5 lg:col-span-2" aria-labelledby="h-canal">
                <h2 id="h-canal" class="text-sm font-semibold text-foreground">
                    Por canal
                </h2>
                <ul v-if="hasChannelSales" class="mt-4 space-y-4">
                    <li v-for="row in channels" :key="row.channel">
                        <div
                            class="flex flex-wrap items-center justify-between gap-2 text-sm"
                        >
                            <SalesChannelBadge :channel="row.channel" />
                            <span class="legacy-numeric text-foreground">
                                <Money
                                    :minor="row.total_minor"
                                    :currency="c.currency"
                                />
                                <span class="ml-2 text-xs text-muted-foreground"
                                    >{{ num(row.orders) }} pedidos ·
                                    {{ num(row.units) }} piezas ·
                                    {{ pct(row.share) }}</span
                                >
                            </span>
                        </div>
                        <div
                            class="mt-2 h-2 overflow-hidden rounded-full bg-muted"
                            role="presentation"
                        >
                            <div
                                class="h-full rounded-full"
                                :style="{
                                    width: `${(row.share ?? 0) * 100}%`,
                                    background: channelSwatch(row.channel),
                                }"
                            />
                        </div>
                    </li>
                </ul>
                <p v-else class="mt-3 text-sm text-muted-foreground">
                    Sin información todavía: no hay ventas pagadas en este
                    periodo.
                </p>
            </div>

            <div class="fl-card p-5" aria-labelledby="h-pos">
                <div class="flex items-center justify-between gap-2">
                    <h2
                        id="h-pos"
                        class="text-sm font-semibold text-foreground"
                    >
                        Venta rápida
                    </h2>
                    <span
                        class="flex size-8 items-center justify-center rounded-lg bg-foreground text-background"
                    >
                        <Zap class="size-4" />
                    </span>
                </div>
                <p
                    class="legacy-numeric mt-3 text-3xl font-semibold text-foreground"
                >
                    {{ duration(c.pos.median_seconds) }}
                </p>
                <p class="text-xs text-muted-foreground">
                    Mediana por venta en persona · meta interna
                    {{ c.pos.target_seconds }} s
                </p>
                <div v-if="posMeter" class="mt-4">
                    <div class="relative h-2 rounded-full bg-muted">
                        <div
                            class="h-full rounded-full"
                            :class="
                                posMeter.ok ? 'bg-emerald-600' : 'bg-amber-600'
                            "
                            :style="{ width: `${posMeter.width}%` }"
                        />
                        <span
                            class="absolute -top-1 h-4 w-0.5 bg-foreground"
                            :style="{ left: `${posMeter.target}%` }"
                            aria-hidden="true"
                        />
                    </div>
                    <p class="mt-2 text-xs text-muted-foreground">
                        {{ pct(c.pos.within_target_rate) }} dentro de la meta ·
                        {{ num(c.pos.sales) }} ventas medidas
                    </p>
                </div>
                <p v-else class="mt-4 text-xs text-muted-foreground">
                    Sin información todavía: se mide desde que se abre la
                    pantalla de Venta rápida hasta cobrar.
                </p>
            </div>
        </section>

        <!-- ACTIVACIÓN -->
        <section aria-labelledby="h-activacion">
            <div class="mb-3 flex items-center justify-between gap-2">
                <h2 id="h-activacion" class="fl-eyebrow">
                    Activación · piezas vendidas {{ rangeLabel }}
                </h2>
                <Link
                    href="/admin/activaciones"
                    class="inline-flex items-center gap-1 text-xs font-medium text-fl-gold-ink hover:underline"
                >
                    Activaciones <ArrowRight class="size-3.5" />
                </Link>
            </div>
            <div
                class="grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-3 xl:grid-cols-6"
            >
                <div class="fl-card p-4">
                    <p class="text-xs text-muted-foreground">Vendidas</p>
                    <p class="legacy-numeric mt-1 text-2xl font-semibold">
                        {{ num(c.activation.sold) }}
                    </p>
                </div>
                <div class="fl-card p-4">
                    <p class="text-xs text-muted-foreground">Activadas</p>
                    <p class="legacy-numeric mt-1 text-2xl font-semibold">
                        {{ num(c.activation.activated) }}
                    </p>
                </div>
                <div class="fl-card p-4">
                    <p class="text-xs text-muted-foreground">Pendientes</p>
                    <p class="legacy-numeric mt-1 text-2xl font-semibold">
                        {{ num(c.activation.pending) }}
                    </p>
                </div>
                <div class="fl-card p-4">
                    <p class="text-xs text-muted-foreground">
                        Tasa de activación
                    </p>
                    <p class="legacy-numeric mt-1 text-2xl font-semibold">
                        {{ pct(c.activation.rate) }}
                    </p>
                </div>
                <div class="fl-card p-4">
                    <p class="text-xs text-muted-foreground">
                        Activadas en {{ c.activation.window_hours }} h
                    </p>
                    <p class="legacy-numeric mt-1 text-2xl font-semibold">
                        {{ pct(c.activation.within_window_rate) }}
                    </p>
                    <p class="mt-0.5 text-[11px] text-muted-foreground">
                        {{
                            c.activation.mature_sold
                                ? `de ${num(c.activation.mature_sold)} vendidas hace ≥${c.activation.window_hours} h`
                                : 'Sin información todavía'
                        }}
                    </p>
                </div>
                <div class="fl-card p-4">
                    <p class="text-xs text-muted-foreground">
                        Mediana hasta activar
                    </p>
                    <p class="legacy-numeric mt-1 text-2xl font-semibold">
                        {{ duration(c.activation.median_seconds) }}
                    </p>
                </div>
            </div>
            <p
                v-if="c.activation.sold === 0"
                class="mt-2 text-xs text-muted-foreground"
            >
                Sin información todavía: no se vendieron tags en este periodo.
            </p>
        </section>

        <!-- ACTIVACIONES RECIENTES + SOPORTE -->
        <section
            v-if="recentActivations !== null || support !== null"
            class="grid gap-3 sm:gap-4 lg:grid-cols-2"
        >
            <div v-if="recentActivations !== null" class="fl-card p-5">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="text-sm font-semibold text-foreground">
                        Activaciones recientes
                    </h2>
                    <Link
                        href="/admin/activaciones"
                        class="inline-flex items-center gap-1 text-xs font-medium text-fl-gold-ink hover:underline"
                    >
                        Ver todas <ArrowRight class="size-3.5" />
                    </Link>
                </div>
                <ul
                    v-if="recentActivations.length"
                    class="mt-3 divide-y divide-border"
                >
                    <li v-for="row in recentActivations" :key="row.uuid">
                        <Link
                            :href="`/admin/tags/${row.uuid}`"
                            class="flex min-h-12 items-center gap-3 py-2 text-sm hover:text-fl-gold-ink"
                        >
                            <span
                                class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-fl-cream text-fl-gold-ink"
                            >
                                <Nfc class="size-4" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-foreground">{{
                                    row.owner ?? 'Atleta sin nombre'
                                }}</span>
                                <span
                                    class="block truncate text-xs text-muted-foreground"
                                    >{{ row.display_code
                                    }}{{
                                        row.product ? ` · ${row.product}` : ''
                                    }}</span
                                >
                            </span>
                            <span
                                class="shrink-0 text-xs whitespace-nowrap text-muted-foreground"
                                >{{ ago(row.activated_at) }}</span
                            >
                        </Link>
                    </li>
                </ul>
                <p v-else class="mt-3 text-sm text-muted-foreground">
                    Ningún tag activado todavía.
                </p>
            </div>

            <div v-if="support !== null" class="fl-card p-5">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="text-sm font-semibold text-foreground">
                        Soporte
                    </h2>
                    <Link
                        href="/admin/soporte"
                        class="inline-flex items-center gap-1 text-xs font-medium text-fl-gold-ink hover:underline"
                    >
                        Incidencias <ArrowRight class="size-3.5" />
                    </Link>
                </div>
                <dl class="mt-3 grid grid-cols-2 gap-3">
                    <div class="rounded-lg bg-muted/50 px-3 py-2">
                        <dt class="text-xs text-muted-foreground">Abiertas</dt>
                        <dd class="legacy-numeric text-xl font-semibold">
                            {{ num(support.open) }}
                        </dd>
                    </div>
                    <div class="rounded-lg bg-muted/50 px-3 py-2">
                        <dt class="text-xs text-muted-foreground">
                            En proceso
                        </dt>
                        <dd class="legacy-numeric text-xl font-semibold">
                            {{ num(support.in_progress) }}
                        </dd>
                    </div>
                </dl>
                <ul
                    v-if="support.recent.length"
                    class="mt-3 divide-y divide-border"
                >
                    <li v-for="row in support.recent" :key="row.uuid">
                        <Link
                            :href="`/admin/soporte/${row.uuid}`"
                            class="flex min-h-12 items-center gap-3 py-2 text-sm hover:text-fl-gold-ink"
                        >
                            <LifeBuoy
                                class="size-4 shrink-0 text-muted-foreground"
                            />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-foreground">{{
                                    row.subject
                                }}</span>
                                <span
                                    class="block truncate text-xs text-muted-foreground"
                                    >{{ row.number }} · {{ row.type_label }} ·
                                    {{ ago(row.created_at) }}</span
                                >
                            </span>
                            <span
                                v-if="
                                    row.priority === 'high' ||
                                    row.priority === 'urgent'
                                "
                                class="shrink-0 rounded-full border border-red-500/30 bg-red-50 px-2 py-0.5 text-[11px] text-red-800"
                                >{{
                                    row.priority === 'urgent'
                                        ? 'Urgente'
                                        : 'Alta'
                                }}</span
                            >
                            <span
                                class="hidden shrink-0 rounded-full border border-border px-2 py-0.5 text-[11px] text-muted-foreground sm:inline"
                                >{{ row.status_label }}</span
                            >
                        </Link>
                    </li>
                </ul>
                <p
                    v-else
                    class="mt-3 flex items-center gap-2 text-sm text-muted-foreground"
                >
                    <CheckCircle2 class="size-4 text-emerald-600" />
                    Sin incidencias abiertas.
                </p>
            </div>
        </section>

        <!-- INVENTARIO + PRODUCTO -->
        <section class="grid gap-3 sm:gap-4 lg:grid-cols-2">
            <div class="fl-card p-5" aria-labelledby="h-inv">
                <div class="flex items-center justify-between gap-2">
                    <h2
                        id="h-inv"
                        class="text-sm font-semibold text-foreground"
                    >
                        Inventario
                    </h2>
                    <Boxes class="size-4 text-fl-gold-ink" />
                </div>
                <dl class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Tags disponibles
                        </dt>
                        <dd class="legacy-numeric text-xl font-semibold">
                            {{ num(c.inventory.units_available) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Reservados
                        </dt>
                        <dd class="legacy-numeric text-xl font-semibold">
                            {{ num(c.inventory.units_reserved) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Stock bajo
                        </dt>
                        <dd
                            class="legacy-numeric text-xl font-semibold"
                            :class="
                                c.inventory.low_stock_count
                                    ? 'text-amber-700'
                                    : ''
                            "
                        >
                            {{ num(c.inventory.low_stock_count) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Agotados</dt>
                        <dd
                            class="legacy-numeric text-xl font-semibold"
                            :class="
                                c.inventory.sold_out_count ? 'text-red-700' : ''
                            "
                        >
                            {{ num(c.inventory.sold_out_count) }}
                        </dd>
                    </div>
                </dl>
                <ul
                    v-if="
                        c.inventory.sold_out.length ||
                        c.inventory.low_stock.length
                    "
                    class="mt-4 divide-y divide-border text-sm"
                >
                    <li
                        v-for="row in [
                            ...c.inventory.sold_out,
                            ...c.inventory.low_stock,
                        ].slice(0, 6)"
                        :key="row.variant_id"
                        class="flex min-h-10 items-center justify-between gap-3 py-1.5"
                    >
                        <span class="min-w-0">
                            <span class="block truncate text-foreground">{{
                                row.name
                            }}</span>
                            <span class="text-xs text-muted-foreground">{{
                                row.sku
                            }}</span>
                        </span>
                        <span
                            class="legacy-numeric shrink-0 text-xs font-semibold"
                            :class="
                                row.available <= 0
                                    ? 'text-red-700'
                                    : 'text-amber-700'
                            "
                        >
                            {{
                                row.available <= 0
                                    ? 'Agotado'
                                    : `${row.available} / mín. ${row.minimum}`
                            }}
                        </span>
                    </li>
                </ul>
                <p
                    v-else-if="c.inventory.tracked_skus === 0"
                    class="mt-4 text-sm text-muted-foreground"
                >
                    Sin información todavía: no hay SKUs con inventario.
                </p>
                <p v-else class="mt-4 text-sm text-muted-foreground">
                    Ningún SKU por debajo de su mínimo.
                </p>
            </div>

            <div class="fl-card p-5" aria-labelledby="h-prod">
                <div class="flex items-center justify-between gap-2">
                    <h2
                        id="h-prod"
                        class="text-sm font-semibold text-foreground"
                    >
                        Producto
                    </h2>
                    <Activity class="size-4 text-fl-gold-ink" />
                </div>
                <p class="mt-3 text-xs text-muted-foreground">
                    Aperturas de tags por fuente
                </p>
                <ul v-if="c.product.opens_total" class="mt-2 space-y-2">
                    <li
                        v-for="o in opens"
                        :key="o.key"
                        class="grid grid-cols-[5.5rem_1fr_3rem] items-center gap-3 text-sm"
                    >
                        <span class="inline-flex items-center gap-1.5">
                            <component
                                :is="o.icon"
                                class="size-3.5 text-muted-foreground"
                            />
                            {{ o.label }}
                        </span>
                        <span class="h-2 rounded-full bg-muted">
                            <span
                                class="block h-full rounded-full bg-foreground/80"
                                :style="{
                                    width: `${(o.value / opensMax) * 100}%`,
                                }"
                            />
                        </span>
                        <span class="legacy-numeric text-right">{{
                            num(o.value)
                        }}</span>
                    </li>
                </ul>
                <p v-else class="mt-2 text-sm text-muted-foreground">
                    Sin información todavía.
                </p>
                <dl
                    class="mt-5 grid grid-cols-2 gap-3 border-t border-border pt-4 sm:grid-cols-4"
                >
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Tags con incidencias
                        </dt>
                        <dd class="legacy-numeric text-xl font-semibold">
                            {{ num(c.product.tags_with_incidents) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Defectos</dt>
                        <dd class="legacy-numeric text-xl font-semibold">
                            {{ num(c.product.defects) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Devoluciones
                        </dt>
                        <dd class="legacy-numeric text-xl font-semibold">
                            {{ num(c.product.returns) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Reemplazos
                        </dt>
                        <dd class="legacy-numeric text-xl font-semibold">
                            {{ num(c.product.replacements) }}
                        </dd>
                    </div>
                </dl>
                <Link
                    href="/admin/soporte"
                    class="mt-4 inline-flex items-center gap-1 text-xs font-medium text-fl-gold-ink hover:underline"
                >
                    <LifeBuoy class="size-3.5" /> Ir a Soporte
                </Link>
            </div>
        </section>

        <!-- COLECCIONES -->
        <section class="fl-card p-5" aria-labelledby="h-col">
            <div class="flex items-center justify-between gap-2">
                <h2 id="h-col" class="text-sm font-semibold text-foreground">
                    Colecciones · {{ rangeLabel }}
                </h2>
                <Sparkles class="size-4 text-fl-gold-ink" />
            </div>
            <div
                v-if="c.collections.top"
                class="mt-4 grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)]"
            >
                <div class="rounded-xl bg-fl-cream/60 p-4">
                    <p class="text-xs text-muted-foreground">
                        Colección más vendida
                    </p>
                    <p class="fl-display mt-1 text-2xl">
                        {{ c.collections.top.name }}
                    </p>
                    <dl class="mt-3 grid grid-cols-3 gap-2 text-sm">
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Piezas
                            </dt>
                            <dd class="legacy-numeric font-semibold">
                                {{ num(c.collections.top.units) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Activación
                            </dt>
                            <dd class="legacy-numeric font-semibold">
                                {{ pct(c.collections.top.activation_rate) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Disponibles
                            </dt>
                            <dd class="legacy-numeric font-semibold">
                                {{ num(c.collections.top.available) }}
                            </dd>
                        </div>
                    </dl>
                </div>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-muted-foreground">
                            <th class="pb-2 font-medium">Colección</th>
                            <th class="pb-2 text-right font-medium">Piezas</th>
                            <th class="pb-2 text-right font-medium">
                                Activación
                            </th>
                            <th class="pb-2 text-right font-medium">
                                Disponibles
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="row in c.collections.ranking" :key="row.id">
                            <td class="py-2 text-foreground">{{ row.name }}</td>
                            <td class="legacy-numeric py-2 text-right">
                                {{ num(row.units) }}
                            </td>
                            <td class="legacy-numeric py-2 text-right">
                                {{ pct(row.activation_rate) }}
                            </td>
                            <td class="legacy-numeric py-2 text-right">
                                {{ num(row.available) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-else class="mt-3 text-sm text-muted-foreground">
                Sin información todavía: ninguna pieza de colección vendida en
                este periodo.
            </p>
        </section>
    </div>
</template>
