<script setup lang="ts">
/**
 * Compact list card for "MIS LEGACY PLATES" (brief §41/§99) — status +
 * engraving text, never a Figma-style editor here, just a read-only
 * summary of what was already produced.
 */
import { Boxes, QrCode } from '@lucide/vue';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';

const props = defineProps<{
    serialNumber: string | null;
    status: string;
    eventName: string | null;
    raceName: string | null;
    engravingDisplayName: string | null;
    legacyCode: string | null;
}>();

const labels: Record<string, string> = {
    draft: 'Borrador',
    pending_confirmation: 'Pendiente de confirmación',
    queued: 'En cola',
    processing: 'En producción',
    produced: 'Producida',
    quality_check: 'Control de calidad',
    ready: 'Lista',
    delivered: 'Entregada',
    cancelled: 'Cancelada',
    reprint: 'Reimpresión',
};

const classes: Record<string, string> = {
    draft: 'border-white/20 text-white/50',
    pending_confirmation: 'border-amber-500/30 text-amber-400',
    queued: 'border-amber-500/30 text-amber-400',
    processing: 'border-sky-500/30 text-sky-400',
    produced: 'border-emerald-500/30 text-emerald-400',
    quality_check: 'border-sky-500/30 text-sky-400',
    ready: 'border-emerald-500/30 text-emerald-400',
    delivered: 'border-fl-gold/40 text-fl-gold-soft',
    cancelled: 'border-red-500/30 text-red-400',
    reprint: 'border-orange-500/30 text-orange-400',
};

const label = computed(() => labels[props.status] ?? props.status);
const className = computed(
    () => classes[props.status] ?? 'border-white/20 text-white/50',
);
</script>

<template>
    <div class="rounded-2xl border border-white/10 bg-fl-graphite/30 p-5">
        <div class="flex items-start justify-between gap-3">
            <div class="flex items-center gap-2 text-fl-gold-soft">
                <Boxes class="size-5" />
                <span class="text-xs tracking-[0.2em] uppercase"
                    >Legacy Plate</span
                >
            </div>
            <Badge variant="outline" :class="className">{{ label }}</Badge>
        </div>

        <p class="mt-4 text-lg font-semibold text-white">
            {{ engravingDisplayName ?? '—' }}
        </p>
        <p class="text-sm text-white/50">
            {{ eventName ?? 'Evento no asignado'
            }}<span v-if="raceName"> · {{ raceName }}</span>
        </p>

        <div
            class="mt-4 flex items-center justify-between text-xs text-white/40"
        >
            <span>{{ serialNumber ?? 'Sin folio' }}</span>
            <span v-if="legacyCode" class="flex items-center gap-1"
                ><QrCode class="size-3.5" /> {{ legacyCode }}</span
            >
        </div>
    </div>
</template>
