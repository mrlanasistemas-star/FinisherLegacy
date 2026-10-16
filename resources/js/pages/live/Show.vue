<script setup lang="ts">
/**
 * `/live/{token}` — the family's live page (SPEC §81-§83). No account,
 * mobile-first, no technical data and no coordinates in text. Realtime via
 * getEcho() on `live.{channel}` → `.tracking.point`; when realtime is off
 * or goes quiet, a soft Inertia partial reload every `pollSeconds`.
 */
import { Head, Link, router } from '@inertiajs/vue3';
import {
    BatteryFull,
    BatteryLow,
    BatteryMedium,
    Crosshair,
    Heart,
    Mic,
} from '@lucide/vue';
import { useDocumentVisibility, useIntervalFn } from '@vueuse/core';
import {
    computed,
    defineAsyncComponent,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import FinisherLegacyLogo from '@/components/public/FinisherLegacyLogo.vue';
import { timeAgo } from '@/lib/datetime';
import { getEcho } from '@/lib/echo';
import type { RealtimeClient } from '@/lib/echo';
import { formatKm } from '@/types/tracking';
import type {
    LiveSnapshot,
    TrackingMapConfig,
    TrackingPointPayload,
} from '@/types/tracking';

defineOptions({ layout: [] });

const props = defineProps<{
    available: boolean;
    snapshot: LiveSnapshot | null;
    pollSeconds: number;
    map?: TrackingMapConfig;
}>();

const LiveTrackingMap = defineAsyncComponent(
    () => import('@/components/tracking/LiveTrackingMap.vue'),
);

const live = ref<LiveSnapshot | null>(
    props.snapshot ? structuredClone(props.snapshot) : null,
);

watch(
    () => props.snapshot,
    (fresh) => {
        live.value = fresh ? structuredClone(fresh) : null;
    },
);

// Ticks so "hace 2 min" stays honest without any request.
const now = ref(Date.now());
useIntervalFn(() => (now.value = Date.now()), 15_000);

const lastUpdate = computed(() => {
    void now.value;
    const at = live.value?.last_point?.recorded_at;

    return at ? timeAgo(at) : null;
});

const isStale = computed(() => {
    const at = live.value?.last_point?.recorded_at;

    return (
        live.value?.status === 'active' &&
        !!at &&
        now.value - new Date(at).getTime() > 5 * 60_000
    );
});

const progressPct = computed(() =>
    live.value?.progress != null ? Math.round(live.value.progress * 100) : null,
);

const statusLabel = computed(() => {
    switch (live.value?.status) {
        case 'active':
            return isStale.value ? 'Sin señal reciente' : 'En vivo';
        case 'stopped':
            return 'Seguimiento terminado';
        default:
            return 'Aún no comienza';
    }
});

const batteryIcon = computed(() => {
    const b = live.value?.battery ?? 100;

    return b <= 20 ? BatteryLow : b <= 60 ? BatteryMedium : BatteryFull;
});

const firstName = computed(
    () => live.value?.athlete.display_name.split(' ')[0] ?? '',
);

// --- Realtime + soft polling -------------------------------------------
let echo: RealtimeClient | null = null;
let lastRealtimeAt = 0;
const visibility = useDocumentVisibility();

function applyPoint(p: TrackingPointPayload): void {
    lastRealtimeAt = Date.now();

    if (!live.value) {
        return;
    }

    live.value.status = 'active';
    live.value.last_point = {
        lat: p.lat,
        lng: p.lng,
        recorded_at: p.recorded_at,
    };
    live.value.distance_m = p.distance_m;
    live.value.battery = p.battery;
    live.value.progress = live.value.race_distance_m
        ? Math.min(1, p.distance_m / live.value.race_distance_m)
        : null;
    live.value.path = [
        ...live.value.path,
        [Number(p.lat.toFixed(5)), Number(p.lng.toFixed(5))],
    ];
}

function refresh(): void {
    router.reload({ only: ['snapshot', 'available'], async: true });
}

const { pause, resume } = useIntervalFn(
    () => {
        if (
            visibility.value !== 'visible' ||
            live.value?.status === 'stopped'
        ) {
            return;
        }

        // With realtime working, only poll when it has gone quiet.
        const quietFor = Date.now() - lastRealtimeAt;

        if (!echo || quietFor > Math.max(45_000, props.pollSeconds * 2000)) {
            refresh();
        }
    },
    Math.max(15, props.pollSeconds || 20) * 1000,
    { immediate: false },
);

onMounted(() => {
    if (!props.available || !live.value) {
        return;
    }

    echo = getEcho();
    echo?.channel(live.value.channel).listen('.tracking.point', applyPoint);
    lastRealtimeAt = Date.now();
    resume();
});

watch(visibility, (v) => {
    if (v === 'visible' && props.available) {
        refresh();
    }
});

onBeforeUnmount(() => {
    pause();

    if (echo && live.value) {
        echo.leave(live.value.channel);
    }
});

const mapRef = ref<{ recenter: () => void } | null>(null);

const mapConfig = computed<TrackingMapConfig>(
    () =>
        props.map ?? {
            tile_url: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
            attribution: '&copy; OpenStreetMap',
        },
);
</script>

<template>
    <Head
        :title="
            live ? `Sigue a ${firstName} en vivo` : 'Seguimiento no disponible'
        "
    >
        <meta name="robots" content="noindex, nofollow" />
        <meta name="referrer" content="no-referrer" />
    </Head>

    <div class="min-h-dvh bg-fl-paper text-foreground">
        <header class="fl-container flex h-14 items-center justify-between">
            <FinisherLegacyLogo variant="wordmark" size="sm" />
            <span
                v-if="live"
                class="inline-flex items-center gap-2 rounded-full border border-border bg-white px-3 py-1 text-xs font-medium"
                role="status"
            >
                <span
                    class="size-2 rounded-full"
                    :class="
                        live.status === 'active' && !isStale
                            ? 'animate-pulse bg-emerald-600 motion-reduce:animate-none'
                            : live.status === 'active'
                              ? 'bg-amber-500'
                              : 'bg-muted-foreground/50'
                    "
                    aria-hidden="true"
                />
                {{ statusLabel }}
            </span>
        </header>

        <!-- Link no longer valid -->
        <main
            v-if="!available || !live"
            class="fl-container grid min-h-[70dvh] place-items-center py-16 text-center"
        >
            <div class="max-w-md">
                <p class="fl-eyebrow">Seguimiento en vivo</p>
                <h1 class="fl-display mt-3 text-4xl">
                    Este enlace ya no está activo
                </h1>
                <p class="mt-4 text-muted-foreground">
                    Puede que haya vencido o que tu atleta lo haya desactivado.
                    Pídele que te comparta uno nuevo desde su evento en Finisher
                    Legacy.
                </p>
                <Link
                    href="/"
                    class="mt-8 inline-flex min-h-11 items-center rounded-full bg-fl-black px-6 text-sm font-medium text-white hover:bg-fl-graphite focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:ring-offset-2 focus-visible:outline-none"
                >
                    Conocer Finisher Legacy
                </Link>
            </div>
        </main>

        <main v-else class="pb-16">
            <!-- Who / what -->
            <section class="fl-container pt-4 pb-5 sm:pt-8">
                <div class="flex items-center gap-4">
                    <img
                        v-if="live.athlete.avatar_url"
                        :src="live.athlete.avatar_url"
                        :alt="`Foto de ${live.athlete.display_name}`"
                        class="size-14 shrink-0 rounded-full object-cover ring-2 ring-white"
                        loading="lazy"
                    />
                    <div
                        v-else
                        class="grid size-14 shrink-0 place-items-center rounded-full bg-fl-cream font-serif text-xl text-fl-gold-ink ring-2 ring-white"
                        aria-hidden="true"
                    >
                        {{ firstName.charAt(0) }}
                    </div>
                    <div class="min-w-0">
                        <p class="fl-eyebrow truncate">
                            {{ live.event.name ?? 'En carrera'
                            }}<template v-if="live.event.race">
                                · {{ live.event.race }}</template
                            >
                        </p>
                        <h1 class="fl-display mt-1 text-3xl sm:text-4xl">
                            {{ live.athlete.display_name }}
                        </h1>
                    </div>
                </div>
            </section>

            <!-- Map -->
            <section class="sm:mx-auto sm:max-w-7xl sm:px-6 lg:px-8">
                <div
                    class="relative h-[52dvh] min-h-72 overflow-hidden border-y border-border sm:h-[460px] sm:rounded-2xl sm:border"
                >
                    <LiveTrackingMap
                        ref="mapRef"
                        :tile-url="mapConfig.tile_url"
                        :attribution="mapConfig.attribution"
                        :path="live.path"
                        :current="live.last_point"
                        :label="`Recorrido de ${firstName}`"
                    />
                    <button
                        v-if="live.last_point"
                        type="button"
                        class="absolute right-3 bottom-8 z-[500] grid size-11 place-items-center rounded-full border border-border bg-white shadow-sm hover:bg-fl-cream focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:outline-none"
                        aria-label="Centrar en la posición actual"
                        @click="mapRef?.recenter()"
                    >
                        <Crosshair class="size-5" />
                    </button>
                    <div
                        v-if="live.status === 'waiting'"
                        class="absolute inset-x-4 top-4 z-[500] rounded-xl bg-white/95 p-4 text-sm shadow-sm"
                    >
                        Cuando {{ firstName }} inicie el seguimiento desde su
                        teléfono, aquí verás su avance en tiempo real.
                    </div>
                </div>
            </section>

            <!-- Numbers -->
            <section
                class="fl-container mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4"
            >
                <div class="fl-card col-span-2 p-4 sm:col-span-2">
                    <p class="text-xs text-muted-foreground">Recorrido</p>
                    <p class="mt-1 font-serif text-4xl tabular-nums">
                        {{ formatKm(live.distance_m) }}
                    </p>
                    <template
                        v-if="progressPct !== null && live.race_distance_m"
                    >
                        <div
                            class="mt-3 h-2 overflow-hidden rounded-full bg-fl-cream"
                            role="progressbar"
                            :aria-valuenow="progressPct"
                            aria-valuemin="0"
                            aria-valuemax="100"
                            :aria-label="`Avance: ${progressPct}%`"
                        >
                            <div
                                class="h-full rounded-full bg-fl-gold transition-[width] duration-700 motion-reduce:transition-none"
                                :style="{ width: `${progressPct}%` }"
                            />
                        </div>
                        <p class="mt-2 text-xs text-muted-foreground">
                            {{ progressPct }}% de
                            {{ formatKm(live.race_distance_m) }}
                        </p>
                    </template>
                </div>
                <div class="fl-card p-4">
                    <p class="text-xs text-muted-foreground">
                        Última actualización
                    </p>
                    <p class="mt-1 text-lg font-medium">
                        {{ lastUpdate ?? 'Todavía no' }}
                    </p>
                    <p v-if="isStale" class="mt-1 text-xs text-amber-700">
                        Puede estar en una zona sin señal.
                    </p>
                </div>
                <div class="fl-card p-4">
                    <p class="text-xs text-muted-foreground">
                        Batería del teléfono
                    </p>
                    <p
                        v-if="live.battery !== null"
                        class="mt-1 flex items-center gap-2 text-lg font-medium"
                        :class="live.battery <= 20 ? 'text-amber-700' : ''"
                    >
                        <component :is="batteryIcon" class="size-5" />
                        {{ live.battery }}%
                    </p>
                    <p v-else class="mt-1 text-lg font-medium">—</p>
                </div>
            </section>

            <!-- Support -->
            <section class="fl-container mt-8">
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <p class="fl-eyebrow">Su equipo de apoyo</p>
                        <h2 class="fl-display mt-2 text-2xl">
                            Tu gente también corre contigo
                        </h2>
                    </div>
                </div>

                <ul
                    v-if="live.support.messages.length"
                    class="mt-4 grid gap-3 sm:grid-cols-2"
                >
                    <li
                        v-for="message in live.support.messages"
                        :key="message.id"
                        class="fl-card flex gap-3 p-4"
                    >
                        <div
                            class="grid size-9 shrink-0 place-items-center rounded-full bg-fl-cream text-fl-gold-ink"
                            aria-hidden="true"
                        >
                            <Mic
                                v-if="message.type === 'audio'"
                                class="size-4"
                            />
                            <Heart v-else class="size-4" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-medium">
                                {{ message.from }}
                                <span
                                    v-if="message.delivered"
                                    class="ml-1 text-xs font-normal text-muted-foreground"
                                    >· ya le llegó</span
                                >
                            </p>
                            <p
                                v-if="message.text"
                                class="mt-0.5 text-sm break-words text-muted-foreground"
                            >
                                “{{ message.text }}”
                            </p>
                            <p
                                v-else
                                class="mt-0.5 text-sm text-muted-foreground"
                            >
                                Le mandó un mensaje de voz.
                            </p>
                        </div>
                    </li>
                </ul>
                <p v-else class="mt-4 text-sm text-muted-foreground">
                    Todavía no hay mensajes. Sé la primera persona en mandarle
                    ánimo.
                </p>

                <a
                    v-if="live.support.url"
                    :href="live.support.url"
                    class="mt-6 inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-full bg-fl-black px-6 text-sm font-medium text-white hover:bg-fl-graphite focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:ring-offset-2 focus-visible:outline-none sm:w-auto"
                >
                    <Heart class="size-4" />
                    Enviar apoyo a {{ firstName }}
                </a>
            </section>

            <footer class="fl-container mt-12 text-xs text-muted-foreground">
                Solo quien tiene este enlace puede ver el seguimiento. Finisher
                Legacy · Tu historia. Tu legado.
            </footer>
        </main>
    </div>
</template>
