<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Eye, Plus, Wrench } from '@lucide/vue';
import AdminTable from '@/components/admin/AdminTable.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

defineProps<{
    editions: {
        data: {
            id: number;
            event: string;
            edition: string;
            event_date: string;
            status: string;
            phase: string;
        }[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: { q: string };
}>();

const columns = [
    { key: 'event', label: 'Evento' },
    { key: 'edition', label: 'Edición' },
    { key: 'event_date', label: 'Fecha' },
    { key: 'phase', label: 'Fase' },
    { key: 'actions', label: '' },
];
</script>

<template>
    <Head title="Eventos" />

    <div class="p-4 md:p-8">
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-xl font-bold text-white">Eventos</h1>
            <Button
                as-child
                class="bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
            >
                <Link href="/admin/editions/create">
                    <Plus class="size-4" />
                    Nuevo evento
                </Link>
            </Button>
        </div>

        <AdminTable
            :columns="columns"
            :rows="editions"
            searchable
            :initial-query="filters.q"
        >
            <template #cell-phase="{ row }">
                <Badge variant="outline" class="border-white/15 text-white/60">
                    {{ row.phase }}
                </Badge>
            </template>
            <template #cell-actions="{ row }">
                <div class="flex justify-end gap-2">
                    <Button
                        as-child
                        size="sm"
                        variant="outline"
                        class="border-white/15 text-white hover:bg-white/10"
                    >
                        <Link :href="`/admin/editions/${row.id}`">
                            <Eye class="size-3.5" />
                            Ver
                        </Link>
                    </Button>
                    <Button
                        as-child
                        size="sm"
                        variant="outline"
                        class="border-white/15 text-white hover:bg-white/10"
                    >
                        <Link
                            :href="`/admin/events/${row.id}/production-setup`"
                        >
                            <Wrench class="size-3.5" />
                            Producción
                        </Link>
                    </Button>
                </div>
            </template>
        </AdminTable>
    </div>
</template>
