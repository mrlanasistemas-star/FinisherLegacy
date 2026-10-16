<script setup lang="ts">
/**
 * One Legacy Plate on the "Preparación y entrega" board: who, which race,
 * its NFC state and the ONE next step. No machine/laser vocabulary — the
 * machine software does the personalisation.
 */
import { Download, Flag, Hash, Nfc, Timer, TriangleAlert } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { timeAgo } from '@/lib/datetime';
import { data as dataRoute } from '@/routes/admin/legacy-nfc/preparation';

export type PreparationCardData = {
    key: string;
    job_id: number | null;
    plate_id: number | null;
    entitlement_id: number | null;
    stage: string;
    next_action:
        | 'generate'
        | 'start'
        | 'personalize'
        | 'assign_nfc'
        | 'ready'
        | 'deliver'
        | null;
    athlete_name: string | null;
    bib_number: string | null;
    event: string | null;
    race: string | null;
    official_time: string | null;
    serial_number: string | null;
    model: string | null;
    historical: boolean;
    nfc: { tag: string | null; connected: boolean } | null;
    eligible: boolean;
    reasons: string[];
    updated_at: string | null;
};

const props = defineProps<{
    card: PreparationCardData;
    busy: boolean;
    canManage: boolean;
    canGenerate: boolean;
    stageLabel?: string | null;
}>();

const emit = defineEmits<{
    action: [card: PreparationCardData];
    assignNfc: [card: PreparationCardData];
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

const actionLabels: Record<string, string> = {
    generate: 'Generar',
    start: 'Empezar preparación',
    personalize: 'Marcar personalizada',
    assign_nfc: 'Asignar NFC',
    ready: 'Marcar lista',
    deliver: 'Marcar entregada',
};

const actionLabel = computed(() =>
    props.card.next_action ? actionLabels[props.card.next_action] : null,
);

const allowed = computed(() => {
    if (props.card.next_action === 'generate') {
        return props.canGenerate && props.card.eligible;
    }

    return props.canManage && props.card.next_action !== null;
});
</script>

<template>
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
            }}<template v-if="card.race"> · {{ card.race }}</template>
        </p>

        <div class="mt-2 flex flex-wrap items-center gap-1.5 text-[11px]">
            <span
                v-if="stageLabel"
                class="rounded-full bg-foreground px-2 py-0.5 font-medium text-background"
                >{{ stageLabel }}</span
            >
            <span
                v-if="card.model"
                class="rounded-full bg-fl-cream px-2 py-0.5 font-medium text-fl-gold-ink"
                >{{ card.model }}</span
            >
            <span
                v-if="card.official_time"
                class="legacy-numeric inline-flex items-center gap-1 rounded-full bg-muted px-2 py-0.5"
                ><Timer class="size-3" />{{ card.official_time }}</span
            >
            <span
                v-if="card.serial_number"
                class="rounded-full bg-muted px-2 py-0.5 font-mono"
                >{{ card.serial_number }}</span
            >
            <span
                v-if="card.nfc"
                class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 font-medium"
                :class="
                    card.nfc.connected
                        ? 'bg-emerald-100 text-emerald-800'
                        : 'bg-sky-100 text-sky-800'
                "
                ><Nfc class="size-3" />{{
                    card.nfc.connected
                        ? 'NFC conectado'
                        : 'NFC ' + (card.nfc.tag ?? '')
                }}</span
            >
            <span
                v-if="card.historical"
                class="rounded-full border border-border px-2 py-0.5 text-muted-foreground"
                >Histórica</span
            >
        </div>

        <ul
            v-if="
                card.next_action === 'generate' &&
                !card.eligible &&
                card.reasons.length
            "
            class="mt-3 space-y-1"
        >
            <li
                v-for="reason in card.reasons"
                :key="reason"
                class="flex items-center gap-1.5 text-xs text-amber-800"
            >
                <TriangleAlert class="size-3" />
                {{ reasonLabels[reason] ?? 'Revisar datos' }}
            </li>
        </ul>

        <div
            v-if="actionLabel || card.plate_id"
            class="mt-3 flex items-center gap-2"
        >
            <Button
                v-if="actionLabel"
                size="sm"
                class="h-9 flex-1 rounded-full"
                :variant="
                    card.next_action === 'assign_nfc' ? 'default' : 'secondary'
                "
                :disabled="!allowed || busy"
                @click="
                    card.next_action === 'assign_nfc'
                        ? emit('assignNfc', card)
                        : emit('action', card)
                "
            >
                <Nfc
                    v-if="card.next_action === 'assign_nfc'"
                    class="size-3.5"
                />
                {{ busy ? 'Guardando…' : actionLabel }}
            </Button>
            <Button
                v-if="card.plate_id && !card.historical"
                as-child
                size="icon"
                variant="ghost"
                class="size-9 shrink-0 rounded-full"
            >
                <a
                    :href="dataRoute(card.plate_id).url"
                    title="Datos de personalización"
                    aria-label="Descargar datos de personalización"
                    ><Download class="size-4"
                /></a>
            </Button>
        </div>

        <p
            v-if="card.updated_at"
            class="mt-2 text-[11px] text-muted-foreground"
        >
            {{ timeAgo(card.updated_at) }}
        </p>
    </article>
</template>
