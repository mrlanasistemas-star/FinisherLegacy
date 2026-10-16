<script setup lang="ts">
/**
 * Event workspace → Tracking (read-only). Who is being followed live,
 * last update, battery and a map overview of last known positions.
 * Refreshes softly every `pollSeconds` while the tab is visible.
 */
import { Head, router } from '@inertiajs/vue3';
import { BatteryLow, Link2, MapPin, Radio } from '@lucide/vue';
import { useDocumentVisibility, useIntervalFn } from '@vueuse/core';
import { computed, defineAsyncComponent, ref } from 'vue';
import Pagination from '@/components/public/Pagination.vue';
import type { PaginationLink } from '@/components/public/Pagination.vue';
import { timeAgo } from '@/lib/datetime';
import { formatKm } from '@/types/tracking';
import type { TrackingMapConfig } from '@/types/tracking';

type Row = {
    uuid: string;
    participant: string;
    bib_number: string | null;
    race: string | null;
    status: 'active' | 'stopped';
    source: 'mobile_gps' | 'gps_band';
    distance_m: number;
    battery: number | null;
    started_at: string;
    last_point_at: string | null;
    position: [number, number] | null;
};

const props = defineProps<{
    edition: { id: number; name: string; event: string | null };
    stats: { active: number; total: number; shares: number };
    sessions: { data: Row[]; links: PaginationLink[]; total: number };
    pollSeconds: number;
    map: TrackingMapConfig;
}>();

const LiveTrackingMap = defineAsyncComponent(
    () => import('@/components/tracking/LiveTrackingMap.vue'),
);

const now = ref(Date.now());
const visibility = useDocumentVisibility();

useIntervalFn(() => {
    now.value = Date.now();

    if (visibility.value === 'visible' && props.stats.active > 0) {
        router.reload({ only: ['stats', 'sessions'] });
    }
}, Math.max(15, props.pollSeconds) * 1000);

const markers = computed(() =>
    props.sessions.data
        .filter((r) => r.status === 'active' && r.position)
        .map((r) => ({
            id: r.uuid,
            lat: r.position![0],
            lng: r.position![1],
            label: r.bib_number
                ? `#${r.bib_number} · ${r.participant}`
                : r.participant,
        })),
);

function ago(iso: string | null): string {
    void now.value;

    return iso ? timeAgo(iso) : 'Sin datos';
}
</script>

<template>
    <Head :title="`Tracking — ${edition.name}`" />

    <div class="space-y-6 p-4 md:p-8">
        <div>
            <p class="text-xs tracking-wide text-muted-foreground/80 uppercase">
                {{ edition.event ?? 'Evento' }} · Seguimiento en vivo
            </p>
            <h1 class="text-xl font-bold text-foreground">
                {{ edition.name }}
            </h1>
        </div>

        <div class="grid grid-cols-3 gap-3">
            <div class="fl-card p-4">
                <p class="flex items-center gap-1.5 text-xs text-muted-foreground">
                    <Radio class="size-3.5" /> En vivo ahora
                </p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">
                    {{ stats.active }}
                </p>
            </div>
            <div class="fl-card p-4">
                <p class="flex items-center gap-1.5 text-xs text-muted-foreground">
                    <MapPin class="size-3.5" /> Seguimientos
                </p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">
                    {{ stats.total }}
                </p>
            </div>
            <div class="fl-card p-4">
                <p class="flex items-center gap-1.5 text-xs text-muted-foreground">
                    <Link2 class="size-3.5" /> Enlaces activos
                </p>
                <p class="mt-1 text-2xl font-semibold tabular-nums">
                    {{ stats.shares }}
                </p>
            </div>
        </div>

        <div
            v-if="markers.length"
            class="h-80 overflow-hidden rounded-xl border border-border md:h-96"
        >
            <LiveTrackingMap
                :tile-url="map.tile_url"
                :attribution="map.attribution"
                :markers="markers"
                :follow="false"
                label="Últimas posiciones de participantes en vivo"
            />
        </div>

        <div
            v-if="!sessions.data.length"
            class="rounded-xl border border-dashed border-border p-10 text-center text-sm text-muted-foreground"
        >
            Nadie ha iniciado seguimiento en vivo en este evento todavía. Los
            atletas lo activan desde la app el día de la carrera.
        </div>

        <template v-else>
            <!-- Desktop table -->
            <div class="hidden overflow-hidden rounded-xl border border-border md:block">
                <table class="w-full text-sm">
                    <thead class="bg-muted/40 text-left text-xs text-muted-foreground">
                        <tr>
                            <th class="px-4 py-2 font-medium">Participante</th>
                            <th class="px-4 py-2 font-medium">Distancia</th>
                            <th class="px-4 py-2 font-medium">Estado</th>
                            <th class="px-4 py-2 font-medium">Última actualización</th>
                            <th class="px-4 py-2 font-medium">Batería</th>
                            <th class="px-4 py-2 font-medium">Fuente</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="row in sessions.data" :key="row.uuid">
                            <td class="px-4 py-2.5">
                                <p class="font-medium text-foreground">
                                    {{ row.participant }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    <span v-if="row.bib_number">#{{ row.bib_number }}</span>
                                    <span v-if="row.race"> · {{ row.race }}</span>
                                </p>
                            </td>
                            <td class="px-4 py-2.5 tabular-nums">
                                {{ formatKm(row.distance_m) }}
                            </td>
                            <td class="px-4 py-2.5">
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs"
                                    :class="
                                        row.status === 'active'
                                            ? 'bg-emerald-500/10 text-emerald-800'
                                            : 'bg-muted text-muted-foreground'
                                    "
                                >
                                    {{ row.status === 'active' ? 'En vivo' : 'Terminado' }}
                                </span>
                            </td>
                            <td class="px-4 py-2.5 text-muted-foreground">
                                {{ ago(row.last_point_at) }}
                            </td>
                            <td class="px-4 py-2.5">
                                <span
                                    v-if="row.battery !== null"
                                    class="inline-flex items-center gap-1"
                                    :class="row.battery <= 20 ? 'text-amber-700' : ''"
                                >
                                    <BatteryLow v-if="row.battery <= 20" class="size-4" />
                                    {{ row.battery }}%
                                </span>
                                <span v-else class="text-muted-foreground">—</span>
                            </td>
                            <td class="px-4 py-2.5 text-muted-foreground">
                                {{ row.source === 'gps_band' ? 'Dispositivo' : 'Teléfono' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Phone cards -->
            <ul class="space-y-2 md:hidden">
                <li
                    v-for="row in sessions.data"
                    :key="row.uuid"
                    class="fl-card flex items-center justify-between gap-3 p-3"
                >
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium">
                            <span v-if="row.bib_number" class="text-muted-foreground">#{{ row.bib_number }} </span>{{ row.participant }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ formatKm(row.distance_m) }} · {{ ago(row.last_point_at) }}
                            <template v-if="row.battery !== null"> · {{ row.battery }}%</template>
                        </p>
                    </div>
                    <span
                        class="shrink-0 rounded-full px-2 py-0.5 text-xs"
                        :class="
                            row.status === 'active'
                                ? 'bg-emerald-500/10 text-emerald-800'
                                : 'bg-muted text-muted-foreground'
                        "
                    >
                        {{ row.status === 'active' ? 'En vivo' : 'Terminado' }}
                    </span>
                </li>
            </ul>

            <Pagination v-if="sessions.links.length > 3" :links="sessions.links" />
        </template>
    </div>
</template>
