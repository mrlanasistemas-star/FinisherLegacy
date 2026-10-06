<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { CalendarClock, QrCode, Search, Zap } from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import {
    generateQuickPlate,
    previewPlate as previewAction,
    search as searchAction,
    selectEvent,
    showParticipant,
} from '@/actions/App/Http/Controllers/OperatorController';
import HelpPopover from '@/components/HelpPopover.vue';
import Reveal from '@/components/motion/Reveal.vue';
import PlatePreviewCard from '@/components/plates/PlatePreviewCard.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import type { PlateFace, PlateRenderMode } from '@/types/plate-studio';

type SearchResult = {
    id: number;
    bib_number: string | null;
    full_name: string;
    race: string | null;
    has_result: boolean;
    has_plate: boolean;
};

type EventOpsDashboard = {
    provider: {
        connected: boolean;
        provider_name: string | null;
        last_sync_at: string | null;
        stale: boolean;
    };
    data: { participants: number; results: number; conflicts: number };
    production: Record<
        'pending' | 'assigned' | 'engraving' | 'ready' | 'delivered' | 'failed',
        number
    >;
    stations: {
        id: number;
        name: string;
        online: boolean;
        current_job: string | null;
    }[];
    readiness: {
        ready: boolean;
        checks: Record<string, boolean>;
        blocking_reasons: string[];
    };
    metrics: {
        queue_wait: number | null;
        front_duration: number | null;
        flip_delay: number | null;
        back_duration: number | null;
        qr_delay: number | null;
        total_duration: number | null;
        sample_size: number;
    } | null;
};

const {
    editions,
    activeEdition,
    dashboard: initialDashboard,
} = defineProps<{
    editions: { id: number; name: string }[];
    activeEdition: { id: number; name: string; hasTemplate: boolean } | null;
    dashboard: EventOpsDashboard | null;
}>();

const dashboard = ref<EventOpsDashboard | null>(initialDashboard);
let statusTimer: ReturnType<typeof setInterval> | undefined;

function formatDuration(seconds: number | null): string {
    if (seconds === null) {
        return '—';
    }

    const m = Math.floor(seconds / 60);
    const s = seconds % 60;

    return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
}

// Polls a small JSON endpoint every 5s — never a full Inertia reload
// (docs/adr/0006-event-operations.md §11).
onMounted(() => {
    if (!activeEdition) {
        return;
    }

    statusTimer = setInterval(async () => {
        const response = await fetch('/operator/status', {
            headers: { Accept: 'application/json' },
        });

        if (response.ok) {
            dashboard.value = (await response.json()).data;
        }
    }, 5000);
});

onBeforeUnmount(() => {
    if (statusTimer) {
        clearInterval(statusTimer);
    }
});

const query = ref('');
const results = ref<SearchResult[]>([]);
const searching = ref(false);
const quickPlateOpen = ref(false);

const quickForm = {
    athlete_name: ref(''),
    bib_number: ref(''),
    race_name: ref(''),
    official_time: ref(''),
    pace: ref(''),
    swim_time: ref(''),
    bike_time: ref(''),
    run_time: ref(''),
    personal_phrase: ref(''),
};
const submittingQuick = ref(false);

const face = ref<PlateFace>('front');
const mode = ref<PlateRenderMode>('product');
const previewSvg = ref<string | null>(null);
const previewWarnings = ref<string[]>([]);
const previewError = ref<string | null>(null);
const previewLoading = ref(false);

function csrfToken(): string {
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

async function fetchQuickPreview() {
    if (!quickPlateOpen.value) {
        return;
    }

    previewLoading.value = true;

    try {
        const response = await fetch(previewAction().url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({
                quick: {
                    athlete_name: quickForm.athlete_name.value,
                    bib_number: quickForm.bib_number.value || null,
                    race_name: quickForm.race_name.value || null,
                    official_time: quickForm.official_time.value || null,
                    pace: quickForm.pace.value || null,
                    swim_time: quickForm.swim_time.value || null,
                    bike_time: quickForm.bike_time.value || null,
                    run_time: quickForm.run_time.value || null,
                    personal_phrase: quickForm.personal_phrase.value || null,
                },
                face: face.value,
                mode: mode.value,
            }),
        });
        const json = await response.json();
        previewSvg.value = json.svg;
        previewWarnings.value = json.warnings ?? [];
        previewError.value = json.error ?? null;
    } finally {
        previewLoading.value = false;
    }
}

const debouncedPreview = useDebounceFn(fetchQuickPreview, 300);

watch([...Object.values(quickForm), face, mode, quickPlateOpen], () =>
    debouncedPreview(),
);

async function runSearch() {
    if (!query.value.trim()) {
        results.value = [];

        return;
    }

    searching.value = true;
    const response = await fetch(
        `${searchAction().url}?q=${encodeURIComponent(query.value)}`,
    );
    results.value = (await response.json()).data ?? [];
    searching.value = false;
}

watch(query, useDebounceFn(runSearch, 250));

function onSelectEvent(value: unknown) {
    if (!value) {
        return;
    }

    router.post(
        selectEvent().url,
        { event_edition_id: String(value) },
        { preserveScroll: true },
    );
}

function submitQuickPlate() {
    submittingQuick.value = true;
    router.post(
        generateQuickPlate().url,
        {
            athlete_name: quickForm.athlete_name.value,
            bib_number: quickForm.bib_number.value || null,
            race_name: quickForm.race_name.value || null,
            official_time: quickForm.official_time.value || null,
            pace: quickForm.pace.value || null,
            swim_time: quickForm.swim_time.value || null,
            bike_time: quickForm.bike_time.value || null,
            run_time: quickForm.run_time.value || null,
            personal_phrase: quickForm.personal_phrase.value || null,
        },
        {
            onFinish: () => {
                submittingQuick.value = false;
                quickPlateOpen.value = false;
            },
        },
    );
}
</script>

<template>
    <Head title="Event OS — Operador" />

    <div class="min-h-svh bg-background p-4 md:p-8">
        <div class="mx-auto max-w-2xl">
            <div class="mb-6 flex items-center justify-between gap-4">
                <div>
                    <p
                        class="text-xs font-semibold tracking-[0.2em] text-fl-gold-ink uppercase"
                    >
                        Event OS
                    </p>
                    <h1
                        class="flex items-center gap-1.5 text-2xl font-bold text-foreground"
                    >
                        Operador
                        <HelpPopover
                            title="Integrada vs. rápida"
                            text="Placa integrada usa datos importados del evento (número, resultado, tiempo). Placa rápida permite producir aunque no exista una base de participantes — se completa a mano."
                        />
                    </h1>
                </div>
                <Select
                    :model-value="String(activeEdition?.id ?? '')"
                    @update:model-value="onSelectEvent"
                >
                    <SelectTrigger
                        class="fl-focus-glow h-12 w-64 border-border bg-card/60 text-foreground transition-colors hover:border-fl-gold/40"
                    >
                        <SelectValue placeholder="Selecciona un evento" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="edition in editions"
                            :key="edition.id"
                            :value="String(edition.id)"
                        >
                            {{ edition.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <template v-if="!activeEdition">
                <Reveal>
                    <div
                        class="flex flex-col items-center gap-4 rounded-2xl border border-dashed border-border bg-card/20 p-14 text-center"
                    >
                        <div
                            class="flex size-14 items-center justify-center rounded-full border border-fl-gold/25 bg-background"
                        >
                            <CalendarClock class="size-7 text-fl-gold-ink" />
                        </div>
                        <p class="max-w-xs text-sm text-muted-foreground">
                            Selecciona un evento activo para empezar a buscar
                            corredores y generar placas.
                        </p>
                    </div>
                </Reveal>
            </template>

            <template v-else>
                <p
                    v-if="!activeEdition.hasTemplate"
                    class="mb-4 rounded-lg border border-amber-500/30 bg-amber-500/5 px-3 py-2 text-xs text-amber-700"
                >
                    Este evento no tiene un molde de placa asignado. Configúralo
                    en "Preparar evento para producción" antes de generar
                    placas.
                </p>

                <div
                    v-if="dashboard"
                    class="mb-6 grid grid-cols-2 gap-2 sm:grid-cols-4"
                >
                    <div class="rounded-xl border border-border bg-card/30 p-3">
                        <p
                            class="text-[10px] text-muted-foreground/80 uppercase"
                        >
                            Proveedor
                        </p>
                        <p
                            class="mt-0.5 flex items-center gap-1.5 text-sm text-foreground"
                        >
                            <span
                                class="size-1.5 rounded-full"
                                :class="
                                    dashboard.provider.connected
                                        ? dashboard.provider.stale
                                            ? 'bg-amber-400'
                                            : 'bg-emerald-400'
                                        : 'bg-foreground/10'
                                "
                            />
                            {{
                                dashboard.provider.connected
                                    ? dashboard.provider.stale
                                        ? 'Datos sin actualizar'
                                        : 'Conectado'
                                    : 'Sin proveedor'
                            }}
                        </p>
                        <p
                            v-if="dashboard.provider.last_sync_at"
                            class="mt-0.5 text-[10px] text-muted-foreground/80"
                        >
                            Último sync: {{ dashboard.provider.last_sync_at }}
                        </p>
                    </div>
                    <div class="rounded-xl border border-border bg-card/30 p-3">
                        <p
                            class="text-[10px] text-muted-foreground/80 uppercase"
                        >
                            Datos
                        </p>
                        <p class="mt-0.5 text-sm text-foreground">
                            {{ dashboard.data.participants }} corredores ·
                            {{ dashboard.data.results }} resultados
                        </p>
                        <p
                            v-if="dashboard.data.conflicts > 0"
                            class="mt-0.5 text-[10px] text-amber-700"
                        >
                            {{ dashboard.data.conflicts }} conflicto(s) de
                            identidad
                        </p>
                    </div>
                    <div class="rounded-xl border border-border bg-card/30 p-3">
                        <p
                            class="text-[10px] text-muted-foreground/80 uppercase"
                        >
                            Producción
                        </p>
                        <p class="mt-0.5 text-sm text-foreground">
                            {{ dashboard.production.pending }} pendientes ·
                            {{ dashboard.production.delivered }} entregadas
                        </p>
                        <p
                            v-if="dashboard.production.failed > 0"
                            class="mt-0.5 text-[10px] text-red-700"
                        >
                            {{ dashboard.production.failed }} con problema
                        </p>
                    </div>
                    <div class="rounded-xl border border-border bg-card/30 p-3">
                        <p
                            class="text-[10px] text-muted-foreground/80 uppercase"
                        >
                            Estaciones
                        </p>
                        <p class="mt-0.5 text-sm text-foreground">
                            {{
                                dashboard.stations.filter((s) => s.online)
                                    .length
                            }}
                            /
                            {{ dashboard.stations.length }} en línea
                        </p>
                        <p
                            v-if="dashboard.metrics"
                            class="mt-0.5 text-[10px] text-muted-foreground/80"
                        >
                            Prom. producción:
                            {{
                                formatDuration(dashboard.metrics.total_duration)
                            }}
                        </p>
                    </div>
                </div>

                <p
                    class="mb-2 text-xs font-semibold tracking-[0.2em] text-muted-foreground/80 uppercase"
                >
                    Buscar corredor
                </p>
                <div class="relative mb-3">
                    <Search
                        class="absolute top-1/2 left-4 size-5 -translate-y-1/2 text-muted-foreground/80"
                    />
                    <Input
                        v-model="query"
                        autofocus
                        placeholder="Número de corredor o nombre…"
                        class="fl-focus-glow h-14 border-border bg-card/60 pl-12 text-lg text-foreground placeholder:text-muted-foreground/80"
                        @keyup.enter="runSearch"
                    />
                </div>

                <Button
                    variant="outline"
                    class="fl-focus-glow h-12 w-full gap-2 border-fl-gold/30 text-sm font-semibold tracking-wide text-fl-gold-ink uppercase hover:bg-fl-gold/10"
                    @click="quickPlateOpen = true"
                >
                    <Zap class="size-4" />
                    Generar placa rápida
                </Button>
                <p class="mt-1.5 mb-6 text-xs text-muted-foreground/80">
                    Para corredores sin cuenta o sin resultado en el sistema
                    todavía. La placa se genera igual, con su propio Legacy Code
                    permanente — la persona puede reclamarla después escaneando
                    el QR.
                </p>

                <div
                    v-if="searching"
                    class="flex items-center gap-2 py-4 text-sm text-muted-foreground/80"
                >
                    <Spinner /> Buscando…
                </div>

                <div v-else-if="results.length" class="space-y-2">
                    <Link
                        v-for="participant in results"
                        :key="participant.id"
                        :href="showParticipant(participant.id).url"
                        class="fl-hover-glow flex items-center justify-between rounded-xl border border-border bg-card/40 p-4 transition-colors"
                    >
                        <div>
                            <p class="font-semibold text-foreground">
                                {{ participant.full_name }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{
                                    [
                                        participant.bib_number
                                            ? `#${participant.bib_number}`
                                            : null,
                                        participant.race,
                                    ]
                                        .filter(Boolean)
                                        .join(' · ')
                                }}
                            </p>
                        </div>
                        <span
                            v-if="participant.has_plate"
                            class="rounded-full border border-fl-gold/30 px-2.5 py-1 text-[10px] text-fl-gold-ink uppercase"
                            >Ya tiene placa</span
                        >
                    </Link>
                </div>

                <p
                    v-else-if="query.trim().length > 0"
                    class="py-6 text-center text-sm text-muted-foreground/80"
                >
                    Sin corredores para «{{ query }}». Prueba con otro nombre o
                    número, o genera una placa rápida.
                </p>
            </template>
        </div>

        <Dialog v-model:open="quickPlateOpen">
            <DialogContent
                class="max-w-3xl border-border bg-card text-foreground"
            >
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <QrCode class="size-5 text-fl-gold-ink" /> Placa rápida
                    </DialogTitle>
                </DialogHeader>
                <div class="grid gap-6 sm:grid-cols-[1fr_260px]">
                    <form class="space-y-4" @submit.prevent="submitQuickPlate">
                        <div class="grid gap-2">
                            <Label>Nombre</Label>
                            <Input
                                v-model="quickForm.athlete_name.value"
                                required
                                class="bg-background"
                            />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-2">
                                <Label>Número (opcional)</Label>
                                <Input
                                    v-model="quickForm.bib_number.value"
                                    class="bg-background"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label>Distancia (opcional)</Label>
                                <Input
                                    v-model="quickForm.race_name.value"
                                    class="bg-background"
                                />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-2">
                                <Label>Tiempo (si se tiene)</Label>
                                <Input
                                    v-model="quickForm.official_time.value"
                                    class="bg-background"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label>Ritmo (si se tiene)</Label>
                                <Input
                                    v-model="quickForm.pace.value"
                                    class="bg-background"
                                />
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-3">
                            <div class="grid gap-2">
                                <Label class="text-xs">Natación</Label>
                                <Input
                                    v-model="quickForm.swim_time.value"
                                    class="bg-background"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label class="text-xs">Ciclismo</Label>
                                <Input
                                    v-model="quickForm.bike_time.value"
                                    class="bg-background"
                                />
                            </div>
                            <div class="grid gap-2">
                                <Label class="text-xs">Carrera</Label>
                                <Input
                                    v-model="quickForm.run_time.value"
                                    class="bg-background"
                                />
                            </div>
                        </div>
                        <div class="grid gap-2">
                            <Label
                                >Frase personal (opcional, va en el
                                reverso)</Label
                            >
                            <Input
                                v-model="quickForm.personal_phrase.value"
                                class="bg-background"
                                maxlength="150"
                            />
                        </div>
                        <DialogFooter>
                            <Button
                                type="submit"
                                class="w-full bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                                :disabled="submittingQuick"
                            >
                                <Spinner v-if="submittingQuick" />
                                Generar placa
                            </Button>
                        </DialogFooter>
                    </form>

                    <div class="flex justify-center">
                        <PlatePreviewCard
                            v-model:face="face"
                            v-model:mode="mode"
                            :svg="previewSvg"
                            :warnings="previewWarnings"
                            :loading="previewLoading"
                            :error="previewError"
                            is-demo
                        />
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
