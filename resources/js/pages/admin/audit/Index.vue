<script setup lang="ts">
/**
 * Auditoría — who did what, when, on which record. A day-grouped activity
 * timeline (not a table) with modern filters: people/action/type selects
 * and a date range with quick presets.
 */
import { Head, router } from '@inertiajs/vue3';
import {
    CalendarRange,
    Filter,
    History,
    Layers,
    Pencil,
    Plus,
    RotateCcw,
    Trash2,
    UserRound,
    Zap,
} from '@lucide/vue';
import { computed } from 'vue';
import SecondaryNav from '@/components/admin/SecondaryNav.vue';
import DatePicker from '@/components/DatePicker.vue';
import FancySelect from '@/components/forms/FancySelect.vue';
import Pagination from '@/components/public/Pagination.vue';
import { getInitials } from '@/composables/useInitials';
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
    created_at_iso: string;
};

const props = defineProps<{
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

const eventStyle: Record<
    string,
    { icon: typeof Plus; chip: string; dot: string }
> = {
    created: {
        icon: Plus,
        chip: 'bg-emerald-50 text-emerald-700 border-emerald-200',
        dot: 'bg-emerald-500',
    },
    updated: {
        icon: Pencil,
        chip: 'bg-amber-50 text-amber-700 border-amber-200',
        dot: 'bg-amber-500',
    },
    deleted: {
        icon: Trash2,
        chip: 'bg-red-50 text-red-700 border-red-200',
        dot: 'bg-red-500',
    },
};
const fallbackStyle = {
    icon: Zap,
    chip: 'bg-fl-cream text-fl-gold-ink border-fl-gold/30',
    dot: 'bg-fl-gold',
};

function update(next: Partial<typeof props.filters>) {
    router.get(
        '/admin/audit',
        Object.fromEntries(
            Object.entries({ ...props.filters, ...next }).filter(
                ([, value]) => value !== null && value !== '',
            ),
        ),
        { preserveState: true, replace: true, preserveScroll: true },
    );
}

const userOptions = computed(() => [
    { value: null, label: 'Todas las personas', icon: UserRound },
    ...props.users.map((u) => ({ value: u.id, label: u.name })),
]);
const eventOptions = computed(() => [
    { value: null, label: 'Toda acción', icon: Zap },
    ...props.events.map((e) => ({
        value: e.value,
        label: e.label,
        icon: eventStyle[e.value]?.icon,
    })),
]);
const typeOptions = computed(() => [
    { value: null, label: 'Todo tipo de registro', icon: Layers },
    ...props.subjectTypes.map((t) => ({ value: t.value, label: t.label })),
]);

const iso = (date: Date) => date.toISOString().slice(0, 10);

const presets = computed(() => {
    const now = new Date();
    const daysAgo = (n: number) => {
        const d = new Date(now);
        d.setDate(d.getDate() - n);

        return iso(d);
    };

    return [
        { label: 'Hoy', from: iso(now), to: iso(now) },
        { label: 'Últimos 7 días', from: daysAgo(6), to: iso(now) },
        { label: 'Últimos 30 días', from: daysAgo(29), to: iso(now) },
        {
            label: 'Este mes',
            from: iso(new Date(now.getFullYear(), now.getMonth(), 1)),
            to: iso(now),
        },
    ];
});

const hasFilters = computed(
    () =>
        !!(
            props.filters.user_id ||
            props.filters.event ||
            props.filters.subject_type ||
            props.filters.from ||
            props.filters.to
        ),
);

function resetFilters() {
    router.get('/admin/audit', {}, { preserveState: true, replace: true });
}

const groups = computed(() => {
    const map = new Map<string, ActivityRow[]>();

    for (const row of props.activities.data) {
        const key = row.created_at_iso.slice(0, 10);
        map.set(key, [...(map.get(key) ?? []), row]);
    }

    return [...map.entries()].map(([day, rows]) => ({
        day,
        label: new Date(`${day}T12:00:00`).toLocaleDateString('es-MX', {
            weekday: 'long',
            day: 'numeric',
            month: 'long',
            year: 'numeric',
        }),
        rows,
    }));
});

const time = (iso: string) =>
    new Date(iso).toLocaleTimeString('es-MX', {
        hour: '2-digit',
        minute: '2-digit',
    });
</script>

<template>
    <Head title="Auditoría" />

    <div class="w-full p-4 md:p-8">
        <SecondaryNav :items="SYSTEM_AREA_NAV" />

        <div class="mb-6 flex items-center gap-3">
            <span
                class="flex size-11 items-center justify-center rounded-xl bg-fl-cream text-fl-gold-ink"
            >
                <History class="size-5" />
            </span>
            <div>
                <h1 class="text-xl font-semibold">Auditoría</h1>
                <p class="text-sm text-muted-foreground">
                    Quién hizo qué, cuándo y sobre qué registro.
                </p>
            </div>
        </div>

        <!-- Filters -->
        <section class="fl-card mb-8 space-y-4 p-4 sm:p-5">
            <div class="flex items-center justify-between">
                <p class="flex items-center gap-2 text-sm font-semibold">
                    <Filter class="size-4 text-fl-gold-ink" /> Filtros
                </p>
                <button
                    v-if="hasFilters"
                    type="button"
                    class="inline-flex items-center gap-1.5 text-xs font-medium text-muted-foreground hover:text-foreground"
                    @click="resetFilters"
                >
                    <RotateCcw class="size-3.5" /> Limpiar
                </button>
            </div>
            <div class="grid gap-3 sm:grid-cols-3">
                <FancySelect
                    :model-value="filters.user_id"
                    :options="userOptions"
                    aria-label="Persona"
                    @update:model-value="
                        (v) => update({ user_id: v as number | null })
                    "
                />
                <FancySelect
                    :model-value="filters.event || null"
                    :options="eventOptions"
                    aria-label="Acción"
                    @update:model-value="
                        (v) => update({ event: (v as string) ?? '' })
                    "
                />
                <FancySelect
                    :model-value="filters.subject_type || null"
                    :options="typeOptions"
                    aria-label="Tipo de registro"
                    @update:model-value="
                        (v) => update({ subject_type: (v as string) ?? '' })
                    "
                />
            </div>
            <div class="grid items-center gap-3 sm:grid-cols-[1fr_auto_1fr]">
                <DatePicker
                    :model-value="filters.from || null"
                    :range-with="filters.to || null"
                    placeholder="Desde"
                    @update:model-value="(v) => update({ from: v ?? '' })"
                />
                <CalendarRange
                    class="mx-auto hidden size-4 text-muted-foreground sm:block"
                />
                <DatePicker
                    :model-value="filters.to || null"
                    :range-with="filters.from || null"
                    placeholder="Hasta"
                    @update:model-value="(v) => update({ to: v ?? '' })"
                />
            </div>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="preset in presets"
                    :key="preset.label"
                    type="button"
                    class="rounded-full border px-3 py-1.5 text-xs font-medium transition-colors"
                    :class="
                        filters.from === preset.from && filters.to === preset.to
                            ? 'border-foreground bg-foreground text-background'
                            : 'border-border hover:border-foreground/30'
                    "
                    @click="update({ from: preset.from, to: preset.to })"
                >
                    {{ preset.label }}
                </button>
            </div>
        </section>

        <!-- Timeline -->
        <div v-if="groups.length" class="space-y-8">
            <section v-for="group in groups" :key="group.day">
                <h2
                    class="sticky top-0 z-10 mb-3 bg-background/95 py-1 text-xs font-semibold tracking-[0.14em] text-muted-foreground uppercase backdrop-blur"
                >
                    {{ group.label }}
                </h2>
                <ol class="relative space-y-3 pl-6">
                    <span
                        class="absolute top-2 bottom-2 left-[7px] w-px bg-border"
                        aria-hidden="true"
                    />
                    <li
                        v-for="row in group.rows"
                        :key="row.id"
                        class="relative"
                    >
                        <span
                            class="absolute top-5 -left-[22px] size-2.5 rounded-full ring-4 ring-background"
                            :class="
                                (eventStyle[row.event] ?? fallbackStyle).dot
                            "
                            aria-hidden="true"
                        />
                        <article
                            class="fl-card flex items-start gap-3 p-4 transition-colors hover:border-foreground/20"
                        >
                            <span
                                class="flex size-9 shrink-0 items-center justify-center rounded-full bg-muted text-xs font-semibold text-foreground"
                                :title="row.causer"
                            >
                                {{ getInitials(row.causer) || '·' }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm leading-snug">
                                    {{ row.description }}
                                </p>
                                <div
                                    class="mt-2 flex flex-wrap items-center gap-2 text-xs"
                                >
                                    <span
                                        class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 font-medium"
                                        :class="
                                            (
                                                eventStyle[row.event] ??
                                                fallbackStyle
                                            ).chip
                                        "
                                    >
                                        <component
                                            :is="
                                                (
                                                    eventStyle[row.event] ??
                                                    fallbackStyle
                                                ).icon
                                            "
                                            class="size-3"
                                        />
                                        {{ row.event_label }}
                                    </span>
                                    <span
                                        class="inline-flex items-center gap-1 rounded-full bg-muted px-2 py-0.5 text-muted-foreground"
                                    >
                                        <Layers class="size-3" />
                                        {{ row.subject_type_label }}
                                        <template v-if="row.subject_id"
                                            >#{{ row.subject_id }}</template
                                        >
                                    </span>
                                    <span class="text-muted-foreground">
                                        {{ row.causer }} ·
                                        {{ time(row.created_at_iso) }}
                                    </span>
                                </div>
                            </div>
                        </article>
                    </li>
                </ol>
            </section>
        </div>
        <div
            v-else
            class="fl-card flex flex-col items-center gap-3 p-12 text-center"
        >
            <History class="size-8 text-fl-gold-ink" />
            <p class="font-semibold">Sin actividad para estos filtros</p>
            <p class="text-sm text-muted-foreground">
                Ajusta las fechas o limpia los filtros.
            </p>
        </div>

        <div class="mt-8">
            <Pagination :links="activities.links" />
        </div>
    </div>
</template>
