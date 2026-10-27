<script setup lang="ts">
/**
 * Inventario → Ubicaciones: where the pieces physically are. Table on
 * desktop, cards on phones; create / edit in one dialog.
 */
import { Head, Link } from '@inertiajs/vue3';
import { MapPin, Pencil, Plus, Search } from '@lucide/vue';
import { ref } from 'vue';
import {
    index,
    show,
} from '@/actions/App/Http/Controllers/Admin/Catalog/InventoryLocationController';
import CatalogHeader from '@/components/admin/catalog/CatalogHeader.vue';
import EmptyState from '@/components/admin/catalog/EmptyState.vue';
import LocationFormDialog from '@/components/admin/catalog/LocationFormDialog.vue';
import type { LocationData } from '@/components/admin/catalog/LocationFormDialog.vue';
import TableSkeleton from '@/components/admin/catalog/TableSkeleton.vue';
import FancySelect from '@/components/forms/FancySelect.vue';
import type { FancyOption } from '@/components/forms/FancySelect.vue';
import Pagination from '@/components/public/Pagination.vue';
import type { PaginationLink } from '@/components/public/Pagination.vue';
import { Button } from '@/components/ui/button';
import { useAdminFilters } from '@/composables/useAdminFilters';

type Row = LocationData & {
    slug: string;
    type_label: string | null;
    ally: string | null;
    event_edition: string | null;
    on_hand: number;
    units_in_stock: number;
};

const props = defineProps<{
    locations: { data: Row[]; links: PaginationLink[] };
    filters: { q: string; type: string | null; active: string | null };
    types: FancyOption[];
    allies: FancyOption[];
    editions: FancyOption[];
}>();

const { filters, loading, apply, applyDebounced } = useAdminFilters(
    () => index().url,
    { ...props.filters },
);

const dialogOpen = ref(false);
const editing = ref<LocationData | null>(null);

function openCreate() {
    editing.value = null;
    dialogOpen.value = true;
}

function openEdit(row: Row) {
    editing.value = row;
    dialogOpen.value = true;
}

const activeOptions = [
    { value: null, label: 'Activas e inactivas' },
    { value: '1', label: 'Solo activas' },
    { value: '0', label: 'Solo inactivas' },
];
</script>

<template>
    <Head title="Ubicaciones" />

    <div class="w-full p-4 md:p-8">
        <CatalogHeader
            eyebrow="Inventario"
            title="Ubicaciones"
            description="Almacén, aliados, eventos, stands y tránsito."
            :icon="MapPin"
        >
            <template #actions>
                <Button class="rounded-full" @click="openCreate">
                    <Plus class="size-4" /> Nueva ubicación
                </Button>
            </template>
        </CatalogHeader>

        <div class="mb-5 grid gap-2 sm:grid-cols-[1fr_200px_200px]">
            <div class="relative">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <input
                    v-model="filters.q"
                    type="search"
                    placeholder="Buscar ubicación…"
                    aria-label="Buscar ubicación"
                    class="h-9 w-full rounded-full border border-input bg-card pr-4 pl-10 text-sm outline-none hover:border-foreground/25 focus:border-fl-gold focus:ring-4 focus:ring-fl-gold/15"
                    @input="applyDebounced"
                />
            </div>
            <FancySelect
                :model-value="filters.type"
                :options="[{ value: null, label: 'Todos los tipos' }, ...types]"
                aria-label="Tipo"
                @update:model-value="
                    (v) => apply({ type: (v as string) ?? null })
                "
            />
            <FancySelect
                :model-value="filters.active"
                :options="activeOptions"
                aria-label="Estado"
                @update:model-value="
                    (v) => apply({ active: (v as string) ?? null })
                "
            />
        </div>

        <TableSkeleton v-if="loading" />

        <template v-else-if="locations.data.length">
            <div
                class="hidden overflow-hidden rounded-xl border border-border bg-card md:block"
            >
                <table class="w-full text-sm">
                    <thead>
                        <tr
                            class="border-b border-border bg-muted/40 text-left text-[11px] tracking-wide text-muted-foreground uppercase"
                        >
                            <th class="px-4 py-2.5 font-semibold">Ubicación</th>
                            <th class="px-3 py-2.5 font-semibold">Tipo</th>
                            <th class="px-3 py-2.5 font-semibold">Vínculo</th>
                            <th class="px-3 py-2.5 text-right font-semibold">
                                Stock
                            </th>
                            <th class="px-3 py-2.5 text-right font-semibold">
                                Piezas
                            </th>
                            <th class="px-3 py-2.5 font-semibold">Estado</th>
                            <th class="px-3 py-2.5">
                                <span class="sr-only">Acciones</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in locations.data"
                            :key="row.id"
                            class="border-b border-border last:border-0 hover:bg-muted/30"
                        >
                            <td class="px-4 py-2.5">
                                <Link
                                    :href="show(row.id).url"
                                    class="font-medium hover:underline"
                                    >{{ row.name }}</Link
                                >
                                <span
                                    v-if="row.address"
                                    class="block truncate text-xs text-muted-foreground"
                                    >{{ row.address }}</span
                                >
                            </td>
                            <td class="px-3 py-2.5">{{ row.type_label }}</td>
                            <td class="px-3 py-2.5 text-muted-foreground">
                                {{ row.ally ?? row.event_edition ?? '—' }}
                            </td>
                            <td
                                class="legacy-numeric px-3 py-2.5 text-right font-semibold"
                            >
                                {{ row.on_hand }}
                            </td>
                            <td class="legacy-numeric px-3 py-2.5 text-right">
                                {{ row.units_in_stock }}
                            </td>
                            <td class="px-3 py-2.5">
                                <span
                                    class="rounded-full border px-2 py-0.5 text-xs"
                                    :class="
                                        row.active
                                            ? 'border-emerald-500/30 text-emerald-700'
                                            : 'border-foreground/15 text-muted-foreground'
                                    "
                                    >{{
                                        row.active ? 'Activa' : 'Inactiva'
                                    }}</span
                                >
                            </td>
                            <td class="px-3 py-2.5 text-right">
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    class="rounded-full"
                                    @click="openEdit(row)"
                                >
                                    <Pencil class="size-3.5" /> Editar
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <ul class="space-y-2 md:hidden">
                <li
                    v-for="row in locations.data"
                    :key="`m-${row.id}`"
                    class="rounded-xl border border-border bg-card p-3"
                >
                    <div class="flex items-start justify-between gap-2">
                        <Link :href="show(row.id).url" class="min-w-0">
                            <p class="truncate font-medium">{{ row.name }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ row.type_label }}
                                <template v-if="row.ally || row.event_edition">
                                    · {{ row.ally ?? row.event_edition }}
                                </template>
                            </p>
                        </Link>
                        <Button
                            size="sm"
                            variant="outline"
                            class="h-9 rounded-full"
                            @click="openEdit(row)"
                            >Editar</Button
                        >
                    </div>
                    <p class="mt-2 text-xs text-muted-foreground">
                        Stock
                        <strong class="text-foreground">{{
                            row.on_hand
                        }}</strong>
                        · Piezas {{ row.units_in_stock }} ·
                        {{ row.active ? 'Activa' : 'Inactiva' }}
                    </p>
                </li>
            </ul>
        </template>

        <EmptyState
            v-else
            :icon="MapPin"
            title="Sin ubicaciones"
            text="Crea el almacén central y las ubicaciones de aliados o eventos."
        />

        <div class="mt-6">
            <Pagination :links="locations.links" />
        </div>

        <LocationFormDialog
            v-model:open="dialogOpen"
            :location="editing"
            :types="types"
            :allies="allies"
            :editions="editions"
        />
    </div>
</template>
