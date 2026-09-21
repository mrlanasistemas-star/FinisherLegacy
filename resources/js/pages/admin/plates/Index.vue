<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Download, Eye } from '@lucide/vue';
import { computed, ref } from 'vue';
import { exportBatch as exportBatchAction } from '@/actions/App/Http/Controllers/Admin/PlateController';
import AdminTable from '@/components/admin/AdminTable.vue';
import SecondaryNav from '@/components/admin/SecondaryNav.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { LEGACY_PLATE_AREA_NAV } from '@/config/areaNav';
import { plateStatus, statusClass, statusLabel } from '@/lib/statusLabels';

const { plates, batchExportLimit } = defineProps<{
    plates: {
        data: {
            id: number;
            serial_number: string;
            athlete_name: string;
            event: string | null;
            status: string;
            generation_mode: string;
            legacy_code: string | null;
            owner: string;
        }[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: { q: string };
    batchExportLimit: number;
}>();

const generationModeLabel: Record<string, string> = {
    integrated: 'Integrada',
    quick: 'Rápida',
};

const columns = [
    { key: 'select', label: '' },
    { key: 'serial_number', label: 'Serie' },
    { key: 'athlete_name', label: 'Atleta' },
    { key: 'event', label: 'Evento' },
    { key: 'generation_mode', label: 'Modo' },
    { key: 'status', label: 'Estado' },
    { key: 'legacy_code', label: 'Legacy Code' },
    { key: 'owner', label: 'Propietario' },
    { key: 'actions', label: '' },
];

const selected = ref(new Set<number>());

function toggle(id: number) {
    if (selected.value.has(id)) {
        selected.value.delete(id);
    } else if (selected.value.size < batchExportLimit) {
        selected.value.add(id);
    }

    selected.value = new Set(selected.value);
}

const overLimit = computed(() => selected.value.size >= batchExportLimit);

function csrfToken(): string {
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

function downloadBatch() {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = exportBatchAction().url;
    form.style.display = 'none';

    const token = document.createElement('input');
    token.type = 'hidden';
    token.name = '_token';
    token.value = csrfToken();
    form.appendChild(token);

    selected.value.forEach((id) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'plate_ids[]';
        input.value = String(id);
        form.appendChild(input);
    });

    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}
</script>

<template>
    <Head title="Placas" />

    <div class="p-4 md:p-8">
        <SecondaryNav :items="LEGACY_PLATE_AREA_NAV" />

        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-xl font-bold text-white">Placas</h1>
            <Button
                v-if="selected.size"
                class="bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                @click="downloadBatch"
            >
                <Download class="size-4" />
                Exportar lote ({{ selected.size }})
            </Button>
        </div>
        <p v-if="overLimit" class="mb-4 text-xs text-amber-400">
            Máximo {{ batchExportLimit }} placas por lote. Descarga este grupo
            antes de seleccionar más.
        </p>

        <AdminTable
            :columns="columns"
            :rows="plates"
            searchable
            :initial-query="filters.q"
        >
            <template #cell-select="{ row }">
                <Checkbox
                    :model-value="selected.has(row.id as number)"
                    @update:model-value="toggle(row.id as number)"
                />
            </template>
            <template #cell-generation_mode="{ row }">
                {{
                    generationModeLabel[row.generation_mode as string] ??
                    row.generation_mode
                }}
            </template>
            <template #cell-status="{ row }">
                <Badge
                    variant="outline"
                    :class="statusClass(plateStatus, row.status as string)"
                >
                    {{ statusLabel(plateStatus, row.status as string) }}
                </Badge>
            </template>
            <template #cell-actions="{ row }">
                <Button
                    as-child
                    size="sm"
                    variant="outline"
                    class="border-white/15 text-white hover:bg-white/10"
                >
                    <Link :href="`/admin/plates/${row.id}`">
                        <Eye class="size-3.5" />
                        Ver
                    </Link>
                </Button>
            </template>
        </AdminTable>
    </div>
</template>
