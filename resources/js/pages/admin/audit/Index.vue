<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { History } from '@lucide/vue';
import AdminTable from '@/components/admin/AdminTable.vue';
import SecondaryNav from '@/components/admin/SecondaryNav.vue';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { SYSTEM_AREA_NAV } from '@/config/areaNav';

type ActivityRow = {
    id: number;
    causer: string;
    event: string;
    event_label: string;
    subject_type: string;
    subject_type_label: string;
    subject_id: number | null;
    description: string;
    created_at: string;
};

const { activities, events, subjectTypes, users, filters } = defineProps<{
    activities: {
        data: ActivityRow[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    events: { value: string; label: string }[];
    subjectTypes: { value: string; label: string }[];
    users: { id: number; name: string }[];
    filters: {
        user_id: number | null;
        event: string;
        subject_type: string;
        from: string;
        to: string;
    };
}>();

const columns = [
    { key: 'created_at', label: 'Fecha' },
    { key: 'causer', label: 'Usuario' },
    { key: 'event', label: 'Acción' },
    { key: 'subject_type', label: 'Tipo' },
    { key: 'description', label: 'Descripción' },
];

const eventBadgeClass: Record<string, string> = {
    created: 'border-emerald-500/30 text-emerald-400',
    updated: 'border-amber-500/30 text-amber-400',
    deleted: 'border-red-500/30 text-red-400',
};

function updateFilter(key: string, value: string | number | null) {
    router.get(
        '/admin/audit',
        { ...filters, [key]: value || undefined },
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <Head title="Auditoría" />

    <div class="p-4 md:p-8">
        <SecondaryNav :items="SYSTEM_AREA_NAV" />

        <div class="mb-6 flex items-center gap-2">
            <History class="size-5 text-fl-gold" />
            <div>
                <h1 class="text-xl font-bold text-white">Auditoría</h1>
                <p class="text-sm text-white/50">
                    Quién hizo qué, cuándo y sobre qué registro.
                </p>
            </div>
        </div>

        <div class="mb-4 flex flex-wrap gap-3">
            <Select
                :model-value="filters.user_id ? String(filters.user_id) : ' '"
                @update:model-value="
                    (v) =>
                        updateFilter(
                            'user_id',
                            v && String(v).trim() ? Number(v) : null,
                        )
                "
            >
                <SelectTrigger
                    class="w-48 border-white/10 bg-fl-graphite/60 text-white"
                >
                    <SelectValue placeholder="Todos los usuarios" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value=" ">Todos los usuarios</SelectItem>
                    <SelectItem
                        v-for="u in users"
                        :key="u.id"
                        :value="String(u.id)"
                    >
                        {{ u.name }}
                    </SelectItem>
                </SelectContent>
            </Select>

            <Select
                :model-value="filters.event || ' '"
                @update:model-value="
                    (v) => updateFilter('event', String(v ?? '').trim())
                "
            >
                <SelectTrigger
                    class="w-40 border-white/10 bg-fl-graphite/60 text-white"
                >
                    <SelectValue placeholder="Toda acción" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value=" ">Toda acción</SelectItem>
                    <SelectItem
                        v-for="e in events"
                        :key="e.value"
                        :value="e.value"
                    >
                        {{ e.label }}
                    </SelectItem>
                </SelectContent>
            </Select>

            <Select
                :model-value="filters.subject_type || ' '"
                @update:model-value="
                    (v) => updateFilter('subject_type', String(v ?? '').trim())
                "
            >
                <SelectTrigger
                    class="w-44 border-white/10 bg-fl-graphite/60 text-white"
                >
                    <SelectValue placeholder="Todo tipo" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value=" ">Todo tipo</SelectItem>
                    <SelectItem
                        v-for="t in subjectTypes"
                        :key="t.value"
                        :value="t.value"
                    >
                        {{ t.label }}
                    </SelectItem>
                </SelectContent>
            </Select>

            <Input
                type="date"
                :model-value="filters.from"
                class="w-40 border-white/10 bg-fl-graphite/60 text-white"
                @change="
                    (e: Event) =>
                        updateFilter(
                            'from',
                            (e.target as HTMLInputElement).value,
                        )
                "
            />
            <Input
                type="date"
                :model-value="filters.to"
                class="w-40 border-white/10 bg-fl-graphite/60 text-white"
                @change="
                    (e: Event) =>
                        updateFilter('to', (e.target as HTMLInputElement).value)
                "
            />
        </div>

        <AdminTable :columns="columns" :rows="activities">
            <template #cell-event="{ row }">
                <Badge
                    variant="outline"
                    :class="
                        eventBadgeClass[row.event as string] ??
                        'border-white/20 text-white/50'
                    "
                >
                    {{ row.event_label }}
                </Badge>
            </template>
            <template #cell-subject_type="{ row }">
                <span class="text-white/60">
                    {{ row.subject_type_label
                    }}<span v-if="row.subject_id" class="text-white/30">
                        #{{ row.subject_id }}</span
                    >
                </span>
            </template>
        </AdminTable>
    </div>
</template>
