<script setup lang="ts">
/**
 * The race "ficha" of a Legacy story: who ran, with which bib, and every
 * figure that has a value — time, pace, overall / category / gender place,
 * distance, elevation, sport, date and venue. Nothing is rendered for an
 * empty field, and the whole block disappears when there is nothing to
 * show. One discreet "Resultados oficiales" mark appears when any shown
 * value comes from the event's official results.
 */
import { BadgeCheck } from '@lucide/vue';
import { computed } from 'vue';
import type { StoryRaceExtended } from './types';

const props = defineProps<{
    race: StoryRaceExtended;
    /** Already formatted date (formatStoryDate). */
    date: string | null;
}>();

type Stat = { key: string; label: string; value: string; note?: string };

function text(value: string | number | null | undefined): string | null {
    if (value === null || value === undefined) {
        return null;
    }

    const s = String(value).trim();

    return s === '' ? null : s;
}

/** "12" → "12.º"; anything already worded ("3 de 240") stays as typed. */
function place(value: string | null): string | null {
    return value !== null && /^\d+$/.test(value) ? `${value}.º` : value;
}

const name = computed(() => text(props.race.athlete_name));
const bib = computed(() => {
    const value = text(props.race.bib_number);

    return value === null ? null : `#${value.replace(/^#/, '')}`;
});
const status = computed(() =>
    props.race.finish_status && props.race.finish_status !== 'finished'
        ? text(props.race.finish_status_label)
        : null,
);

const stats = computed<Stat[]>(() => {
    const r = props.race;
    const elevation =
        typeof r.elevation_gain_m === 'number' && r.elevation_gain_m > 0
            ? `+${r.elevation_gain_m.toLocaleString('es-MX')} m`
            : null;
    const categoryPlace = place(text(r.category_position));
    const category = text(r.category);

    const rows: Array<Omit<Stat, 'value'> & { value: string | null }> = [
        { key: 'result_time', label: 'Tiempo', value: text(r.time) },
        {
            key: 'pace',
            label: 'Ritmo',
            value: text(r.pace) ?? text(r.suggested_pace),
        },
        {
            key: 'result_position',
            label: 'Lugar general',
            value: place(text(r.position)),
        },
        category !== null
            ? {
                  key: 'category',
                  label: 'Categoría',
                  value: category,
                  note:
                      categoryPlace !== null
                          ? `Lugar ${categoryPlace}`
                          : undefined,
              }
            : {
                  key: 'category_position',
                  label: 'Lugar en categoría',
                  value: categoryPlace,
              },
        {
            key: 'gender_position',
            label: 'Lugar por rama',
            value: place(text(r.gender_position)),
        },
        { key: 'race', label: 'Carrera', value: text(r.race) },
        { key: 'distance', label: 'Distancia', value: text(r.distance) },
        { key: 'elevation_gain_m', label: 'Desnivel', value: elevation },
        { key: 'sport', label: 'Deporte', value: text(r.sport) },
        { key: 'race_date', label: 'Fecha', value: props.date },
        { key: 'location', label: 'Lugar', value: text(r.location) },
    ];

    return rows.filter((row): row is Stat => row.value !== null);
});

const hasOfficial = computed(() => {
    const official = props.race.official ?? {};
    const shown = new Set(stats.value.map((s) => s.key));

    if (bib.value !== null) {
        shown.add('bib_number');
    }

    if (status.value !== null) {
        shown.add('finish_status');
    }

    // "Categoría" also carries its place.
    if (shown.has('category')) {
        shown.add('category_position');
    }

    // Payloads without the map: the old rule (a linked participation).
    if (props.race.official === undefined) {
        return props.race.source === 'participation';
    }

    return [...shown].some((key) => official[key] === true);
});

const visible = computed(
    () =>
        stats.value.length > 0 ||
        name.value !== null ||
        bib.value !== null ||
        status.value !== null,
);
</script>

<template>
    <section
        v-if="visible"
        class="border-t border-foreground/10 py-10"
        aria-label="Datos de la carrera"
    >
        <div
            v-if="name || bib || status"
            class="mb-8 flex flex-wrap items-center gap-x-4 gap-y-3"
        >
            <p
                v-if="name"
                class="min-w-0 font-serif text-2xl leading-tight text-balance text-foreground sm:text-3xl"
            >
                {{ name }}
            </p>
            <span
                v-if="bib"
                class="legacy-numeric inline-flex items-center rounded-lg border border-fl-gold/40 bg-white px-3 py-1 text-sm font-semibold tracking-wide text-foreground shadow-fl-micro"
            >
                <span class="sr-only">Número de corredor </span>{{ bib }}
            </span>
            <span
                v-if="status"
                class="inline-flex items-center rounded-full border border-foreground/15 px-3 py-1 text-xs font-medium text-muted-foreground"
            >
                {{ status }}
            </span>
        </div>

        <dl
            v-if="stats.length"
            class="grid grid-cols-2 gap-x-6 gap-y-7 sm:grid-cols-3"
        >
            <div v-for="stat in stats" :key="stat.key" class="min-w-0">
                <dt
                    class="text-[11px] font-semibold tracking-[0.2em] text-fl-gold-ink uppercase"
                >
                    {{ stat.label }}
                </dt>
                <dd
                    class="legacy-numeric mt-1.5 font-serif text-lg leading-snug break-words text-foreground sm:text-xl"
                >
                    {{ stat.value }}
                    <span
                        v-if="stat.note"
                        class="mt-0.5 block font-sans text-xs font-normal text-muted-foreground"
                    >
                        {{ stat.note }}
                    </span>
                </dd>
            </div>
        </dl>

        <p
            v-if="hasOfficial"
            class="mt-7 inline-flex items-center gap-1.5 text-xs text-muted-foreground"
        >
            <BadgeCheck class="size-4 text-fl-gold-ink" aria-hidden="true" />
            Resultados oficiales del evento
        </p>
    </section>
</template>
