<script setup lang="ts">
/**
 * Administración → Resumen. Every figure is a live aggregate from
 * Admin\DashboardController — Legacy Plate stock from inventory levels,
 * the order workflow from order / plate-entitlement states.
 */
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    Calendar,
    ChevronRight,
    ClipboardList,
    Inbox,
    TriangleAlert,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import { plateStatus, statusLabel } from '@/lib/statusLabels';

const props = defineProps<{
    stats: {
        athletes: number;
        events: number;
        participants: number;
        preregistrations: number;
        plates_today: number;
        pending_production: number;
        delivered: number;
        open_incidents: number;
        new_messages: number;
    };
    productionByStatus: Record<string, number>;
    plateInventory: {
        available: number;
        reserved: number;
        in_personalization: number;
        delivered: number;
    };
    inventory: {
        product_id: number;
        sku: string;
        product: string;
        variant: string;
        available: number;
        reserved: number;
    }[];
    workflow: { key: string; label: string; count: number }[];
}>();

const fmt = new Intl.NumberFormat('es-MX');

const plateCards = computed(() => [
    {
        label: 'Disponibles',
        value: props.plateInventory.available,
        hint: 'En inventario',
    },
    {
        label: 'Reservadas',
        value: props.plateInventory.reserved,
        hint: 'Apartadas por pedidos',
    },
    {
        label: 'En personalización',
        value: props.plateInventory.in_personalization,
        hint: 'Pagadas, por grabar',
    },
    {
        label: 'Producidas / entregadas',
        value: props.plateInventory.delivered,
        hint: 'Listas o entregadas',
    },
]);

const general = computed(() => [
    {
        label: 'Atletas',
        value: props.stats.athletes,
        icon: Users,
        href: '/admin/athletes',
    },
    {
        label: 'Eventos',
        value: props.stats.events,
        icon: Calendar,
        href: '/admin/editions',
    },
    {
        label: 'Prerregistros',
        value: props.stats.preregistrations,
        icon: ClipboardList,
        href: '/admin/preregistrations',
    },
    {
        label: 'Incidencias abiertas',
        value: props.stats.open_incidents,
        icon: TriangleAlert,
        href: '/admin/incidents',
    },
]);

function stockState(available: number) {
    if (available <= 0) {
        return {
            label: 'Agotado',
            class: 'bg-red-50 text-red-700 border-red-200',
        };
    }

    if (available <= 5) {
        return {
            label: 'Bajo',
            class: 'bg-amber-50 text-amber-700 border-amber-200',
        };
    }

    return {
        label: 'OK',
        class: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    };
}

const today = new Date().toLocaleDateString('es-MX', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
});
</script>

<template>
    <Head title="Resumen · Administración" />

    <div class="mx-auto w-full max-w-7xl space-y-8 p-4 md:p-8">
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
        >
            <div>
                <p class="fl-eyebrow capitalize">{{ today }}</p>
                <h1 class="fl-display mt-2 text-3xl sm:text-4xl">Resumen</h1>
            </div>
            <Link
                v-if="stats.new_messages > 0"
                href="/admin/messages"
                class="inline-flex items-center gap-2 self-start rounded-full border border-border bg-card px-4 py-2 text-sm hover:border-foreground/20"
            >
                <Inbox class="size-4 text-fl-gold-ink" />
                {{ stats.new_messages }} mensaje{{
                    stats.new_messages === 1 ? '' : 's'
                }}
                nuevo{{ stats.new_messages === 1 ? '' : 's' }}
                <ChevronRight class="size-4 text-muted-foreground" />
            </Link>
        </div>

        <!-- Plate inventory -->
        <section aria-labelledby="placas-title">
            <div class="mb-3 flex items-center justify-between">
                <h2
                    id="placas-title"
                    class="text-sm font-semibold text-foreground"
                >
                    Inventario de placas
                </h2>
                <Link
                    href="/admin/legacy-plates/production"
                    class="text-xs font-medium text-muted-foreground hover:text-foreground"
                    >Ir a producción →</Link
                >
            </div>
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <div
                    v-for="card in plateCards"
                    :key="card.label"
                    class="fl-card p-5"
                >
                    <p class="text-xs font-medium text-muted-foreground">
                        {{ card.label }}
                    </p>
                    <p
                        class="legacy-numeric mt-2 text-3xl font-semibold text-foreground"
                    >
                        {{ fmt.format(card.value) }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        {{ card.hint }}
                    </p>
                </div>
            </div>
        </section>

        <div class="grid gap-8 lg:grid-cols-12">
            <!-- Inventory table -->
            <section class="lg:col-span-7" aria-labelledby="inventario-title">
                <div class="mb-3 flex items-center justify-between">
                    <h2
                        id="inventario-title"
                        class="text-sm font-semibold text-foreground"
                    >
                        Inventario con menor disponibilidad
                    </h2>
                    <Link
                        href="/admin/inventory"
                        class="text-xs font-medium text-muted-foreground hover:text-foreground"
                        >Ver inventario →</Link
                    >
                </div>
                <div class="fl-card overflow-x-auto">
                    <table class="w-full min-w-[520px] text-sm">
                        <thead>
                            <tr
                                class="border-b border-border text-left text-xs text-muted-foreground"
                            >
                                <th class="px-4 py-3 font-medium">SKU</th>
                                <th class="px-4 py-3 font-medium">Producto</th>
                                <th class="px-4 py-3 text-right font-medium">
                                    Disponible
                                </th>
                                <th class="px-4 py-3 text-right font-medium">
                                    Reservado
                                </th>
                                <th class="px-4 py-3 font-medium">Estado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="row in inventory" :key="row.sku">
                                <td
                                    class="px-4 py-3 font-mono text-xs text-muted-foreground"
                                >
                                    {{ row.sku }}
                                </td>
                                <td class="px-4 py-3">
                                    <Link
                                        :href="`/admin/products/${row.product_id}`"
                                        class="font-medium hover:underline"
                                        >{{ row.product }}</Link
                                    >
                                    <span
                                        class="block text-xs text-muted-foreground"
                                        >{{ row.variant }}</span
                                    >
                                </td>
                                <td
                                    class="legacy-numeric px-4 py-3 text-right font-semibold"
                                >
                                    {{ fmt.format(row.available) }}
                                </td>
                                <td
                                    class="legacy-numeric px-4 py-3 text-right text-muted-foreground"
                                >
                                    {{ fmt.format(row.reserved) }}
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="rounded-full border px-2 py-0.5 text-[11px] font-medium"
                                        :class="stockState(row.available).class"
                                        >{{
                                            stockState(row.available).label
                                        }}</span
                                    >
                                </td>
                            </tr>
                            <tr v-if="!inventory.length">
                                <td
                                    colspan="5"
                                    class="px-4 py-8 text-center text-muted-foreground"
                                >
                                    Aún no hay niveles de inventario
                                    registrados.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Order workflow -->
            <section class="lg:col-span-5" aria-labelledby="proceso-title">
                <h2
                    id="proceso-title"
                    class="mb-3 text-sm font-semibold text-foreground"
                >
                    Proceso de pedido
                </h2>
                <ol class="fl-card divide-y divide-border">
                    <li
                        v-for="(step, index) in workflow"
                        :key="step.key"
                        class="flex items-center gap-4 px-5 py-4"
                    >
                        <span
                            class="legacy-numeric flex size-8 shrink-0 items-center justify-center rounded-full text-xs font-semibold"
                            :class="
                                step.count > 0
                                    ? 'bg-foreground text-background'
                                    : 'bg-muted text-muted-foreground'
                            "
                            >{{ index + 1 }}</span
                        >
                        <span class="flex-1 text-sm font-medium">{{
                            step.label
                        }}</span>
                        <span
                            class="legacy-numeric text-2xl font-semibold"
                            :class="
                                step.count > 0
                                    ? 'text-foreground'
                                    : 'text-muted-foreground/60'
                            "
                            >{{ fmt.format(step.count) }}</span
                        >
                    </li>
                </ol>
                <Link
                    href="/admin/orders"
                    class="mt-3 inline-flex items-center gap-1.5 text-xs font-medium text-muted-foreground hover:text-foreground"
                >
                    Ver pedidos
                    <ArrowRight class="size-3.5" />
                </Link>
            </section>
        </div>

        <!-- General -->
        <section aria-label="Indicadores generales">
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <Link
                    v-for="card in general"
                    :key="card.label"
                    :href="card.href"
                    class="fl-card flex items-center gap-4 p-5 transition-colors hover:border-foreground/20"
                >
                    <span
                        class="flex size-10 shrink-0 items-center justify-center rounded-full bg-fl-cream text-fl-gold-ink"
                    >
                        <component :is="card.icon" class="size-4" />
                    </span>
                    <span>
                        <span
                            class="legacy-numeric block text-2xl font-semibold"
                            >{{ fmt.format(card.value) }}</span
                        >
                        <span class="block text-xs text-muted-foreground">{{
                            card.label
                        }}</span>
                    </span>
                </Link>
            </div>
        </section>

        <section
            v-if="Object.keys(productionByStatus).length"
            class="fl-card p-5"
            aria-labelledby="estado-placas-title"
        >
            <h2
                id="estado-placas-title"
                class="mb-3 text-sm font-semibold text-foreground"
            >
                Placas por estado
            </h2>
            <div class="flex flex-wrap gap-2">
                <div
                    v-for="(count, status) in productionByStatus"
                    :key="status"
                    class="rounded-full border border-border px-3 py-1.5 text-sm"
                >
                    <span class="text-muted-foreground">{{
                        statusLabel(plateStatus, String(status))
                    }}</span>
                    <span class="legacy-numeric ml-2 font-semibold">{{
                        count
                    }}</span>
                </div>
            </div>
        </section>
    </div>
</template>
