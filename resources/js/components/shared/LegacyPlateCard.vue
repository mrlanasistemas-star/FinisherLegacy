<script setup lang="ts">
/**
 * Compact list card for "MIS LEGACY PLATES" (brief §12-§13/§41/§99) — shows
 * the commercial presale status (LegacyPlateEntitlementStatus), not just a
 * produced Plate's physical status, so a paid presale with no result yet
 * still shows up as "Preventa pagada · Esperando evento" instead of being
 * invisible until production.
 */
import { Boxes, QrCode } from '@lucide/vue';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';

const props = defineProps<{
    presaleStatus: string;
    eventName: string | null;
    editionName: string | null;
    raceName: string | null;
    modelName: string | null;
    engravingDisplayName: string | null;
    serialNumber: string | null;
    legacyCode: string | null;
}>();

const labels: Record<string, string> = {
    pending_payment: 'Pago pendiente',
    paid: 'Preventa pagada',
    linked: 'Vinculado a tu participación',
    queued: 'En producción',
    produced: 'Lista',
    delivered: 'Entregada',
};

const classes: Record<string, string> = {
    pending_payment: 'border-amber-500/30 text-amber-400',
    paid: 'border-sky-500/30 text-sky-400',
    linked: 'border-sky-500/30 text-sky-400',
    queued: 'border-fl-gold/40 text-fl-gold-soft',
    produced: 'border-emerald-500/30 text-emerald-400',
    delivered: 'border-emerald-500/30 text-emerald-400',
};

const helper: Record<string, string> = {
    pending_payment: 'Completa tu pago para asegurar tu Legacy Plate.',
    paid: 'Esperando el evento — tu Legacy Plate se producirá con tu resultado.',
    linked: 'Ya identificamos tu participación — falta tu resultado oficial.',
    queued: 'Tu Legacy Plate está en producción.',
};

const label = computed(
    () => labels[props.presaleStatus] ?? props.presaleStatus,
);
const className = computed(
    () => classes[props.presaleStatus] ?? 'border-white/20 text-white/50',
);
const helperText = computed(() => helper[props.presaleStatus] ?? null);
</script>

<template>
    <div class="rounded-2xl border border-white/10 bg-fl-graphite/30 p-5">
        <div class="flex items-start justify-between gap-3">
            <div class="flex items-center gap-2 text-fl-gold-soft">
                <Boxes class="size-5" />
                <span class="text-xs tracking-[0.2em] uppercase">{{
                    modelName ?? 'Legacy Plate'
                }}</span>
            </div>
            <Badge variant="outline" :class="className">{{ label }}</Badge>
        </div>

        <p class="mt-4 text-lg font-semibold text-white">
            {{ engravingDisplayName ?? eventName ?? 'Evento por confirmar' }}
        </p>
        <p class="text-sm text-white/50">
            {{ eventName ?? editionName ?? 'Evento no asignado'
            }}<span v-if="raceName"> · {{ raceName }}</span>
        </p>
        <p v-if="helperText" class="mt-2 text-xs text-white/40">
            {{ helperText }}
        </p>

        <div
            v-if="serialNumber || legacyCode"
            class="mt-4 flex items-center justify-between text-xs text-white/40"
        >
            <span>{{ serialNumber ?? 'Sin folio' }}</span>
            <span v-if="legacyCode" class="flex items-center gap-1"
                ><QrCode class="size-3.5" /> {{ legacyCode }}</span
            >
        </div>
    </div>
</template>
