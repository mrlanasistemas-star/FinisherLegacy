<script setup lang="ts">
/**
 * "Tus entrenamientos" on /mi-entrenamiento — a read-only list of the
 * athlete's latest saved workouts and this month's totals. Same data the
 * app writes through /api/v1/training/workouts (Laravel is the single
 * source of truth). Workouts are started from the app only (GPS + timer
 * live on the phone); manual entries are labeled as such.
 */
import { Footprints } from '@lucide/vue';
import { activeRegional, useI18n } from '@/i18n';
import { formatDuration, shortDate } from '@/lib/datetime';

export type WorkoutRow = {
    uuid: string;
    source: 'live' | 'manual';
    sport: { name: string; slug: string; metric: 'pace' | 'speed' } | null;
    uses_gps: boolean;
    started_at: string;
    duration_s: number;
    distance_m: number | null;
    avg_pace_s_per_km: number | null;
    avg_speed_mps: number | null;
};

defineProps<{
    workouts: WorkoutRow[];
    month: { workouts: number; duration_s: number; distance_m: number };
}>();

const { t, tc } = useI18n();

function km(meters: number): string {
    return (meters / 1000).toLocaleString(activeRegional(), {
        maximumFractionDigits: meters >= 100_000 ? 0 : 2,
    });
}

/** Pace for running-type sports, speed for cycling — and only when the server computed one. */
function rhythm(row: WorkoutRow): string | null {
    if (row.sport?.metric === 'speed' && row.avg_speed_mps !== null) {
        return t('{value} km/h', {
            value: (row.avg_speed_mps * 3.6).toLocaleString(activeRegional(), {
                maximumFractionDigits: 1,
            }),
        });
    }

    if (row.avg_pace_s_per_km === null) {
        return null;
    }

    const m = Math.floor(row.avg_pace_s_per_km / 60);
    const s = String(row.avg_pace_s_per_km % 60).padStart(2, '0');

    return t('{value} /km', { value: `${m}:${s}` });
}
</script>

<template>
    <section class="fl-card mt-8 p-5 sm:p-7">
        <div
            class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between"
        >
            <h2 class="font-heading text-xl font-semibold">
                {{ t('Tus entrenamientos') }}
            </h2>
            <p
                v-if="month.workouts > 0"
                class="text-sm text-muted-foreground tabular-nums"
            >
                {{
                    tc(
                        'Este mes: {count} entrenamiento|Este mes: {count} entrenamientos',
                        month.workouts,
                    )
                }}
                · {{ formatDuration(month.duration_s) }}
                <template v-if="month.distance_m > 0">
                    · {{ km(month.distance_m) }} km
                </template>
            </p>
        </div>

        <ul v-if="workouts.length" class="mt-4 divide-y divide-border">
            <li
                v-for="row in workouts"
                :key="row.uuid"
                class="flex items-center gap-3 py-3"
            >
                <span
                    class="flex size-10 shrink-0 items-center justify-center rounded-full bg-fl-line/60"
                    aria-hidden="true"
                >
                    <Footprints class="size-5" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="truncate font-semibold">
                        {{ row.sport?.name ?? t('Entrenamiento') }}
                        <span
                            class="ml-2 rounded-full border border-border px-2 py-0.5 align-middle text-[11px] font-medium text-muted-foreground"
                        >
                            {{
                                row.source === 'manual'
                                    ? t('Manual')
                                    : t('En vivo')
                            }}
                        </span>
                    </p>
                    <p class="text-sm text-muted-foreground">
                        {{ shortDate(row.started_at) }}
                    </p>
                </div>
                <div class="text-right text-sm tabular-nums">
                    <p class="font-semibold">
                        {{ formatDuration(row.duration_s) }}
                    </p>
                    <p class="text-muted-foreground">
                        <template v-if="row.distance_m">
                            {{ km(row.distance_m) }} km
                        </template>
                        <template v-if="row.distance_m && rhythm(row)">
                            ·
                        </template>
                        {{ rhythm(row) ?? '' }}
                    </p>
                </div>
            </li>
        </ul>
        <p v-else class="mt-4 text-sm text-muted-foreground">
            {{
                t(
                    'Aún no tienes entrenamientos guardados. Empieza uno desde la app.',
                )
            }}
        </p>
    </section>
</template>
