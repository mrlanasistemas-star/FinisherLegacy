<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import type { AcceptableValue } from 'reka-ui';
import AdminTable from '@/components/admin/AdminTable.vue';
import { Badge } from '@/components/ui/badge';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    preregistrationStatus,
    statusClass,
    statusLabel,
} from '@/lib/statusLabels';

const props = defineProps<{
    preregistrations: {
        data: {
            id: number;
            name: string;
            email: string;
            event: string | null;
            race: string | null;
            bib_number: string | null;
            status: string;
        }[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    statuses: string[];
    filters: { q: string; status: string | null };
}>();

const columns = [
    { key: 'name', label: 'Nombre' },
    { key: 'email', label: 'Correo' },
    { key: 'event', label: 'Evento' },
    { key: 'race', label: 'Distancia' },
    { key: 'bib_number', label: 'Número' },
    { key: 'status', label: 'Estado' },
];

// A status change navigates without a `page` param, same as the search
// box — it always lands back on page 1 for the new, possibly smaller,
// result set rather than preserving a page number that may not exist
// anymore (product UX consolidation brief §80).
function onStatusChange(value: AcceptableValue) {
    router.get(
        '/admin/preregistrations',
        {
            q: props.filters.q || undefined,
            status: typeof value === 'string' ? value : undefined,
        },
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <Head title="Prerregistros" />

    <div class="p-4 md:p-8">
        <h1 class="mb-6 text-xl font-bold text-white">Prerregistros</h1>

        <div class="mb-4">
            <Select
                :model-value="filters.status ?? undefined"
                @update:model-value="onStatusChange"
            >
                <SelectTrigger
                    class="w-48 border-white/10 bg-fl-black text-white"
                >
                    <SelectValue placeholder="Todos los estados" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="status in statuses"
                        :key="status"
                        :value="status"
                    >
                        {{ statusLabel(preregistrationStatus, status) }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>

        <AdminTable
            :columns="columns"
            :rows="preregistrations"
            searchable
            :initial-query="filters.q"
        >
            <template #cell-status="{ row }">
                <Badge
                    variant="outline"
                    :class="
                        statusClass(preregistrationStatus, row.status as string)
                    "
                >
                    {{
                        statusLabel(preregistrationStatus, row.status as string)
                    }}
                </Badge>
            </template>
        </AdminTable>
    </div>
</template>
