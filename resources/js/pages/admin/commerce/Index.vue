<script setup lang="ts">
/**
 * Comercio → Resumen (spec §11): one screen that answers ¿vendí hoy? ¿este
 * mes? ¿qué se vende más? ¿qué tallas se acaban? ¿qué falta cobrar? ¿qué
 * entrego? ¿qué está agotado? ¿qué repongo? Compact and operational; on a
 * phone everything stacks into cards. The workspace tabs come from the
 * admin shell.
 */
import { Head, Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowDownRight,
    ArrowRight,
    ArrowUpRight,
    CircleDollarSign,
    PackageCheck,
    Store,
    Truck,
} from '@lucide/vue';
import { computed } from 'vue';
import SalesBarChart from '@/components/commerce/SalesBarChart.vue';
import Money from '@/components/shared/Money.vue';
import { paymentStatus, statusClass, statusLabel } from '@/lib/statusLabels';

type Totals = { total_minor: number; orders: number; units: number };

type StockRow = {
    variant_id: number;
    product_id: number;
    product: string;
    variant: string;
    size: string | null;
    color: string | null;
    image_url: string | null;
    available: number;
    minimum: number;
    alert: 'out' | 'low' | 'ok';
};

const props = defineProps<{
    currency: string;
    can: { orders: boolean; inventory: boolean; payments: boolean };
    kpis: {
        today: Totals;
        yesterday: Totals;
        month: Totals;
        previous_month: Totals;
        orders: {
            awaiting_payment: number;
            paid: number;
            to_deliver: number;
            delivered: number;
            cancelled: number;
        };
    } | null;
    series: { date: string; total_minor: number; orders: number }[];
    topProducts: {
        product_id: number;
        name: string;
        image_url: string | null;
        units: number;
        revenue_minor: number;
    }[];
    toDeliver: {
        uuid: string;
        order_number: string;
        customer: string;
        delivery: string;
        items: string[];
        total_minor: number;
        currency: string;
        created_at: string;
    }[];
    recentPayments: {
        uuid: string;
        order_uuid: string | null;
        order_number: string | null;
        provider: string;
        status: string;
        amount_minor: number;
        currency: string;
        created_at: string | null;
    }[];
    stock: {
        counts: { total: number; low: number; out: number; attention: number };
        restock: StockRow[];
    } | null;
}>();

function delta(current: number, previous: number): number | null {
    if (previous <= 0) {
        return null;
    }

    return Math.round(((current - previous) / previous) * 100);
}

const monthDelta = computed(() =>
    props.kpis
        ? delta(
              props.kpis.month.total_minor,
              props.kpis.previous_month.total_minor,
          )
        : null,
);
const todayDelta = computed(() =>
    props.kpis
        ? delta(props.kpis.today.total_minor, props.kpis.yesterday.total_minor)
        : null,
);

const sinceLabel = (iso: string) => {
    const hours = Math.max(
        0,
        Math.round((Date.now() - new Date(iso).getTime()) / 3_600_000),
    );

    return hours < 24 ? `hace ${hours} h` : `hace ${Math.round(hours / 24)} d`;
};

const providerLabel: Record<string, string> = {
    stripe: 'Tarjeta (Stripe)',
    openpay: 'Tarjeta (OpenPay)',
    manual: 'Manual',
};
</script>

<template>
    <Head title="Comercio" />

    <div class="w-full space-y-6 px-4 py-6 md:px-6 md:py-8 xl:px-8">
        <header>
            <p class="fl-eyebrow">Comercio</p>
            <h1 class="font-serif text-2xl font-semibold md:text-3xl">
                Resumen
            </h1>
        </header>

        <!-- KPIs -->
        <section
            v-if="kpis"
            class="grid grid-cols-2 gap-3 lg:grid-cols-4"
            aria-label="Indicadores"
        >
            <div class="fl-card p-4">
                <p class="text-xs text-muted-foreground">Vendido hoy</p>
                <p class="legacy-numeric mt-1 text-2xl font-semibold">
                    <Money
                        :minor="kpis.today.total_minor"
                        :currency="currency"
                    />
                </p>
                <p
                    class="mt-1 flex items-center gap-1 text-xs text-muted-foreground"
                >
                    {{ kpis.today.orders }}
                    {{ kpis.today.orders === 1 ? 'pedido' : 'pedidos' }}
                    <template v-if="todayDelta !== null">
                        ·
                        <span
                            class="inline-flex items-center"
                            :class="
                                todayDelta >= 0
                                    ? 'text-emerald-700'
                                    : 'text-red-700'
                            "
                        >
                            <component
                                :is="
                                    todayDelta >= 0
                                        ? ArrowUpRight
                                        : ArrowDownRight
                                "
                                class="size-3"
                            />{{ Math.abs(todayDelta) }}% vs ayer
                        </span>
                    </template>
                </p>
            </div>
            <div class="fl-card p-4">
                <p class="text-xs text-muted-foreground">Este mes</p>
                <p class="legacy-numeric mt-1 text-2xl font-semibold">
                    <Money
                        :minor="kpis.month.total_minor"
                        :currency="currency"
                    />
                </p>
                <p
                    class="mt-1 flex items-center gap-1 text-xs text-muted-foreground"
                >
                    {{ kpis.month.units }} piezas
                    <template v-if="monthDelta !== null">
                        ·
                        <span
                            class="inline-flex items-center"
                            :class="
                                monthDelta >= 0
                                    ? 'text-emerald-700'
                                    : 'text-red-700'
                            "
                        >
                            <component
                                :is="
                                    monthDelta >= 0
                                        ? ArrowUpRight
                                        : ArrowDownRight
                                "
                                class="size-3"
                            />{{ Math.abs(monthDelta) }}% vs mes anterior
                        </span>
                    </template>
                </p>
            </div>
            <Link
                href="/admin/orders"
                class="fl-card group p-4 transition hover:border-foreground/30"
            >
                <p
                    class="flex items-center gap-1.5 text-xs text-muted-foreground"
                >
                    <CircleDollarSign class="size-3.5" /> Pendientes de pago
                </p>
                <p class="legacy-numeric mt-1 text-2xl font-semibold">
                    {{ kpis.orders.awaiting_payment }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ kpis.orders.paid }} pagados este mes
                </p>
            </Link>
            <Link
                href="/admin/orders"
                class="fl-card group p-4 transition hover:border-foreground/30"
            >
                <p
                    class="flex items-center gap-1.5 text-xs text-muted-foreground"
                >
                    <Truck class="size-3.5" /> Por entregar
                </p>
                <p class="legacy-numeric mt-1 text-2xl font-semibold">
                    {{ kpis.orders.to_deliver }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ kpis.orders.delivered }} entregados este mes
                </p>
            </Link>
        </section>

        <!-- Restock banner -->
        <Link
            v-if="stock && stock.counts.attention > 0"
            href="/admin/comercio/inventario?estado=attention"
            class="flex items-center gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 transition hover:border-amber-300"
        >
            <AlertTriangle class="size-4 shrink-0" />
            <span>
                <strong>{{ stock.counts.attention }}</strong>
                {{
                    stock.counts.attention === 1
                        ? 'variante necesita'
                        : 'variantes necesitan'
                }}
                reposición<template v-if="stock.counts.out">
                    · {{ stock.counts.out }} agotada{{
                        stock.counts.out === 1 ? '' : 's'
                    }}</template
                >.
            </span>
            <ArrowRight class="ml-auto size-4 shrink-0" />
        </Link>

        <div class="grid gap-4 xl:grid-cols-3">
            <!-- Sales chart -->
            <section v-if="kpis" class="fl-card p-4 md:p-5 xl:col-span-2">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-semibold">Últimos 14 días</h2>
                    <Link
                        href="/admin/comercio/ventas"
                        class="text-xs font-medium text-fl-gold-ink hover:underline"
                        >Ver ventas</Link
                    >
                </div>
                <SalesBarChart :points="series" :currency="currency" />
            </section>

            <!-- Best sellers -->
            <section v-if="kpis" class="fl-card p-4 md:p-5">
                <h2 class="mb-3 text-sm font-semibold">
                    Lo más vendido este mes
                </h2>
                <ol v-if="topProducts.length" class="space-y-3">
                    <li
                        v-for="(product, i) in topProducts"
                        :key="product.product_id"
                    >
                        <Link
                            :href="`/admin/products/${product.product_id}`"
                            class="flex items-center gap-3 rounded-lg hover:bg-muted/40"
                        >
                            <span
                                class="legacy-numeric w-4 text-xs text-muted-foreground"
                                >{{ i + 1 }}</span
                            >
                            <img
                                v-if="product.image_url"
                                :src="product.image_url"
                                alt=""
                                loading="lazy"
                                class="size-9 rounded-md object-cover"
                            />
                            <span v-else class="size-9 rounded-md bg-muted" />
                            <span class="min-w-0 flex-1 truncate text-sm">{{
                                product.name
                            }}</span>
                            <span class="text-right text-xs">
                                <span class="block font-semibold"
                                    >{{ product.units }} u.</span
                                >
                                <Money
                                    class="text-muted-foreground"
                                    :minor="product.revenue_minor"
                                    :currency="currency"
                                />
                            </span>
                        </Link>
                    </li>
                </ol>
                <p v-else class="text-sm text-muted-foreground">
                    Aún no hay ventas pagadas este mes.
                </p>
            </section>
        </div>

        <div class="grid gap-4 xl:grid-cols-3">
            <!-- To deliver -->
            <section v-if="kpis" class="fl-card p-4 md:p-5 xl:col-span-2">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="flex items-center gap-1.5 text-sm font-semibold">
                        <PackageCheck class="size-4 text-fl-gold-ink" /> Qué
                        tengo que entregar
                    </h2>
                    <Link
                        href="/admin/orders"
                        class="text-xs font-medium text-fl-gold-ink hover:underline"
                        >Todos los pedidos</Link
                    >
                </div>
                <ul v-if="toDeliver.length" class="divide-y divide-border">
                    <li v-for="order in toDeliver" :key="order.uuid">
                        <Link
                            :href="`/admin/orders/${order.uuid}`"
                            class="flex items-start gap-3 py-3 hover:bg-muted/30"
                        >
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium">
                                    #{{ order.order_number }} ·
                                    {{ order.customer }}
                                </p>
                                <p
                                    class="truncate text-xs text-muted-foreground"
                                >
                                    {{ order.items.join(', ') }}
                                </p>
                            </div>
                            <div class="shrink-0 text-right text-xs">
                                <span
                                    class="inline-flex rounded-full bg-muted px-2 py-0.5 font-medium"
                                    >{{
                                        order.delivery === 'shipping'
                                            ? 'Envío'
                                            : 'Entrega en mano'
                                    }}</span
                                >
                                <p class="mt-1 text-muted-foreground">
                                    {{ sinceLabel(order.created_at) }}
                                </p>
                            </div>
                        </Link>
                    </li>
                </ul>
                <p v-else class="text-sm text-muted-foreground">
                    Todo lo pagado ya fue entregado.
                </p>
            </section>

            <!-- Sizes running out / restock -->
            <section v-if="stock" class="fl-card p-4 md:p-5">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-semibold">Tallas que se acaban</h2>
                    <Link
                        href="/admin/comercio/inventario?estado=attention"
                        class="text-xs font-medium text-fl-gold-ink hover:underline"
                        >Inventario</Link
                    >
                </div>
                <ul v-if="stock.restock.length" class="space-y-2.5">
                    <li
                        v-for="row in stock.restock"
                        :key="row.variant_id"
                        class="flex items-center gap-3"
                    >
                        <img
                            v-if="row.image_url"
                            :src="row.image_url"
                            alt=""
                            loading="lazy"
                            class="size-9 rounded-md object-cover"
                        />
                        <span v-else class="size-9 rounded-md bg-muted" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm">{{ row.product }}</p>
                            <p class="truncate text-xs text-muted-foreground">
                                {{
                                    [row.size, row.color]
                                        .filter(Boolean)
                                        .join(' · ') || row.variant
                                }}
                            </p>
                        </div>
                        <span
                            class="legacy-numeric shrink-0 rounded-full px-2 py-0.5 text-xs font-semibold ring-1 ring-inset"
                            :class="
                                row.alert === 'out'
                                    ? 'bg-red-50 text-red-700 ring-red-200'
                                    : 'bg-amber-50 text-amber-800 ring-amber-200'
                            "
                            >{{
                                row.alert === 'out'
                                    ? 'Agotado'
                                    : `${row.available} / mín. ${row.minimum}`
                            }}</span
                        >
                    </li>
                </ul>
                <p v-else class="text-sm text-muted-foreground">
                    Todo el inventario está por encima de su mínimo.
                </p>
            </section>
        </div>

        <!-- Recent payments -->
        <section
            v-if="can.payments && recentPayments.length"
            class="fl-card p-4 md:p-5"
        >
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-sm font-semibold">Pagos recientes</h2>
                <Link
                    href="/admin/payments"
                    class="text-xs font-medium text-fl-gold-ink hover:underline"
                    >Todos los pagos</Link
                >
            </div>
            <ul class="divide-y divide-border">
                <li v-for="payment in recentPayments" :key="payment.uuid">
                    <Link
                        :href="
                            payment.order_uuid
                                ? `/admin/orders/${payment.order_uuid}`
                                : '/admin/payments'
                        "
                        class="flex items-center gap-3 py-2.5 text-sm hover:bg-muted/30"
                    >
                        <span class="min-w-0 flex-1 truncate">
                            #{{ payment.order_number }} ·
                            <span class="text-muted-foreground">{{
                                providerLabel[payment.provider] ??
                                payment.provider
                            }}</span>
                        </span>
                        <span
                            class="rounded-full border px-2 py-0.5 text-xs"
                            :class="statusClass(paymentStatus, payment.status)"
                            >{{
                                statusLabel(paymentStatus, payment.status)
                            }}</span
                        >
                        <Money
                            class="legacy-numeric w-24 text-right font-medium"
                            :minor="payment.amount_minor"
                            :currency="payment.currency"
                        />
                    </Link>
                </li>
            </ul>
        </section>

        <div
            v-if="!kpis && !stock"
            class="flex flex-col items-center gap-2 rounded-xl border border-dashed border-border py-14 text-center text-sm text-muted-foreground"
        >
            <Store class="size-8" />
            Usa las pestañas de Comercio para ir a productos o cupones.
        </div>
    </div>
</template>
