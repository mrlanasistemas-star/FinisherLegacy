<script setup lang="ts">
/**
 * Comercial → Aliados: partner businesses that resell tags. Filters (tipo,
 * estado, ciudad) + search; each card opens the ally's detail (inventory,
 * sales, activations, reconciliation, incidents).
 */
import { Head, Link, router } from '@inertiajs/vue3';
import { Handshake, Plus } from '@lucide/vue';
import { ref } from 'vue';
import { show } from '@/actions/App/Http/Controllers/Admin/Commercial/AllyController';
import AdminTable from '@/components/admin/AdminTable.vue';
import AllyFormDialog from '@/components/admin/allies/AllyFormDialog.vue';
import FancySelect from '@/components/forms/FancySelect.vue';
import { Button } from '@/components/ui/button';

type Option = { value: string; label: string };
type AllyRow = {
    id: number;
    uuid: string;
    name: string;
    type_label: string;
    status: string;
    status_label: string;
    contact_name: string | null;
    city: string | null;
    commission_percent: number | null;
    locations_count: number;
    users_count: number;
    units_in_stock: number;
};

const props = defineProps<{
    allies: {
        data: AllyRow[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: {
        q: string;
        type: string | null;
        status: string | null;
        city: string;
    };
    types: Option[];
    statuses: Option[];
    cities: string[];
    canManage: boolean;
}>();

const type = ref<string | number | null>(props.filters.type);
const status = ref<string | number | null>(props.filters.status);
const city = ref<string | number | null>(props.filters.city || null);
const creating = ref(false);

function applyFilters() {
    router.get(
        '/admin/aliados',
        {
            q: props.filters.q || undefined,
            type: type.value || undefined,
            status: status.value || undefined,
            city: city.value || undefined,
        },
        { preserveState: true, replace: true },
    );
}

const statusTone: Record<string, string> = {
    active: 'border-emerald-500/30 text-emerald-700',
    prospect: 'border-sky-500/30 text-sky-700',
    paused: 'border-amber-500/30 text-amber-700',
    ended: 'border-foreground/15 text-muted-foreground',
};

const columns = [
    { key: 'name', label: 'Aliado', primary: true, icon: Handshake },
    { key: 'type_label', label: 'Tipo' },
    { key: 'status', label: 'Estado' },
    { key: 'city', label: 'Ciudad' },
    { key: 'units_in_stock', label: 'Stock' },
    { key: 'commission_percent', label: 'Comisión' },
];
</script>

<template>
    <Head title="Aliados" />

    <div class="w-full p-4 md:p-8">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="fl-eyebrow">Comercial</p>
                <h1 class="fl-display mt-1 text-3xl">Aliados</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Gimnasios, tiendas y organizadores que venden tus tags.
                </p>
            </div>
            <Button v-if="canManage" @click="creating = true">
                <Plus class="size-4" /> Nuevo aliado
            </Button>
        </div>

        <div class="mt-6 grid gap-2 sm:grid-cols-3 lg:max-w-3xl">
            <FancySelect
                v-model="type"
                :options="[{ value: null, label: 'Todos los tipos' }, ...types]"
                placeholder="Tipo"
                aria-label="Filtrar por tipo"
                @update:model-value="applyFilters"
            />
            <FancySelect
                v-model="status"
                :options="[
                    { value: null, label: 'Todos los estados' },
                    ...statuses,
                ]"
                placeholder="Estado"
                aria-label="Filtrar por estado"
                @update:model-value="applyFilters"
            />
            <FancySelect
                v-model="city"
                :options="[
                    { value: null, label: 'Todas las ciudades' },
                    ...cities.map((c) => ({ value: c, label: c })),
                ]"
                placeholder="Ciudad"
                aria-label="Filtrar por ciudad"
                @update:model-value="applyFilters"
            />
        </div>

        <div class="mt-5">
            <AdminTable
                :columns="columns"
                :rows="allies"
                searchable
                :initial-query="filters.q"
            >
                <template #cell-name="{ row }">
                    <Link
                        :href="show.url(row.uuid as string)"
                        class="hover:text-fl-gold-ink hover:underline"
                        >{{ row.name }}</Link
                    >
                    <span
                        class="block text-xs font-normal text-muted-foreground"
                    >
                        {{ row.contact_name ?? 'Sin contacto' }} ·
                        {{ row.locations_count }} ubicación(es) ·
                        {{ row.users_count }} vendedor(es)
                    </span>
                </template>
                <template #cell-status="{ row }">
                    <span
                        class="rounded-full border px-2 py-0.5 text-xs"
                        :class="statusTone[row.status as string]"
                        >{{ row.status_label }}</span
                    >
                </template>
                <template #cell-units_in_stock="{ row }">
                    {{ row.units_in_stock }} piezas
                </template>
                <template #cell-commission_percent="{ row }">
                    {{
                        row.commission_percent === null
                            ? '—'
                            : `${row.commission_percent}%`
                    }}
                </template>
            </AdminTable>
        </div>

        <AllyFormDialog
            v-model:open="creating"
            :types="types"
            :statuses="statuses"
        />
    </div>
</template>
