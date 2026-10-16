<script setup lang="ts">
/**
 * Event workspace › Resultados: official times and positions of this event,
 * ranked, filterable by race / status / name or bib. Read-only — results
 * arrive through the event's data source (Datos tab).
 */
import { Head, Link, router } from '@inertiajs/vue3';
import { Search, Trophy } from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import EventWorkspace from '@/components/admin/events/EventWorkspace.vue';
import type { EventWorkspaceData } from '@/components/admin/events/types';
import FancySelect from '@/components/forms/FancySelect.vue';
import Pagination from '@/components/public/Pagination.vue';
import { Badge } from '@/components/ui/badge';

type ResultRow = {
    id: number;
    participant_id: number;
    name: string | null;
    bib_number: string | null;
    race: string | null;
    status: string;
    official_time: string | null;
    chip_time: string | null;
    pace: string | null;
    overall_position: number | null;
    gender_position: number | null;
    category_position: number | null;
    verified: boolean;
};

const props = defineProps<{
    workspace: EventWorkspaceData;
    results: {
        data: ResultRow[];
        links: { url: string | null; label: string; active: boolean }[];
        total: number;
    };
    races: { id: number; name: string }[];
    statuses: string[];
    summary: {
        participants: number;
        with_result: number;
        without_result: number;
        finishers: number;
        dnf: number;
    };
    resultsStatus: string | null;
    filters: {
        q: string | null;
        event_race_id: number | null;
        status: string | null;
    };
}>();

const statusLabels: Record<string, string> = {
    pending: 'Pendiente',
    unofficial: 'Extraoficial',
    finished: 'Finalizó',
    verified: 'Verificado',
    dnf: 'No terminó',
    dns: 'No salió',
    dq: 'Descalificado',
};

const statusBadge: Record<string, string> = {
    verified: 'border-emerald-500/30 text-emerald-700',
    finished: 'border-emerald-500/30 text-emerald-700',
    unofficial: 'border-amber-500/30 text-amber-700',
    pending: 'border-foreground/15 text-muted-foreground',
    dnf: 'border-red-500/30 text-red-700',
    dns: 'border-red-500/30 text-red-700',
    dq: 'border-red-500/30 text-red-700',
};

const raceOptions = computed(() => [
    { value: null, label: 'Todas las carreras' },
    ...props.races.map((race) => ({ value: race.id, label: race.name })),
]);
const statusOptions = computed(() => [
    { value: null, label: 'Todos los estados' },
    ...props.statuses.map((status) => ({
        value: status,
        label: statusLabels[status] ?? status,
    })),
]);

const q = ref(props.filters.q ?? '');
const race = ref<string | number | null>(props.filters.event_race_id);
const status = ref<string | number | null>(props.filters.status);

function apply() {
    router.get(
        `${props.workspace.base_url}/results`,
        {
            q: q.value || undefined,
            event_race_id: race.value ?? undefined,
            status: status.value ?? undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

watch(
    q,
    useDebounceFn(() => apply(), 350),
);
watch([race, status], apply);

const summaryCards = computed(() => [
    { label: 'Con resultado', value: props.summary.with_result },
    { label: 'Finalizaron', value: props.summary.finishers },
    { label: 'No terminaron / no salieron', value: props.summary.dnf },
    { label: 'Sin resultado todavía', value: props.summary.without_result },
]);
</script>

<template>
    <Head :title="`Resultados — ${workspace.name}`" />

    <EventWorkspace :workspace="workspace" active="results">
        <div class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div
                v-for="card in summaryCards"
                :key="card.label"
                class="fl-card p-4"
            >
                <p class="text-2xl font-semibold text-foreground tabular-nums">
                    {{ card.value.toLocaleString('es-MX') }}
                </p>
                <p class="text-xs text-muted-foreground">{{ card.label }}</p>
            </div>
        </div>

        <div class="mb-4 flex flex-wrap items-center gap-2">
            <label class="relative w-full sm:w-64">
                <span class="sr-only">Buscar por nombre o dorsal</span>
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <input
                    v-model="q"
                    type="search"
                    placeholder="Nombre o dorsal…"
                    class="h-10 w-full rounded-lg border border-border bg-card pr-3 pl-9 text-sm text-foreground outline-none placeholder:text-muted-foreground/70 focus-visible:border-fl-gold"
                />
            </label>
            <FancySelect
                v-model="race"
                :options="raceOptions"
                aria-label="Carrera"
                class="w-full sm:w-52"
            />
            <FancySelect
                v-model="status"
                :options="statusOptions"
                aria-label="Estado del resultado"
                class="w-full sm:w-52"
            />
            <Link
                :href="`${workspace.base_url}/data`"
                class="ml-auto text-sm font-medium text-fl-gold-ink hover:underline"
            >
                ¿De dónde vienen los resultados?
            </Link>
        </div>

        <div
            v-if="results.data.length"
            class="overflow-x-auto rounded-xl border border-border bg-card"
        >
            <table class="w-full min-w-[720px] text-sm">
                <thead>
                    <tr
                        class="border-b border-border bg-muted/40 text-left text-xs text-muted-foreground"
                    >
                        <th class="px-4 py-2.5 font-medium">Pos.</th>
                        <th class="px-4 py-2.5 font-medium">Atleta</th>
                        <th class="px-4 py-2.5 font-medium">Carrera</th>
                        <th class="px-4 py-2.5 font-medium">Tiempo oficial</th>
                        <th
                            class="hidden px-4 py-2.5 font-medium lg:table-cell"
                        >
                            Tiempo chip
                        </th>
                        <th
                            class="hidden px-4 py-2.5 font-medium lg:table-cell"
                        >
                            Ritmo
                        </th>
                        <th
                            class="hidden px-4 py-2.5 font-medium xl:table-cell"
                        >
                            Rama / Cat.
                        </th>
                        <th class="px-4 py-2.5 font-medium">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in results.data"
                        :key="row.id"
                        class="border-b border-border last:border-0 hover:bg-foreground/[0.02]"
                    >
                        <td
                            class="px-4 py-2.5 font-semibold text-foreground tabular-nums"
                        >
                            {{ row.overall_position ?? '—' }}
                        </td>
                        <td class="px-4 py-2.5">
                            <Link
                                :href="`/admin/participants/${row.participant_id}`"
                                class="font-medium text-foreground hover:underline"
                                >{{ row.name ?? 'Sin nombre' }}</Link
                            >
                            <span
                                v-if="row.bib_number"
                                class="ml-2 font-mono text-xs text-fl-gold-ink"
                                >#{{ row.bib_number }}</span
                            >
                        </td>
                        <td class="px-4 py-2.5 text-muted-foreground">
                            {{ row.race ?? '—' }}
                        </td>
                        <td
                            class="px-4 py-2.5 font-mono text-foreground tabular-nums"
                        >
                            {{ row.official_time ?? '—' }}
                        </td>
                        <td
                            class="hidden px-4 py-2.5 font-mono text-muted-foreground tabular-nums lg:table-cell"
                        >
                            {{ row.chip_time ?? '—' }}
                        </td>
                        <td
                            class="hidden px-4 py-2.5 text-muted-foreground lg:table-cell"
                        >
                            {{ row.pace ?? '—' }}
                        </td>
                        <td
                            class="hidden px-4 py-2.5 text-muted-foreground tabular-nums xl:table-cell"
                        >
                            {{ row.gender_position ?? '—' }} /
                            {{ row.category_position ?? '—' }}
                        </td>
                        <td class="px-4 py-2.5">
                            <Badge
                                variant="outline"
                                :class="
                                    statusBadge[row.status] ??
                                    statusBadge.pending
                                "
                            >
                                {{ statusLabels[row.status] ?? row.status }}
                            </Badge>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            v-else
            class="flex flex-col items-center gap-3 rounded-2xl border border-dashed border-border py-16 text-center text-muted-foreground"
        >
            <Trophy class="size-8" />
            <p v-if="summary.with_result === 0">
                Este evento todavía no tiene resultados.
            </p>
            <p v-else>Sin resultados para estos filtros.</p>
            <Link
                v-if="summary.with_result === 0"
                :href="`${workspace.base_url}/data`"
                class="text-sm font-medium text-fl-gold-ink hover:underline"
            >
                Revisar la fuente de datos
            </Link>
        </div>

        <div class="mt-4">
            <Pagination :links="results.links" />
        </div>
    </EventWorkspace>
</template>
