<script setup lang="ts">
/**
 * Eventos — entry of the events workspace (spec §9). One row per event
 * edition; opening it lands on its workspace (Resumen, Participantes,
 * Resultados, Prerregistros, Datos, Tracking, Media).
 */
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { CalendarX2, ChevronRight, Plus, Search } from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import Pagination from '@/components/public/Pagination.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    eventEditionPhase,
    statusClass,
    statusLabel,
} from '@/lib/statusLabels';

type Row = {
    id: number;
    event: string;
    edition: string;
    event_date: string;
    city: string;
    status: string;
    phase: string;
    participants_count: number;
    preregistrations_count: number;
};

const props = defineProps<{
    editions: {
        data: Row[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: { q: string; phase: string | null };
}>();

const page = usePage();
const canCreate = computed(() =>
    (page.props.auth?.permissions ?? []).includes('events.manage'),
);

const phases = [
    { value: null, label: 'Todos' },
    { value: 'upcoming', label: 'Próximos' },
    { value: 'ongoing', label: 'Hoy' },
    { value: 'finished', label: 'Finalizados' },
] as const;

const q = ref(props.filters.q ?? '');

function visit(overrides: Record<string, string | null | undefined> = {}) {
    router.get(
        '/admin/events',
        {
            q: q.value || undefined,
            phase: props.filters.phase ?? undefined,
            ...overrides,
        },
        { preserveState: true, replace: true },
    );
}

watch(
    q,
    useDebounceFn(() => visit(), 350),
);

function dateParts(date: string) {
    const d = new Date(`${date}T12:00:00`);

    return {
        day: d.toLocaleDateString('es-MX', { day: '2-digit' }),
        month: d
            .toLocaleDateString('es-MX', { month: 'short' })
            .replace('.', ''),
        year: d.getFullYear(),
    };
}
</script>

<template>
    <Head title="Eventos" />

    <div class="w-full px-4 py-6 md:px-6 md:py-8 xl:px-8">
        <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="fl-eyebrow text-fl-gold-ink">Eventos</p>
                <h1
                    class="mt-1 font-serif text-2xl font-semibold text-foreground md:text-3xl"
                >
                    Cada evento, en un solo lugar
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Participantes, resultados, prerregistros, datos y fotos
                    viven dentro de cada evento.
                </p>
            </div>
            <Button
                v-if="canCreate"
                as-child
                class="bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
            >
                <Link href="/admin/events/create">
                    <Plus class="size-4" />
                    Nuevo evento
                </Link>
            </Button>
        </div>

        <div class="mb-4 flex flex-wrap items-center gap-3">
            <label class="relative w-full sm:w-72">
                <span class="sr-only">Buscar evento</span>
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <input
                    v-model="q"
                    type="search"
                    placeholder="Buscar evento…"
                    class="h-10 w-full rounded-lg border border-border bg-card pr-3 pl-9 text-sm text-foreground outline-none placeholder:text-muted-foreground/70 focus-visible:border-fl-gold"
                />
            </label>
            <div
                class="flex gap-1 overflow-x-auto rounded-full border border-border bg-card p-1"
                role="group"
                aria-label="Filtrar por fecha"
            >
                <button
                    v-for="phase in phases"
                    :key="phase.label"
                    type="button"
                    class="min-h-9 shrink-0 rounded-full px-4 text-sm font-medium transition-colors"
                    :class="
                        (filters.phase ?? null) === phase.value
                            ? 'bg-foreground text-background'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    :aria-pressed="(filters.phase ?? null) === phase.value"
                    @click="visit({ phase: phase.value ?? undefined })"
                >
                    {{ phase.label }}
                </button>
            </div>
        </div>

        <ul class="grid gap-2">
            <li v-for="row in editions.data" :key="row.id">
                <Link
                    :href="`/admin/events/${row.id}`"
                    class="fl-card group flex items-center gap-4 p-3 transition-colors hover:border-foreground/20 sm:p-4"
                >
                    <span
                        class="flex w-14 shrink-0 flex-col items-center rounded-lg border border-border bg-background py-1.5 text-center leading-none"
                    >
                        <span
                            class="text-[10px] font-semibold text-fl-gold-ink uppercase"
                            >{{ dateParts(row.event_date).month }}</span
                        >
                        <span
                            class="mt-1 text-xl font-semibold text-foreground tabular-nums"
                            >{{ dateParts(row.event_date).day }}</span
                        >
                        <span class="mt-1 text-[10px] text-muted-foreground">{{
                            dateParts(row.event_date).year
                        }}</span>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span
                            class="block truncate font-semibold text-foreground"
                            >{{ row.event }}</span
                        >
                        <span
                            class="block truncate text-sm text-muted-foreground"
                            >{{ row.edition }} · {{ row.city }}</span
                        >
                    </span>
                    <span
                        class="hidden gap-6 text-right text-sm md:flex"
                        aria-hidden="false"
                    >
                        <span>
                            <span
                                class="block font-semibold text-foreground tabular-nums"
                                >{{
                                    row.participants_count.toLocaleString(
                                        'es-MX',
                                    )
                                }}</span
                            >
                            <span class="text-xs text-muted-foreground"
                                >Participantes</span
                            >
                        </span>
                        <span>
                            <span
                                class="block font-semibold text-foreground tabular-nums"
                                >{{
                                    row.preregistrations_count.toLocaleString(
                                        'es-MX',
                                    )
                                }}</span
                            >
                            <span class="text-xs text-muted-foreground"
                                >Prerregistros</span
                            >
                        </span>
                    </span>
                    <Badge
                        variant="outline"
                        :class="statusClass(eventEditionPhase, row.phase)"
                        class="hidden sm:inline-flex"
                    >
                        {{ statusLabel(eventEditionPhase, row.phase) }}
                    </Badge>
                    <ChevronRight
                        class="size-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5"
                    />
                </Link>
            </li>
        </ul>

        <div
            v-if="!editions.data.length"
            class="flex flex-col items-center gap-3 rounded-2xl border border-dashed border-border py-16 text-center text-muted-foreground"
        >
            <CalendarX2 class="size-8" />
            <p>No hay eventos con estos filtros.</p>
        </div>

        <div class="mt-4">
            <Pagination :links="editions.links" />
        </div>
    </div>
</template>
