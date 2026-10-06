<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AdminTable from '@/components/admin/AdminTable.vue';
import { Badge } from '@/components/ui/badge';
import { legacyCodeStatus, statusClass, statusLabel } from '@/lib/statusLabels';

defineProps<{
    legacyCodes: {
        data: {
            id: number;
            code: string;
            status: string;
            owner: string;
            plate: string;
            claimed_at: string;
        }[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: { q: string };
}>();

const columns = [
    { key: 'code', label: 'Código' },
    { key: 'status', label: 'Estado' },
    { key: 'owner', label: 'Propietario' },
    { key: 'plate', label: 'Placa' },
    { key: 'claimed_at', label: 'Reclamado' },
];
</script>

<template>
    <Head title="Legacy Codes" />

    <div class="p-4 md:p-8">
        <h1 class="mb-6 text-xl font-bold text-foreground">Legacy Codes</h1>

        <AdminTable
            :columns="columns"
            :rows="legacyCodes"
            searchable
            :initial-query="filters.q"
        >
            <template #cell-code="{ row }">
                <span class="font-mono text-fl-gold-ink">{{ row.code }}</span>
            </template>
            <template #cell-status="{ row }">
                <Badge
                    variant="outline"
                    :class="statusClass(legacyCodeStatus, row.status as string)"
                >
                    {{ statusLabel(legacyCodeStatus, row.status as string) }}
                </Badge>
            </template>
        </AdminTable>
    </div>
</template>
