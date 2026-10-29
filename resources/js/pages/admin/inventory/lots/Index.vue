<script setup lang="ts">
/**
 * Inventario → Lotes: each manufacturing batch with its codes. Creating a
 * lot generates the codes before fabrication; receiving it puts the good
 * pieces in stock.
 */
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Factory, Plus, Search } from '@lucide/vue';
import { computed } from 'vue';
import {
    create,
    index,
    show,
} from '@/actions/App/Http/Controllers/Admin/Catalog/InventoryLotController';
import CatalogHeader from '@/components/admin/catalog/CatalogHeader.vue';
import EmptyState from '@/components/admin/catalog/EmptyState.vue';
import TableSkeleton from '@/components/admin/catalog/TableSkeleton.vue';
import FancySelect from '@/components/forms/FancySelect.vue';
import type { FancyOption } from '@/components/forms/FancySelect.vue';
import Pagination from '@/components/public/Pagination.vue';
import type { PaginationLink } from '@/components/public/Pagination.vue';
import { Button } from '@/components/ui/button';
import { hasPermission, useAdminFilters } from '@/composables/useAdminFilters';
import { shortDate } from '@/lib/datetime';
import { LOT_STATUS } from '@/lib/tagStatus';

type Lot = {
    uuid: string;
    code: string;
    status: string;
    sku: string | null;
    product: string | null;
    supplier: string | null;
    location: string | null;
    quantity: number;
    quantity_received: number;
    reference: string | null;
    ordered_at: string | null;
    received_at: string | null;
};

const props = defineProps<{
    lots: { data: Lot[]; links: PaginationLink[] };
    filters: {
        q: string;
        status: string | null;
        variant: number | null;
        supplier: number | null;
    };
    statuses: FancyOption[];
    variants: FancyOption[];
    suppliers: FancyOption[];
}>();

const page = usePage();
const canManage = computed(() =>
    hasPermission(page.props.auth?.permissions, 'tags.manage'),
);

const { filters, loading, apply, applyDebounced } = useAdminFilters(
    () => index().url,
    { ...props.filters },
);
</script>

<template>
    <Head title="Lotes" />

    <div class="w-full px-4 py-6 md:px-6 md:py-8 xl:px-8">
        <CatalogHeader
            eyebrow="Inventario"
            title="Lotes"
            description="Fabricación por SKU: códigos antes de producir, recepción en una ubicación."
            :icon="Factory"
        >
            <template v-if="canManage" #actions>
                <Button as-child class="rounded-full">
                    <Link :href="create().url">
                        <Plus class="size-4" /> Nuevo lote
                    </Link>
                </Button>
            </template>
        </CatalogHeader>

        <div
            class="mb-5 grid gap-2 sm:grid-cols-2 lg:grid-cols-[1fr_180px_220px_220px]"
        >
            <div class="relative">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <input
                    v-model="filters.q"
                    type="search"
                    placeholder="Código de lote o referencia…"
                    aria-label="Buscar lote"
                    class="h-9 w-full rounded-full border border-input bg-card pr-4 pl-10 text-sm outline-none hover:border-foreground/25 focus:border-fl-gold focus:ring-4 focus:ring-fl-gold/15"
                    @input="applyDebounced"
                />
            </div>
            <FancySelect
                :model-value="filters.status"
                :options="[
                    { value: null, label: 'Todos los estados' },
                    ...statuses,
                ]"
                aria-label="Estado"
                @update:model-value="
                    (v) => apply({ status: (v as string) ?? null })
                "
            />
            <FancySelect
                :model-value="filters.variant"
                :options="[
                    { value: null, label: 'Todos los SKU' },
                    ...variants,
                ]"
                aria-label="SKU"
                @update:model-value="
                    (v) => apply({ variant: (v as number) ?? null })
                "
            />
            <FancySelect
                :model-value="filters.supplier"
                :options="[
                    { value: null, label: 'Todos los proveedores' },
                    ...suppliers,
                ]"
                aria-label="Proveedor"
                @update:model-value="
                    (v) => apply({ supplier: (v as number) ?? null })
                "
            />
        </div>

        <TableSkeleton v-if="loading" />

        <template v-else-if="lots.data.length">
            <div
                class="hidden overflow-hidden rounded-xl border border-border bg-card md:block"
            >
                <table class="w-full text-sm">
                    <thead>
                        <tr
                            class="border-b border-border bg-muted/40 text-left text-[11px] tracking-wide text-muted-foreground uppercase"
                        >
                            <th class="px-4 py-2.5 font-semibold">Lote</th>
                            <th class="px-3 py-2.5 font-semibold">SKU</th>
                            <th class="px-3 py-2.5 font-semibold">Proveedor</th>
                            <th class="px-3 py-2.5 text-right font-semibold">
                                Piezas
                            </th>
                            <th class="px-3 py-2.5 font-semibold">Ubicación</th>
                            <th class="px-3 py-2.5 font-semibold">Fechas</th>
                            <th class="px-3 py-2.5 font-semibold">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="lot in lots.data"
                            :key="lot.uuid"
                            class="border-b border-border last:border-0 hover:bg-muted/30"
                        >
                            <td class="px-4 py-2.5">
                                <Link
                                    :href="show(lot.uuid).url"
                                    class="font-mono font-medium hover:underline"
                                    >{{ lot.code }}</Link
                                >
                                <span
                                    v-if="lot.reference"
                                    class="block text-xs text-muted-foreground"
                                    >{{ lot.reference }}</span
                                >
                            </td>
                            <td class="px-3 py-2.5">
                                <span class="font-mono text-xs">{{
                                    lot.sku
                                }}</span>
                                <span
                                    class="block text-xs text-muted-foreground"
                                    >{{ lot.product }}</span
                                >
                            </td>
                            <td class="px-3 py-2.5">
                                {{ lot.supplier ?? '—' }}
                            </td>
                            <td class="legacy-numeric px-3 py-2.5 text-right">
                                <template v-if="lot.status === 'ordered'">{{
                                    lot.quantity
                                }}</template>
                                <template v-else
                                    >{{ lot.quantity_received }} /
                                    {{ lot.quantity }}</template
                                >
                            </td>
                            <td class="px-3 py-2.5">
                                {{ lot.location ?? '—' }}
                            </td>
                            <td
                                class="px-3 py-2.5 text-xs text-muted-foreground"
                            >
                                Pedido {{ shortDate(lot.ordered_at) }}
                                <template v-if="lot.received_at"
                                    ><br />Recibido
                                    {{ shortDate(lot.received_at) }}</template
                                >
                            </td>
                            <td class="px-3 py-2.5">
                                <span
                                    class="rounded-full border px-2 py-0.5 text-xs font-medium"
                                    :class="LOT_STATUS[lot.status]?.class"
                                    >{{
                                        LOT_STATUS[lot.status]?.label ??
                                        lot.status
                                    }}</span
                                >
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <ul class="space-y-2 md:hidden">
                <li
                    v-for="lot in lots.data"
                    :key="`m-${lot.uuid}`"
                    class="rounded-xl border border-border bg-card p-3"
                >
                    <Link :href="show(lot.uuid).url" class="block">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-mono font-medium">{{
                                lot.code
                            }}</span>
                            <span
                                class="rounded-full border px-2 py-0.5 text-xs font-medium"
                                :class="LOT_STATUS[lot.status]?.class"
                                >{{ LOT_STATUS[lot.status]?.label }}</span
                            >
                        </div>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ lot.sku }} · {{ lot.quantity }} piezas ·
                            {{ lot.supplier ?? 'Sin proveedor' }}
                        </p>
                    </Link>
                </li>
            </ul>
        </template>

        <EmptyState
            v-else
            :icon="Factory"
            title="Sin lotes"
            text="Crea un lote para generar los códigos que la fábrica codificará en el NFC e imprimirá en el QR."
        />

        <div class="mt-6"><Pagination :links="lots.links" /></div>
    </div>
</template>
