<script setup lang="ts">
/**
 * Admin â†’ Legacy + NFC â†’ "PreparaciÃ³n y entrega". Six plain stages over
 * the existing production state machine (docs/NFC.md Â§PreparaciÃ³n):
 * Pendiente â†’ Preparando â†’ Personalizada â†’ NFC asignado â†’ Lista â†’ Entregada.
 * The machine software does the personalisation; staff record each step.
 */
import { Head, router } from '@inertiajs/vue3';
import {
    CheckCircle2,
    Clock,
    Columns3,
    Download,
    Flag,
    List,
    Nfc,
    PackageCheck,
    Search,
    Sparkles,
    TriangleAlert,
    Wrench,
} from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import type { Component } from 'vue';
import { computed, ref } from 'vue';
import KanbanBoard from '@/components/admin/KanbanBoard.vue';
import type { KanbanLane } from '@/components/admin/KanbanBoard.vue';
import FancySelect from '@/components/forms/FancySelect.vue';
import AssignNfcDialog from '@/components/preparation/AssignNfcDialog.vue';
import PreparationCard from '@/components/preparation/PreparationCard.vue';
import type { PreparationCardData } from '@/components/preparation/PreparationCard.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    deliver,
    exportMethod,
    index,
    personalize,
    ready,
    start,
} from '@/routes/admin/legacy-nfc/preparation';
import { produce } from '@/routes/admin/legacy-plates/production';

type Lane = {
    key: string;
    title: string;
    count: number;
    items: PreparationCardData[];
};

const props = defineProps<{
    lanes: Lane[];
    attention: PreparationCardData[];
    filters: { event_edition_id: number | null; q: string | null };
    events: { id: number; name: string }[];
    availableTags: { id: number; label: string; batch: string | null }[];
    can: { manage: boolean; generate: boolean };
}>();

const laneMeta: Record<string, { icon: Component; accent: string }> = {
    pending: { icon: Clock, accent: 'bg-amber-100 text-amber-800' },
    preparing: { icon: Wrench, accent: 'bg-sky-100 text-sky-800' },
    personalized: { icon: Sparkles, accent: 'bg-violet-100 text-violet-800' },
    nfc_assigned: { icon: Nfc, accent: 'bg-fl-cream text-fl-gold-ink' },
    ready: { icon: PackageCheck, accent: 'bg-emerald-100 text-emerald-800' },
    delivered: { icon: CheckCircle2, accent: 'bg-muted text-foreground' },
};

const lanes = computed<KanbanLane<PreparationCardData>[]>(() =>
    props.lanes.map((lane) => ({
        key: lane.key,
        title: lane.title,
        icon: laneMeta[lane.key]?.icon ?? Clock,
        accent: laneMeta[lane.key]?.accent ?? 'bg-muted',
        count: lane.count,
        items: lane.items,
    })),
);

const stageTitles = computed(
    () =>
        Object.fromEntries(props.lanes.map((l) => [l.key, l.title])) as Record<
            string,
            string
        >,
);
const allCards = computed(() => props.lanes.flatMap((lane) => lane.items));

const view = ref<'board' | 'list'>('board');

const eventOptions = computed(() => [
    { value: null, label: 'Todos los eventos', icon: Flag },
    ...props.events.map((e) => ({ value: e.id, label: e.name })),
]);

const search = ref(props.filters.q ?? '');

function reload(params: Record<string, unknown>) {
    router.get(
        index().url,
        {
            event_edition_id: props.filters.event_edition_id,
            q: search.value || undefined,
            ...params,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

const applySearch = useDebounceFn(() => reload({}), 350);

const exportUrl = computed(
    () =>
        exportMethod({
            query: props.filters.event_edition_id
                ? { event_edition_id: props.filters.event_edition_id }
                : {},
        }).url,
);

const busyKey = ref<string | null>(null);

function runAction(card: PreparationCardData) {
    const url = (() => {
        switch (card.next_action) {
            case 'generate':
                return card.entitlement_id
                    ? produce(card.entitlement_id).url
                    : null;
            case 'start':
                return card.job_id ? start(card.job_id).url : null;
            case 'personalize':
                return card.job_id ? personalize(card.job_id).url : null;
            case 'ready':
                return card.job_id ? ready(card.job_id).url : null;
            case 'deliver':
                return card.job_id ? deliver(card.job_id).url : null;
            default:
                return null;
        }
    })();

    if (!url) {
        return;
    }

    busyKey.value = card.key;
    router.post(
        url,
        {},
        { preserveScroll: true, onFinish: () => (busyKey.value = null) },
    );
}

const nfcOpen = ref(false);
const nfcCard = ref<PreparationCardData | null>(null);

function openNfc(card: PreparationCardData) {
    nfcCard.value = card;
    nfcOpen.value = true;
}
</script>

<template>
    <Head title="PreparaciÃ³n y entrega" />

    <div class="w-full px-4 py-6 md:px-6 md:py-8 xl:px-8">
        <div
            class="mb-6 flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between"
        >
            <div class="flex items-center gap-3">
                <span
                    class="flex size-11 items-center justify-center rounded-xl bg-fl-cream text-fl-gold-ink"
                >
                    <PackageCheck class="size-5" />
                </span>
                <div>
                    <h1 class="text-xl font-semibold">
                        PreparaciÃ³n y entrega
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        La mÃ¡quina personaliza cada Legacy Plate; aquÃ­
                        registras cada paso hasta entregarla con su etiqueta
                        NFC.
                    </p>
                </div>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <div class="relative">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        v-model="search"
                        class="h-10 pl-9 sm:w-56"
                        placeholder="Nombre, dorsal o serie"
                        aria-label="Buscar placa"
                        @update:model-value="applySearch"
                    />
                </div>
                <FancySelect
                    :model-value="filters.event_edition_id"
                    :options="eventOptions"
                    aria-label="Evento"
                    class="w-full sm:w-72"
                    @update:model-value="(v) => reload({ event_edition_id: v })"
                />
                <div class="flex gap-2">
                    <div
                        class="inline-flex rounded-lg border border-border p-0.5"
                        role="group"
                        aria-label="Vista"
                    >
                        <Button
                            size="icon"
                            :variant="view === 'board' ? 'secondary' : 'ghost'"
                            class="size-9"
                            aria-label="Tablero"
                            :aria-pressed="view === 'board'"
                            @click="view = 'board'"
                            ><Columns3 class="size-4"
                        /></Button>
                        <Button
                            size="icon"
                            :variant="view === 'list' ? 'secondary' : 'ghost'"
                            class="size-9"
                            aria-label="Lista"
                            :aria-pressed="view === 'list'"
                            @click="view = 'list'"
                            ><List class="size-4"
                        /></Button>
                    </div>
                    <Button as-child variant="outline" class="h-10">
                        <a
                            :href="exportUrl"
                            title="Datos de las placas por preparar (CSV)"
                        >
                            <Download class="size-4" /> Datos para la mÃ¡quina
                        </a>
                    </Button>
                </div>
            </div>
        </div>

        <KanbanBoard
            v-if="view === 'board'"
            :lanes="lanes"
            :item-key="(card) => card.key"
        >
            <template #card="{ item: card }">
                <PreparationCard
                    :card="card"
                    :busy="busyKey === card.key"
                    :can-manage="can.manage"
                    :can-generate="can.generate"
                    @action="runAction"
                    @assign-nfc="openNfc"
                />
            </template>
        </KanbanBoard>

        <div
            v-else
            class="grid gap-3 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4"
        >
            <PreparationCard
                v-for="card in allCards"
                :key="card.key"
                :card="card"
                :stage-label="stageTitles[card.stage]"
                :busy="busyKey === card.key"
                :can-manage="can.manage"
                :can-generate="can.generate"
                @action="runAction"
                @assign-nfc="openNfc"
            />
            <p
                v-if="!allCards.length"
                class="col-span-full rounded-xl border border-dashed border-border py-10 text-center text-sm text-muted-foreground"
            >
                No hay placas con estos filtros.
            </p>
        </div>

        <section v-if="attention.length" class="mt-8">
            <h2
                class="flex items-center gap-2 text-sm font-semibold text-amber-800"
            >
                <TriangleAlert class="size-4" /> Requieren atenciÃ³n ({{
                    attention.length
                }})
            </h2>
            <p class="mt-1 text-xs text-muted-foreground">
                Intentos que la mÃ¡quina no pudo terminar o que se cancelaron
                â€” se reimprimen desde Legacy Plates.
            </p>
            <div class="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <PreparationCard
                    v-for="card in attention"
                    :key="card.key"
                    :card="card"
                    :busy="false"
                    :can-manage="false"
                    :can-generate="false"
                />
            </div>
        </section>

        <AssignNfcDialog
            v-model:open="nfcOpen"
            :plate-id="nfcCard?.plate_id ?? null"
            :plate-label="
                nfcCard
                    ? `${nfcCard.athlete_name ?? ''} Â· ${nfcCard.serial_number ?? ''}`
                    : null
            "
            :has-tag="nfcCard?.nfc !== null && nfcCard?.nfc !== undefined"
            :available-tags="availableTags"
        />
    </div>
</template>
