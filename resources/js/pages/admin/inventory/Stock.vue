<script setup lang="ts">
/**
 * Inventario → Existencias: pieces by SKU × location (en inventario /
 * reservadas / vendidas / activadas), flagged when in-stock pieces are at
 * or under the SKU minimum. Counted from the units themselves.
 */
import { Head, Link } from '@inertiajs/vue3';
import { AlertTriangle, Boxes, Search } from '@lucide/vue';
import { computed } from 'vue';
import { index as tagsIndex } from '@/actions/App/Http/Controllers/Admin/Catalog/LegacyTagController';
import { index } from '@/actions/App/Http/Controllers/Admin/Catalog/TagStockController';
import CatalogHeader from '@/components/admin/catalog/CatalogHeader.vue';
import EmptyState from '@/components/admin/catalog/EmptyState.vue';
import TableSkeleton from '@/components/admin/catalog/TableSkeleton.vue';
import FancySelect from '@/components/forms/FancySelect.vue';
import type { FancyOption } from '@/components/forms/FancySelect.vue';
import { useAdminFilters } from '@/composables/useAdminFilters';

type Counts = {
    in_stock: number;
    reserved: number;
    sold: number;
    activated: number;
};

type Row = {
    variant_id: number;
    product_id: number;
    product: string | null;
    variant: string;
    sku: string;
    minimum: number;
    totals: Counts;
    low: boolean;
    locations: ({ location_id: number | null; location: string } & Counts)[];
};

const props = defineProps<{
    rows: Row[];
    filters: {
        q: string;
        collection: number | null;
        location: number | null;
        low: boolean;
    };
    collections: FancyOption[];
    locations: FancyOption[];
}>();

const { filters, loading, apply, applyDebounced } = useAdminFilters(
    () => index().url,
    { ...props.filters },
);

const lowCount = computed(() => props.rows.filter((r) => r.low).length);
const columns: { key: keyof Counts; label: string }[] = [
    { key: 'in_stock', label: 'En inventario' },
    { key: 'reserved', label: 'Reservadas' },
    { key: 'sold', label: 'Vendidas' },
    { key: 'activated', label: 'Activadas' },
];
</script>

<template>
    <Head title="Existencias" />

    <div class="w-full px-4 py-6 md:px-6 md:py-8 xl:px-8">
        <CatalogHeader
            eyebrow="Inventario"
            title="Existencias por pieza"
            description="Finisher Legacy Tags por SKU y ubicación. El stock agregado de todo el catálogo sigue en Comercio → Inventario."
            :icon="Boxes"
        >
            <template #actions>
                <Link
                    href="/admin/comercio/inventario"
                    class="inline-flex min-h-9 items-center text-sm font-medium text-muted-foreground hover:text-foreground"
                    >Inventario agregado →</Link
                >
            </template>
        </CatalogHeader>

        <div
            class="mb-5 grid gap-2 sm:grid-cols-2 lg:grid-cols-[1fr_220px_220px_auto]"
        >
            <div class="relative">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <input
                    v-model="filters.q"
                    type="search"
                    placeholder="SKU o producto…"
                    aria-label="Buscar SKU"
                    class="h-9 w-full rounded-full border border-input bg-card pr-4 pl-10 text-sm outline-none hover:border-foreground/25 focus:border-fl-gold focus:ring-4 focus:ring-fl-gold/15"
                    @input="applyDebounced"
                />
            </div>
            <FancySelect
                :model-value="filters.collection"
                :options="[
                    { value: null, label: 'Todas las colecciones' },
                    ...collections,
                ]"
                aria-label="Colección"
                @update:model-value="
                    (v) => apply({ collection: (v as number) ?? null })
                "
            />
            <FancySelect
                :model-value="filters.location"
                :options="[
                    { value: null, label: 'Todas las ubicaciones' },
                    ...locations,
                ]"
                aria-label="Ubicación"
                @update:model-value="
                    (v) => apply({ location: (v as number) ?? null })
                "
            />
            <button
                type="button"
                class="inline-flex min-h-9 items-center gap-1.5 rounded-full border px-3.5 text-sm font-medium"
                :class="
                    filters.low
                        ? 'border-amber-500 bg-amber-50 text-amber-900'
                        : 'border-border bg-card'
                "
                @click="apply({ low: !filters.low })"
            >
                <AlertTriangle class="size-4" /> Stock bajo
                <span v-if="!filters.low" class="text-xs opacity-70">{{
                    lowCount
                }}</span>
            </button>
        </div>

        <TableSkeleton v-if="loading" />

        <div v-else-if="rows.length" class="space-y-3">
            <article
                v-for="row in rows"
                :key="row.variant_id"
                class="overflow-hidden rounded-xl border bg-card"
                :class="row.low ? 'border-amber-300' : 'border-border'"
            >
                <header
                    class="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3"
                >
                    <div class="min-w-0 flex-1">
                        <Link
                            :href="`/admin/products/${row.product_id}`"
                            class="font-semibold hover:underline"
                            >{{ row.product }}</Link
                        >
                        <p class="font-mono text-xs text-muted-foreground">
                            {{ row.sku }} · {{ row.variant }}
                        </p>
                    </div>
                    <dl class="flex gap-4 text-xs text-muted-foreground">
                        <div
                            v-for="c in columns"
                            :key="c.key"
                            class="text-right"
                        >
                            <dt>{{ c.label }}</dt>
                            <dd
                                class="legacy-numeric text-base font-semibold text-foreground"
                            >
                                {{ row.totals[c.key] }}
                            </dd>
                        </div>
                    </dl>
                    <span
                        v-if="row.low"
                        class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-800 ring-1 ring-amber-200"
                        :title="`Mínimo ${row.minimum}`"
                    >
                        <AlertTriangle class="size-3" /> Bajo (mín.
                        {{ row.minimum }})
                    </span>
                </header>
                <div
                    v-if="row.locations.length"
                    class="overflow-x-auto border-t border-border"
                >
                    <table class="w-full min-w-[520px] text-sm">
                        <thead>
                            <tr
                                class="bg-muted/40 text-left text-[11px] tracking-wide text-muted-foreground uppercase"
                            >
                                <th class="px-4 py-2 font-semibold">
                                    Ubicación
                                </th>
                                <th
                                    v-for="c in columns"
                                    :key="c.key"
                                    class="px-3 py-2 text-right font-semibold"
                                >
                                    {{ c.label }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="loc in row.locations"
                                :key="loc.location_id ?? 0"
                                class="border-t border-border"
                            >
                                <td class="px-4 py-2">{{ loc.location }}</td>
                                <td
                                    v-for="c in columns"
                                    :key="c.key"
                                    class="legacy-numeric px-3 py-2 text-right"
                                >
                                    <Link
                                        v-if="loc[c.key] > 0 && loc.location_id"
                                        :href="
                                            tagsIndex({
                                                query: {
                                                    variant: row.variant_id,
                                                    location: loc.location_id,
                                                    ...(c.key === 'sold' ||
                                                    c.key === 'activated'
                                                        ? {}
                                                        : { status: c.key }),
                                                },
                                            }).url
                                        "
                                        class="hover:underline"
                                        >{{ loc[c.key] }}</Link
                                    >
                                    <span
                                        v-else
                                        class="text-muted-foreground"
                                        >{{ loc[c.key] }}</span
                                    >
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p
                    v-else
                    class="border-t border-border px-4 py-3 text-xs text-muted-foreground"
                >
                    Sin piezas recibidas todavía.
                </p>
            </article>
        </div>

        <EmptyState
            v-else
            :icon="Boxes"
            title="Sin existencias que mostrar"
            text="Solo aparecen productos con «Cada pieza es única». Ajusta los filtros o crea un lote."
        />
    </div>
</template>
