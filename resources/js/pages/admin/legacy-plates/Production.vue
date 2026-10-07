<script setup lang="ts">
/**
 * Legacy Plate production as a kanban board — Por pagar → Lista para
 * imprimir → En impresión → Impresa · NFC · clip → Entregada. Legacy Plate
 * V3: the FRONT is printed and resin-coated (no laser), the NFC chip is
 * programmed with the Legacy Code and the stainless money clip is
 * assembled on the unprinted back. "Enviar a impresión" always goes through GenerateLegacyPlate, the
 * same eligibility rules as the API.
 */
import { Head, router } from '@inertiajs/vue3';
import {
    CheckCircle2,
    CreditCard,
    Flag,
    Hash,
    Nfc,
    PackageCheck,
    Printer,
    Send,
    Timer,
    TriangleAlert,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import KanbanBoard from '@/components/admin/KanbanBoard.vue';
import type { KanbanLane } from '@/components/admin/KanbanBoard.vue';
import SecondaryNav from '@/components/admin/SecondaryNav.vue';
import FancySelect from '@/components/forms/FancySelect.vue';
import { Button } from '@/components/ui/button';
import { LEGACY_PLATE_AREA_NAV } from '@/config/areaNav';
import { timeAgo } from '@/lib/datetime';

type QueueRow = {
    id: number;
    bib_number: string | null;
    athlete_name: string | null;
    race: string | null;
    model: string | null;
    status: string;
    paid: boolean;
    official_time: string | null;
    pace: string | null;
    eligible: boolean;
    reasons: string[];
};

type BoardCard = {
    id: number;
    athlete_name: string | null;
    bib_number: string | null;
    event: string | null;
    race: string | null;
    official_time: string | null;
    model: string | null;
    plate_id: number | null;
    serial_number: string | null;
    eligible: boolean;
    reasons: string[];
    updated_at: string | null;
};

const props = defineProps<{
    events: { id: number; name: string }[];
    selectedEventEditionId: number | null;
    queue: QueueRow[];
    board: { key: string; count: number; items: BoardCard[] }[];
}>();

const reasonLabels: Record<string, string> = {
    LEGACY_PLATE_NOT_PAID: 'Pago pendiente',
    NO_PARTICIPANT_LINKED: 'Sin participante vinculado',
    IDENTITY_CONFLICT: 'Conflicto de identidad',
    NO_RESULT: 'Falta resultado',
    PLATE_ALREADY_EXISTS: 'Ya tiene Legacy Plate',
    NO_MODEL: 'Layout no disponible',
    LEGACY_PLATE_ALREADY_EXISTS: 'Ya se generó',
};

const laneMeta: Record<
    string,
    { title: string; icon: typeof Printer; accent: string }
> = {
    pending_payment: {
        title: 'Por pagar',
        icon: CreditCard,
        accent: 'bg-amber-100 text-amber-700',
    },
    ready: {
        title: 'Lista para imprimir',
        icon: Send,
        accent: 'bg-sky-100 text-sky-700',
    },
    printing: {
        title: 'En impresión',
        icon: Printer,
        accent: 'bg-violet-100 text-violet-700',
    },
    printed: {
        title: 'Impresa · NFC · clip',
        icon: Nfc,
        accent: 'bg-fl-cream text-fl-gold-ink',
    },
    delivered: {
        title: 'Entregada',
        icon: PackageCheck,
        accent: 'bg-emerald-100 text-emerald-700',
    },
};

const lanes = computed<KanbanLane<BoardCard>[]>(() =>
    props.board.map((lane) => ({
        key: lane.key,
        title: laneMeta[lane.key]?.title ?? lane.key,
        icon: laneMeta[lane.key]?.icon ?? Printer,
        accent: laneMeta[lane.key]?.accent ?? 'bg-muted',
        count: lane.count,
        items: lane.items,
    })),
);

const eventOptions = computed(() => [
    { value: null, label: 'Todos los eventos', icon: Flag },
    ...props.events.map((e) => ({ value: e.id, label: e.name })),
]);

function selectEvent(value: string | number | null) {
    router.get(
        '/admin/legacy-plates/production',
        value ? { event_edition_id: value } : {},
        { preserveState: true, replace: true },
    );
}

const producing = ref<number | null>(null);

function produce(id: number) {
    producing.value = id;
    router.post(
        `/admin/legacy-plates/production/entitlements/${id}/produce`,
        {},
        { preserveScroll: true, onFinish: () => (producing.value = null) },
    );
}

const readyCount = computed(
    () =>
        props.board
            .find((l) => l.key === 'ready')
            ?.items.filter((i) => i.eligible).length ?? 0,
);
</script>

<template>
    <Head title="Producción de Legacy Plates" />

    <div class="w-full p-4 md:p-8">
        <SecondaryNav :items="LEGACY_PLATE_AREA_NAV" />

        <div
            class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
        >
            <div class="flex items-center gap-3">
                <span
                    class="flex size-11 items-center justify-center rounded-xl bg-fl-cream text-fl-gold-ink"
                >
                    <Printer class="size-5" />
                </span>
                <div>
                    <h1 class="text-xl font-semibold">Producción</h1>
                    <p class="text-sm text-muted-foreground">
                        Impresión del frente + chip NFC programado con el Legacy
                        Code + clip de acero inoxidable en el reverso.
                        <span
                            v-if="readyCount"
                            class="font-medium text-foreground"
                            >{{ readyCount }} listas para enviar.</span
                        >
                    </p>
                </div>
            </div>
            <FancySelect
                :model-value="selectedEventEditionId"
                :options="eventOptions"
                aria-label="Evento"
                class="w-full sm:w-80"
                @update:model-value="selectEvent"
            />
        </div>

        <KanbanBoard :lanes="lanes" :item-key="(card) => card.id">
            <template #card="{ item: card, lane }">
                <article
                    class="rounded-xl border border-border bg-card p-3.5 shadow-[0_1px_2px_rgb(23_23_20/0.04)]"
                >
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-sm leading-snug font-semibold">
                            {{ card.athlete_name ?? 'Sin participante' }}
                        </p>
                        <span
                            v-if="card.bib_number"
                            class="inline-flex shrink-0 items-center gap-0.5 rounded-full bg-muted px-2 py-0.5 font-mono text-[11px]"
                            ><Hash class="size-3" />{{ card.bib_number }}</span
                        >
                    </div>
                    <p
                        v-if="card.event"
                        class="mt-1 flex items-center gap-1.5 truncate text-xs text-muted-foreground"
                    >
                        <Flag class="size-3 shrink-0" />
                        {{ card.event
                        }}<template v-if="card.race">
                            · {{ card.race }}</template
                        >
                    </p>
                    <div
                        class="mt-2 flex flex-wrap items-center gap-1.5 text-[11px]"
                    >
                        <span
                            v-if="card.model"
                            class="rounded-full bg-fl-cream px-2 py-0.5 font-medium text-fl-gold-ink"
                            >{{ card.model }}</span
                        >
                        <span
                            v-if="card.official_time"
                            class="legacy-numeric inline-flex items-center gap-1 rounded-full bg-muted px-2 py-0.5"
                            ><Timer class="size-3" />{{
                                card.official_time
                            }}</span
                        >
                        <span
                            v-if="card.serial_number"
                            class="rounded-full bg-muted px-2 py-0.5 font-mono"
                            >{{ card.serial_number }}</span
                        >
                    </div>

                    <template v-if="lane.key === 'ready'">
                        <ul
                            v-if="!card.eligible && card.reasons.length"
                            class="mt-3 space-y-1"
                        >
                            <li
                                v-for="reason in card.reasons"
                                :key="reason"
                                class="flex items-center gap-1.5 text-xs text-amber-700"
                            >
                                <TriangleAlert class="size-3" />
                                {{ reasonLabels[reason] ?? reason }}
                            </li>
                        </ul>
                        <Button
                            size="sm"
                            class="mt-3 w-full rounded-full"
                            :disabled="!card.eligible || producing === card.id"
                            @click="produce(card.id)"
                        >
                            <Send class="size-3.5" />
                            {{
                                producing === card.id
                                    ? 'Enviando…'
                                    : 'Enviar a impresión'
                            }}
                        </Button>
                    </template>
                    <p
                        v-else-if="lane.key === 'delivered'"
                        class="mt-3 inline-flex items-center gap-1 text-xs text-emerald-700"
                    >
                        <CheckCircle2 class="size-3.5" /> Entregada
                    </p>
                    <p
                        v-if="card.updated_at"
                        class="mt-2 text-[11px] text-muted-foreground"
                    >
                        {{ timeAgo(card.updated_at) }}
                    </p>
                </article>
            </template>
        </KanbanBoard>
    </div>
</template>
