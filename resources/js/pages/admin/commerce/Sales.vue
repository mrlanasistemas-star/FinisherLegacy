<script setup lang="ts">
/**
 * Comercio → Ventas: paid sales by day (bar chart) and by product, for
 * 7 / 30 / 90 days, compared with the previous period. Default currency
 * only — other currencies are never summed in. Cards on phones.
 */
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowDownRight, ArrowUpRight, ChartColumn } from '@lucide/vue';
import { computed } from 'vue';
import SalesBarChart from '@/components/commerce/SalesBarChart.vue';
import Money from '@/components/shared/Money.vue';

type Totals = { total_minor: number; orders: number; units: number };

const props = defineProps<{
    period: number;
    periods: number[];
    currency: string;
    totals: Totals;
    previous: Totals;
    series: { date: string; total_minor: number; orders: number }[];
    products: {
        product_id: number | null;
        name: string;
        type: string | null;
        image_url: string | null;
        units: number;
        revenue_minor: number;
        orders: number;
    }[];
}>();

const change = computed(() =>
    props.previous.total_minor > 0
        ? Math.round(
              ((props.totals.total_minor - props.previous.total_minor) /
                  props.previous.total_minor) *
                  100,
          )
        : null,
);

const averageTicket = computed(() =>
    props.totals.orders > 0
        ? Math.round(props.totals.total_minor / props.totals.orders)
        : 0,
);

const maxRevenue = computed(() =>
    Math.max(1, ...props.products.map((p) => p.revenue_minor)),
);

function setPeriod(days: number) {
    router.get(
        '/admin/comercio/ventas',
        { periodo: days },
        { preserveScroll: true, preserveState: true, replace: true },
    );
}
</script>

<template>
    <Head title="Ventas" />

    <div class="w-full space-y-6 px-4 py-6 md:px-6 md:py-8 xl:px-8">
        <header class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="fl-eyebrow">Comercio</p>
                <h1 class="flex items-center gap-2 text-xl font-bold">
                    <ChartColumn class="size-5 text-fl-gold-ink" />
                    Ventas
                </h1>
            </div>
            <div
                class="inline-flex rounded-full bg-muted p-1"
                role="group"
                aria-label="Periodo"
            >
                <button
                    v-for="days in periods"
                    :key="days"
                    type="button"
                    class="min-h-9 rounded-full px-3.5 text-sm font-medium"
                    :aria-pressed="days === period"
                    :class="
                        days === period
                            ? 'bg-card shadow-sm'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    @click="setPeriod(days)"
                >
                    {{ days }} días
                </button>
            </div>
        </header>

        <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="fl-card p-4">
                <p class="text-xs text-muted-foreground">Ventas pagadas</p>
                <p class="legacy-numeric mt-1 text-2xl font-semibold">
                    <Money :minor="totals.total_minor" :currency="currency" />
                </p>
                <p
                    v-if="change !== null"
                    class="mt-1 inline-flex items-center text-xs"
                    :class="change >= 0 ? 'text-emerald-700' : 'text-red-700'"
                >
                    <component
                        :is="change >= 0 ? ArrowUpRight : ArrowDownRight"
                        class="size-3"
                    />{{ Math.abs(change) }}% vs periodo anterior
                </p>
            </div>
            <div class="fl-card p-4">
                <p class="text-xs text-muted-foreground">Pedidos</p>
                <p class="legacy-numeric mt-1 text-2xl font-semibold">
                    {{ totals.orders }}
                </p>
            </div>
            <div class="fl-card p-4">
                <p class="text-xs text-muted-foreground">Piezas</p>
                <p class="legacy-numeric mt-1 text-2xl font-semibold">
                    {{ totals.units }}
                </p>
            </div>
            <div class="fl-card p-4">
                <p class="text-xs text-muted-foreground">Ticket promedio</p>
                <p class="legacy-numeric mt-1 text-2xl font-semibold">
                    <Money :minor="averageTicket" :currency="currency" />
                </p>
            </div>
        </section>

        <section class="fl-card p-4 md:p-5">
            <h2 class="mb-3 text-sm font-semibold">Por día</h2>
            <SalesBarChart
                :points="series"
                :currency="currency"
                :height="220"
            />
        </section>

        <section class="fl-card p-4 md:p-5">
            <h2 class="mb-3 text-sm font-semibold">Por producto</h2>
            <ul v-if="products.length" class="divide-y divide-border">
                <li
                    v-for="product in products"
                    :key="product.product_id ?? product.name"
                    class="py-3"
                >
                    <component
                        :is="product.product_id ? Link : 'div'"
                        :href="
                            product.product_id
                                ? `/admin/products/${product.product_id}`
                                : undefined
                        "
                        class="flex items-center gap-3"
                    >
                        <img
                            v-if="product.image_url"
                            :src="product.image_url"
                            alt=""
                            loading="lazy"
                            class="size-10 rounded-md object-cover"
                        />
                        <span v-else class="size-10 rounded-md bg-muted" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">
                                {{ product.name }}
                            </p>
                            <div
                                class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-muted"
                                aria-hidden="true"
                            >
                                <div
                                    class="h-full rounded-full bg-foreground/80"
                                    :style="{
                                        width: `${(product.revenue_minor / maxRevenue) * 100}%`,
                                    }"
                                />
                            </div>
                        </div>
                        <div class="shrink-0 text-right text-sm">
                            <Money
                                class="legacy-numeric block font-semibold"
                                :minor="product.revenue_minor"
                                :currency="currency"
                            />
                            <span class="text-xs text-muted-foreground"
                                >{{ product.units }} u. ·
                                {{ product.orders }} ped.</span
                            >
                        </div>
                    </component>
                </li>
            </ul>
            <p v-else class="text-sm text-muted-foreground">
                Sin ventas pagadas en este periodo.
            </p>
        </section>
    </div>
</template>
