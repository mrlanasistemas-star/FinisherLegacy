<script setup lang="ts">
/**
 * Compact list card for "MIS LEGACY PLATES" (brief §12-§13/§41/§99) — shows
 * the commercial presale status (LegacyPlateEntitlementStatus), not just a
 * produced Plate's physical status, so a paid presale with no result yet
 * still shows up as "Preventa pagada · Esperando evento" instead of being
 * invisible until production.
 */
import { Boxes, Nfc } from '@lucide/vue';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { useI18n } from '@/i18n';

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

const { t } = useI18n();

const labels = computed<Record<string, string>>(() => ({
    pending_payment: t('Pago pendiente'),
    paid: t('Preventa pagada'),
    linked: t('Vinculado a tu participación'),
    queued: t('En producción'),
    produced: t('Lista'),
    delivered: t('Entregada'),
}));

const classes: Record<string, string> = {
    pending_payment: 'border-amber-500/30 text-amber-700',
    paid: 'border-sky-500/30 text-sky-700',
    linked: 'border-sky-500/30 text-sky-700',
    queued: 'border-fl-gold/40 text-fl-gold-ink',
    produced: 'border-emerald-500/30 text-emerald-700',
    delivered: 'border-emerald-500/30 text-emerald-700',
};

const helper = computed<Record<string, string>>(() => ({
    pending_payment: t('Completa tu pago para asegurar tu Legacy Plate.'),
    paid: t(
        'Esperando el evento — tu Legacy Plate se producirá con tu resultado.',
    ),
    linked: t(
        'Ya identificamos tu participación — falta tu resultado oficial.',
    ),
    queued: t('Tu Legacy Plate está en producción.'),
}));

const label = computed(
    () => labels.value[props.presaleStatus] ?? props.presaleStatus,
);
const className = computed(
    () =>
        classes[props.presaleStatus] ??
        'border-foreground/15 text-muted-foreground',
);
const helperText = computed(() => helper.value[props.presaleStatus] ?? null);
</script>

<template>
    <div class="rounded-2xl border border-border bg-card/30 p-5">
        <div class="flex items-start justify-between gap-3">
            <div class="flex items-center gap-2 text-fl-gold-ink">
                <Boxes class="size-5" />
                <span class="text-xs tracking-[0.2em] uppercase">{{
                    modelName ?? 'Legacy Plate'
                }}</span>
            </div>
            <Badge variant="outline" :class="className">{{ label }}</Badge>
        </div>

        <p class="mt-4 text-lg font-semibold text-foreground">
            {{
                engravingDisplayName ?? eventName ?? $t('Evento por confirmar')
            }}
        </p>
        <p class="text-sm text-muted-foreground">
            {{ eventName ?? editionName ?? $t('Evento no asignado')
            }}<span v-if="raceName"> · {{ raceName }}</span>
        </p>
        <p v-if="helperText" class="mt-2 text-xs text-muted-foreground/80">
            {{ helperText }}
        </p>

        <div
            v-if="serialNumber || legacyCode"
            class="mt-4 flex items-center justify-between text-xs text-muted-foreground/80"
        >
            <span>{{ serialNumber ?? $t('Sin folio') }}</span>
            <span v-if="legacyCode" class="flex items-center gap-1"
                ><Nfc class="size-3.5" /> {{ legacyCode }}</span
            >
        </div>
    </div>
</template>
