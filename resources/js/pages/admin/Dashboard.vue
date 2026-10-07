<script setup lang="ts">
/**
 * Administración → Resumen. Every figure is a live aggregate from
 * Admin\DashboardController — no sample numbers. Full width: KPIs, sales
 * chart for the selected period, Legacy Plate production pipeline (labels
 * over the existing internal states), commerce and photo panels.
 */
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowDownRight,
    ArrowRight,
    ArrowUpRight,
    Calendar,
    Camera,
    ChevronRight,
    ClipboardList,
    Factory,
    Hourglass,
    Inbox,
    Package,
    PackageX,
    Percent,
    ReceiptText,
    ShoppingBag,
    TriangleAlert,
    UserRoundPlus,
    Users,
    Wallet,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import Money from '@/components/shared/Money.vue';
import { paymentStatus, statusClass, statusLabel } from '@/lib/statusLabels';

type SalesTotal = { currency: string; total_minor: number; orders: number };

const props = defineProps<{
    period: number;
    periods: number[];
    currency: string;
    kpis: {
        pending_orders: number;
        sales: SalesTotal[];
        sales_previous: SalesTotal[];
        plates_in_production: number;
        photos_pending: number;
        photographers_pending: number;
        low_stock: number;
    };
    salesSeries: { date: string; total_minor: number; orders: number }[];
    production: {
        stages: { key: string; label: string; count: number; states: string }[];
        delivered: number;
    };
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
    activeOffers: {
        id: number;
        name: string;
        label: string;
        applies_to: string;
        products_count: number;
        ends_at: string | null;
    }[];
    lowStock: {
        product_id: number;
        sku: string;
        product: string;
        variant: string;
        image_url: string | null;
        available: number;
        reserved: number;
    }[];
    photos: {
        pending: number;
        published: number;
        rejected: number;
        sales: {
            currency: string;
            sold: number;
            gross_minor: number;
            net_minor: number;
        }[];
        photographers_pending: {
            id: number;
            uuid: string;
            name: string;
            city: string | null;
            created_at: string | null;
        }[];
        recent_pending: { uuid: string; thumb_url: string }[];
    };
    general: {
        athletes: number;
        events: number;
        participants: number;
        preregistrations: number;
        open_incidents: number;
        new_messages: number;
    };
}>();

const fmt = new Intl.NumberFormat('es-MX');

const mainSales = computed(
    () =>
        props.kpis.sales.find((s) => s.currency === props.currency) ?? {
            currency: props.currency,
            total_minor: 0,
            orders: 0,
        },
);
const otherSales = computed(() =>
    props.kpis.sales.filter((s) => s.currency !== props.currency),
);
const previousMain = computed(
    () =>
        props.kpis.sales_previous.find((s) => s.currency === props.currency)
            ?.total_minor ?? 0,
);
const delta = computed(() => {
    if (previousMain.value === 0) {
        return null;
    }

    return Math.round(
        ((mainSales.value.total_minor - previousMain.value) /
            previousMain.value) *
            100,
    );
});

const kpiCards = computed(() => [
    {
        key: 'orders',
        label: 'Pedidos pendientes',
        value: props.kpis.pending_orders,
        icon: ShoppingBag,
        href: '/admin/orders',
        tone: 'bg-sky-50 text-sky-700',
    },
    {
        key: 'plates',
        label: 'Placas en producción',
        value: props.kpis.plates_in_production,
        icon: Factory,
        href: '/admin/legacy-plates/production',
        tone: 'bg-fl-cream text-fl-gold-ink',
    },
    {
        key: 'photos',
        label: 'Fotos pendientes',
        value: props.kpis.photos_pending,
        icon: Camera,
        href: '/admin/photos?status=review',
        tone: 'bg-violet-50 text-violet-700',
    },
    {
        key: 'photographers',
        label: 'Fotógrafos pendientes',
        value: props.kpis.photographers_pending,
        icon: UserRoundPlus,
        href: '/admin/photographers?status=pending',
        tone: 'bg-emerald-50 text-emerald-700',
    },
    {
        key: 'stock',
        label: 'Stock bajo',
        value: props.kpis.low_stock,
        icon: PackageX,
        href: '/admin/inventory',
        tone:
            props.kpis.low_stock > 0
                ? 'bg-amber-50 text-amber-700'
                : 'bg-muted text-muted-foreground',
    },
]);

// --- Sales chart (single series: daily paid sales, default currency) ----

const W = 720;
const H = 200;
const PAD_B = 22;
const max = computed(() =>
    Math.max(...props.salesSeries.map((d) => d.total_minor), 1),
);
const barGap = 2;
const barWidth = computed(() =>
    Math.max(W / props.salesSeries.length - barGap, 2),
);
const hovered = ref<number | null>(null);
const hoveredPoint = computed(() =>
    hovered.value === null ? null : props.salesSeries[hovered.value],
);
const hasSales = computed(() => props.salesSeries.some((d) => d.total_minor));

function barHeight(value: number) {
    return value === 0 ? 0 : Math.max(((H - PAD_B) * value) / max.value, 3);
}

const shortDate = (iso: string) =>
    new Date(`${iso}T12:00:00`).toLocaleDateString('es-MX', {
        day: 'numeric',
        month: 'short',
    });

const axisTicks = computed(() => {
    const n = props.salesSeries.length;
    const step = n <= 7 ? 1 : n <= 30 ? 7 : 15;

    return props.salesSeries
        .map((d, i) => ({ i, label: shortDate(d.date) }))
        .filter(({ i }) => i % step === 0 || i === n - 1);
});

function setPeriod(days: number) {
    router.get(
        '/admin',
        { period: days },
        { preserveScroll: true, preserveState: true, replace: true },
    );
}

// --- Production -----------------------------------------------------------

const productionTotal = computed(() =>
    props.production.stages.reduce((sum, s) => sum + s.count, 0),
);

const generalCards = computed(() => [
    {
        label: 'Atletas',
        value: props.general.athletes,
        icon: Users,
        href: '/admin/athletes',
    },
    {
        label: 'Eventos',
        value: props.general.events,
        icon: Calendar,
        href: '/admin/editions',
    },
    {
        label: 'Prerregistros',
        value: props.general.preregistrations,
        icon: ClipboardList,
        href: '/admin/preregistrations',
    },
    {
        label: 'Incidencias abiertas',
        value: props.general.open_incidents,
        icon: TriangleAlert,
        href: '/admin/incidents',
    },
]);

const timeAgo = (iso: string | null) =>
    iso
        ? new Date(iso).toLocaleDateString('es-MX', {
              day: 'numeric',
              month: 'short',
          })
        : '';

const today = new Date().toLocaleDateString('es-MX', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
});
</script>

<template>
    <Head title="Resumen · Administración" />

    <div class="w-full space-y-8 p-4 md:p-8">
        <!-- Header -->
        <div
            class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
        >
            <div>
                <p class="fl-eyebrow first-letter:uppercase">{{ today }}</p>
                <h1 class="fl-display mt-2 text-3xl sm:text-4xl">Resumen</h1>
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
                    <ChevronRight class="size-4 text-muted-foreground" />
                </Link>
                <div
                    class="inline-flex rounded-full border border-border bg-card p-1"
                    role="group"
                    aria-label="Periodo"
                >
                    <button
                        v-for="days in periods"
                        :key="days"
                        type="button"
                        class="rounded-full px-3.5 py-1.5 text-xs font-semibold transition-colors"
                        :class="
                            period === days
                                ? 'bg-foreground text-background'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                        :aria-pressed="period === days"
                        @click="setPeriod(days)"
                    >
                        {{ days }} días
                    </button>
                </div>
            </div>
        </div>

        <!-- KPIs -->
        <section
            class="grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-3 xl:grid-cols-6"
            aria-label="Indicadores"
        >
            <div
                class="fl-card col-span-2 flex flex-col justify-between p-5 md:col-span-3 xl:col-span-1"
            >
                <div class="flex items-center justify-between gap-2">
                    <p class="text-xs font-medium text-muted-foreground">
                        Ventas · {{ period }} días
                    </p>
                    <span
                        class="flex size-8 items-center justify-center rounded-lg bg-foreground text-background"
                    >
                        <Wallet class="size-4" />
                    </span>
                </div>
                <p
                    class="legacy-numeric mt-3 text-2xl font-semibold text-foreground sm:text-3xl"
                >
                    <Money
                        :minor="mainSales.total_minor"
                        :currency="mainSales.currency"
                    />
                </p>
                <p
                    class="mt-1 flex flex-wrap items-center gap-x-2 text-xs text-muted-foreground"
                >
                    {{ mainSales.orders }} pedidos pagados
                    <span
                        v-if="delta !== null"
                        class="inline-flex items-center gap-0.5 font-semibold"
                        :class="
                            delta >= 0 ? 'text-emerald-700' : 'text-red-700'
                        "
                    >
                        <component
                            :is="delta >= 0 ? ArrowUpRight : ArrowDownRight"
                            class="size-3.5"
                        />
                        {{ Math.abs(delta) }}% vs. periodo anterior
                    </span>
                </p>
                <p
                    v-if="otherSales.length"
                    class="mt-1 text-xs text-muted-foreground"
                >
                    Además:
                    <template v-for="(s, i) in otherSales" :key="s.currency">
                        <Money :minor="s.total_minor" :currency="s.currency" />
                        <template v-if="i < otherSales.length - 1">
                            ·
                        </template>
                    </template>
                </p>
            </div>
            <Link
                v-for="card in kpiCards"
                :key="card.key"
                :href="card.href"
                class="fl-card group flex flex-col justify-between p-5 transition-colors hover:border-foreground/20"
            >
                <div class="flex items-center justify-between gap-2">
                    <p class="text-xs font-medium text-muted-foreground">
                        {{ card.label }}
                    </p>
                    <span
                        class="flex size-8 items-center justify-center rounded-lg"
                        :class="card.tone"
                    >
                        <component :is="card.icon" class="size-4" />
                    </span>
                </div>
                <p
                    class="legacy-numeric mt-3 text-3xl font-semibold text-foreground"
                >
                    {{ fmt.format(card.value) }}
                </p>
                <p
                    class="mt-1 inline-flex items-center gap-1 text-xs text-muted-foreground group-hover:text-foreground"
                >
                    Ver detalle <ArrowRight class="size-3" />
                </p>
            </Link>
        </section>

        <!-- Sales chart + production -->
        <div class="grid grid-cols-1 gap-6 xl:grid-cols-12">
            <section class="fl-card p-5 sm:p-6 xl:col-span-7">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="font-semibold">
                            Ventas pagadas por día ({{ currency }})
                        </h2>
                        <p class="text-xs text-muted-foreground">
                            Últimos {{ period }} días · pedidos con pago
                            confirmado
                        </p>
                    </div>
                    <div
                        v-if="hoveredPoint"
                        class="rounded-lg border border-border bg-card px-3 py-1.5 text-right text-xs shadow-sm"
                        aria-live="polite"
                    >
                        <p class="text-muted-foreground">
                            {{ shortDate(hoveredPoint.date) }} ·
                            {{ hoveredPoint.orders }} pedidos
                        </p>
                        <p class="legacy-numeric font-semibold">
                            <Money
                                :minor="hoveredPoint.total_minor"
                                :currency="currency"
                            />
                        </p>
                    </div>
                </div>
                <div class="relative mt-5">
                    <svg
                        :viewBox="`0 0 ${W} ${H}`"
                        class="h-52 w-full overflow-visible"
                        preserveAspectRatio="none"
                        role="img"
                        :aria-label="`Ventas pagadas por día, últimos ${period} días`"
                        @pointerleave="hovered = null"
                    >
                        <line
                            v-for="f in [0.25, 0.5, 0.75]"
                            :key="f"
                            x1="0"
                            :x2="W"
                            :y1="(H - PAD_B) * f"
                            :y2="(H - PAD_B) * f"
                            stroke="currentColor"
                            class="text-border"
                            stroke-width="1"
                            vector-effect="non-scaling-stroke"
                        />
                        <line
                            x1="0"
                            :x2="W"
                            :y1="H - PAD_B"
                            :y2="H - PAD_B"
                            stroke="currentColor"
                            class="text-foreground/30"
                            vector-effect="non-scaling-stroke"
                        />
                        <g v-for="(d, i) in salesSeries" :key="d.date">
                            <rect
                                :x="i * (barWidth + barGap)"
                                y="0"
                                :width="barWidth + barGap"
                                :height="H - PAD_B"
                                fill="transparent"
                                @pointerenter="hovered = i"
                            />
                            <rect
                                :x="i * (barWidth + barGap)"
                                :y="H - PAD_B - barHeight(d.total_minor)"
                                :width="barWidth"
                                :height="barHeight(d.total_minor)"
                                rx="2"
                                :class="
                                    hovered === i
                                        ? 'fill-fl-gold'
                                        : 'fill-foreground/85'
                                "
                                pointer-events="none"
                            />
                        </g>
                    </svg>
                    <div
                        class="mt-1 flex justify-between text-[10px] text-muted-foreground"
                    >
                        <span v-for="t in axisTicks" :key="t.i">{{
                            t.label
                        }}</span>
                    </div>
                    <p
                        v-if="!hasSales"
                        class="absolute inset-x-0 top-16 text-center text-sm text-muted-foreground"
                    >
                        Aún no hay ventas pagadas en este periodo.
                    </p>
                </div>
            </section>

            <section class="fl-card p-5 sm:p-6 xl:col-span-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="font-semibold">Producción Legacy Plate</h2>
                        <p class="text-xs text-muted-foreground">
                            {{ productionTotal }} en proceso ·
                            {{ production.delivered }} entregadas
                        </p>
                    </div>
                    <Link
                        href="/admin/legacy-plates/production"
                        class="text-xs font-medium text-muted-foreground hover:text-foreground"
                        >Tablero →</Link
                    >
                </div>
                <ol class="mt-5 space-y-2.5">
                    <li
                        v-for="(stage, i) in production.stages"
                        :key="stage.key"
                        class="grid grid-cols-[1.75rem_1fr_auto] items-center gap-3"
                    >
                        <span
                            class="flex size-7 items-center justify-center rounded-full text-[11px] font-semibold"
                            :class="
                                stage.count
                                    ? 'bg-foreground text-background'
                                    : 'border border-border text-muted-foreground'
                            "
                            >{{ i + 1 }}</span
                        >
                        <div class="min-w-0">
                            <div class="flex items-baseline justify-between">
                                <p class="truncate text-sm font-medium">
                                    {{ stage.label }}
                                </p>
                                <span
                                    class="ml-2 hidden text-[10px] text-muted-foreground sm:inline"
                                    >{{ stage.states }}</span
                                >
                            </div>
                            <div
                                class="mt-1 h-1.5 overflow-hidden rounded-full bg-muted"
                            >
                                <div
                                    class="h-full rounded-full bg-fl-gold transition-[width] duration-700"
                                    :style="{
                                        width: `${productionTotal ? Math.max((stage.count / productionTotal) * 100, stage.count ? 4 : 0) : 0}%`,
                                    }"
                                />
                            </div>
                        </div>
                        <span
                            class="legacy-numeric w-8 text-right text-lg font-semibold"
                            >{{ stage.count }}</span
                        >
                    </li>
                </ol>
                <p class="mt-4 text-[11px] text-muted-foreground">
                    NFC y clip se registran juntos en el estado interno
                    "Producida".
                </p>
            </section>
        </div>

        <!-- Commerce -->
        <div class="grid grid-cols-1 gap-6 xl:grid-cols-12">
            <section class="fl-card overflow-hidden xl:col-span-7">
                <div
                    class="flex items-center justify-between border-b border-border px-5 py-4"
                >
                    <h2 class="flex items-center gap-2 font-semibold">
                        <ReceiptText class="size-4 text-fl-gold-ink" />
                        Pedidos recientes
                    </h2>
                    <Link
                        href="/admin/orders"
                        class="text-xs font-medium text-muted-foreground hover:text-foreground"
                        >Todos →</Link
                    >
                </div>
                <ul class="divide-y divide-border">
                    <li v-for="order in recentOrders" :key="order.id">
                        <Link
                            :href="`/admin/orders/${order.uuid}`"
                            class="grid grid-cols-[1fr_auto] items-center gap-x-4 gap-y-1 px-5 py-3 transition-colors hover:bg-muted/50 sm:grid-cols-[8rem_1fr_auto_auto]"
                        >
                            <span
                                class="legacy-numeric text-sm font-semibold"
                                >{{ order.number }}</span
                            >
                            <span
                                class="order-3 col-span-2 truncate text-sm text-muted-foreground sm:order-none sm:col-span-1"
                                >{{ order.customer }} · {{ order.items }}
                                {{
                                    order.items === 1 ? 'artículo' : 'artículos'
                                }}
                                · {{ timeAgo(order.created_at) }}</span
                            >
                            <span
                                class="hidden rounded-full border px-2 py-0.5 text-[11px] font-medium sm:inline"
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
                            <span
                                class="legacy-numeric text-right text-sm font-semibold"
                            >
                                <Money
                                    :minor="order.total_minor"
                                    :currency="order.currency"
                                />
                            </span>
                        </Link>
                    </li>
                    <li
                        v-if="!recentOrders.length"
                        class="px-5 py-10 text-center text-sm text-muted-foreground"
                    >
                        Todavía no hay pedidos.
                    </li>
                </ul>
            </section>

            <div class="grid gap-6 xl:col-span-5">
                <section class="fl-card p-5">
                    <div class="flex items-center justify-between">
                        <h2 class="flex items-center gap-2 font-semibold">
                            <Percent class="size-4 text-fl-gold-ink" />
                            Ofertas activas
                        </h2>
                        <Link
                            href="/admin/promotions"
                            class="text-xs font-medium text-muted-foreground hover:text-foreground"
                            >Gestionar →</Link
                        >
                    </div>
                    <ul class="mt-4 space-y-2">
                        <li
                            v-for="offer in activeOffers"
                            :key="offer.id"
                            class="flex items-center gap-3 rounded-xl border border-border px-3 py-2.5"
                        >
                            <span
                                class="rounded-full bg-foreground px-2.5 py-1 text-[11px] font-semibold text-background"
                                >{{ offer.label }}</span
                            >
                            <span class="min-w-0 flex-1">
                                <span
                                    class="block truncate text-sm font-medium"
                                    >{{ offer.name }}</span
                                >
                                <span class="text-xs text-muted-foreground">
                                    {{
                                        offer.applies_to === 'all'
                                            ? 'Toda la tienda'
                                            : `${offer.products_count} productos`
                                    }}
                                    <template v-if="offer.ends_at">
                                        · termina {{ timeAgo(offer.ends_at) }}
                                    </template>
                                </span>
                            </span>
                        </li>
                        <li
                            v-if="!activeOffers.length"
                            class="text-sm text-muted-foreground"
                        >
                            No hay ofertas corriendo ahora.
                        </li>
                    </ul>
                </section>

                <section class="fl-card p-5">
                    <div class="flex items-center justify-between">
                        <h2 class="flex items-center gap-2 font-semibold">
                            <Package class="size-4 text-fl-gold-ink" />
                            Stock bajo
                        </h2>
                        <Link
                            href="/admin/inventory"
                            class="text-xs font-medium text-muted-foreground hover:text-foreground"
                            >Inventario →</Link
                        >
                    </div>
                    <ul class="mt-4 space-y-2">
                        <li
                            v-for="row in lowStock"
                            :key="row.sku"
                            class="flex items-center gap-3"
                        >
                            <span
                                class="size-11 shrink-0 overflow-hidden rounded-lg border border-border bg-muted"
                            >
                                <img
                                    v-if="row.image_url"
                                    :src="row.image_url"
                                    :alt="row.product"
                                    loading="lazy"
                                    decoding="async"
                                    class="size-full object-cover"
                                />
                                <Package
                                    v-else
                                    class="m-3 size-5 text-muted-foreground"
                                />
                            </span>
                            <Link
                                :href="`/admin/products/${row.product_id}`"
                                class="min-w-0 flex-1 hover:underline"
                            >
                                <span
                                    class="block truncate text-sm font-medium"
                                    >{{ row.product }}</span
                                >
                                <span
                                    class="block truncate text-xs text-muted-foreground"
                                    >{{ row.variant }} · {{ row.sku }}</span
                                >
                            </Link>
                            <span
                                class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-xs font-semibold"
                                :class="
                                    row.available <= 0
                                        ? 'border-red-200 bg-red-50 text-red-700'
                                        : 'border-amber-200 bg-amber-50 text-amber-700'
                                "
                            >
                                <AlertTriangle class="size-3" />
                                {{
                                    row.available <= 0
                                        ? 'Agotado'
                                        : `${row.available} disp.`
                                }}
                            </span>
                        </li>
                        <li
                            v-if="!lowStock.length"
                            class="text-sm text-muted-foreground"
                        >
                            Todo el inventario está por encima del mínimo.
                        </li>
                    </ul>
                </section>
            </div>
        </div>

        <!-- Photos -->
        <section class="fl-card p-5 sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="flex items-center gap-2 font-semibold">
                    <Camera class="size-4 text-fl-gold-ink" />
                    Fotografías
                </h2>
                <div class="flex gap-3 text-xs font-medium">
                    <Link
                        href="/admin/photos"
                        class="text-muted-foreground hover:text-foreground"
                        >Revisar fotos →</Link
                    >
                    <Link
                        href="/admin/photographers"
                        class="text-muted-foreground hover:text-foreground"
                        >Fotógrafos →</Link
                    >
                </div>
            </div>
            <div class="mt-5 grid gap-6 lg:grid-cols-12">
                <dl
                    class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:col-span-5 lg:grid-cols-2"
                >
                    <div class="rounded-xl bg-violet-50 p-4">
                        <dt
                            class="flex items-center gap-1.5 text-xs text-violet-700"
                        >
                            <Hourglass class="size-3.5" /> Pendientes
                        </dt>
                        <dd class="legacy-numeric mt-1 text-2xl font-semibold">
                            {{ fmt.format(photos.pending) }}
                        </dd>
                    </div>
                    <div class="rounded-xl bg-emerald-50 p-4">
                        <dt class="text-xs text-emerald-700">Publicadas</dt>
                        <dd class="legacy-numeric mt-1 text-2xl font-semibold">
                            {{ fmt.format(photos.published) }}
                        </dd>
                    </div>
                    <div class="rounded-xl bg-red-50 p-4">
                        <dt class="text-xs text-red-700">Rechazadas</dt>
                        <dd class="legacy-numeric mt-1 text-2xl font-semibold">
                            {{ fmt.format(photos.rejected) }}
                        </dd>
                    </div>
                    <div class="rounded-xl bg-fl-cream p-4">
                        <dt class="text-xs text-fl-gold-ink">
                            Ventas · {{ period }} días
                        </dt>
                        <dd class="legacy-numeric mt-1 text-2xl font-semibold">
                            {{
                                fmt.format(
                                    photos.sales.reduce(
                                        (n, s) => n + s.sold,
                                        0,
                                    ),
                                )
                            }}
                        </dd>
                        <dd
                            v-for="s in photos.sales"
                            :key="s.currency"
                            class="text-xs text-muted-foreground"
                        >
                            <Money
                                :minor="s.gross_minor"
                                :currency="s.currency"
                            />
                            brutos
                        </dd>
                    </div>
                </dl>
                <div class="lg:col-span-4">
                    <p class="mb-2 text-xs font-medium text-muted-foreground">
                        Por revisar
                    </p>
                    <div
                        v-if="photos.recent_pending.length"
                        class="grid grid-cols-4 gap-2"
                    >
                        <Link
                            v-for="photo in photos.recent_pending"
                            :key="photo.uuid"
                            href="/admin/photos?status=review"
                            class="aspect-square overflow-hidden rounded-lg bg-muted"
                        >
                            <img
                                :src="photo.thumb_url"
                                alt="Foto pendiente de revisión"
                                loading="lazy"
                                decoding="async"
                                class="size-full object-cover transition-transform hover:scale-105"
                            />
                        </Link>
                    </div>
                    <p v-else class="text-sm text-muted-foreground">
                        No hay fotos esperando revisión.
                    </p>
                </div>
                <div class="lg:col-span-3">
                    <p class="mb-2 text-xs font-medium text-muted-foreground">
                        Fotógrafos por aprobar
                    </p>
                    <ul class="space-y-2">
                        <li
                            v-for="p in photos.photographers_pending"
                            :key="p.id"
                        >
                            <Link
                                :href="`/admin/photographers/${p.uuid}`"
                                class="flex items-center gap-3 rounded-xl border border-border px-3 py-2 hover:border-foreground/20"
                            >
                                <span
                                    class="flex size-8 items-center justify-center rounded-full bg-fl-cream font-serif text-sm text-fl-gold-ink"
                                    >{{ p.name.charAt(0) }}</span
                                >
                                <span class="min-w-0">
                                    <span
                                        class="block truncate text-sm font-medium"
                                        >{{ p.name }}</span
                                    >
                                    <span class="text-xs text-muted-foreground"
                                        >{{ p.city ?? 'Sin ciudad' }} ·
                                        {{ timeAgo(p.created_at) }}</span
                                    >
                                </span>
                            </Link>
                        </li>
                        <li
                            v-if="!photos.photographers_pending.length"
                            class="text-sm text-muted-foreground"
                        >
                            Sin solicitudes nuevas.
                        </li>
                    </ul>
                </div>
            </div>
        </section>

        <!-- General -->
        <section
            class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4"
            aria-label="General"
        >
            <Link
                v-for="item in generalCards"
                :key="item.label"
                :href="item.href"
                class="fl-card flex items-center gap-4 p-4 hover:border-foreground/20"
            >
                <span
                    class="flex size-10 items-center justify-center rounded-xl bg-muted text-foreground"
                >
                    <component :is="item.icon" class="size-4" />
                </span>
                <span>
                    <span class="block text-xs text-muted-foreground">{{
                        item.label
                    }}</span>
                    <span class="legacy-numeric text-xl font-semibold">{{
                        fmt.format(item.value)
                    }}</span>
                </span>
            </Link>
        </section>
    </div>
</template>
