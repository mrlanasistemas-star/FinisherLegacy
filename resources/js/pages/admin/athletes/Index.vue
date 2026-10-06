<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AdminTable from '@/components/admin/AdminTable.vue';
import HelpPopover from '@/components/HelpPopover.vue';

defineProps<{
    athletes: {
        data: {
            id: number;
            full_name: string;
            email: string;
            has_user: string;
            legacy_id: string;
            event_count: number;
            identity_status: string;
        }[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: { q: string };
}>();

const columns = [
    { key: 'full_name', label: 'Nombre' },
    { key: 'email', label: 'Email' },
    { key: 'has_user', label: 'Cuenta' },
    { key: 'legacy_id', label: 'Legacy ID' },
    { key: 'event_count', label: 'Eventos' },
];
</script>

<template>
    <Head title="Atletas" />

    <div class="p-4 md:p-8">
        <h1
            class="mb-1 flex items-center gap-1.5 text-xl font-bold text-foreground"
        >
            Atletas
            <HelpPopover
                title="Identidad canónica"
                text="Un Athlete es una persona real, independiente de si tiene cuenta o no. Un mismo Athlete puede tener varias participaciones (dorsales) en distintos eventos — el dorsal identifica una participación, nunca a la persona."
            />
        </h1>
        <p class="mb-6 text-sm text-muted-foreground">
            Busca por nombre, email o Legacy ID. La columna "Eventos" cuenta
            participaciones distintas — un mismo atleta puede aparecer varias
            veces con dorsales diferentes.
        </p>

        <AdminTable
            :columns="columns"
            :rows="athletes"
            searchable
            :initial-query="filters.q"
        >
            <template #cell-full_name="{ row }">
                <Link
                    :href="`/admin/athletes/${row.id}`"
                    class="text-fl-gold-ink hover:underline"
                >
                    {{ row.full_name }}
                </Link>
            </template>
        </AdminTable>
    </div>
</template>
