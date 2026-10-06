<script setup lang="ts">
/**
 * "MI EQUIPO" — the Digital Closet (brief §84-§93/§103): what the athlete
 * actually owns (Trisuit, FAST T1 Socks, Chill Band, racepack, etc.), no
 * PII, no tracking yet (brief §111).
 */
import { Package, QrCode } from '@lucide/vue';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';

type UsageHistoryItem = {
    event_participant_id: number;
    event: string | null;
    edition: string | null;
};

const props = defineProps<{
    product: string;
    variant: string | null;
    imageUrl: string | null;
    status: string;
    acquiredAt: string | null;
    assetCode: string | null;
    usageHistory?: UsageHistoryItem[];
}>();

const labels: Record<string, string> = {
    unclaimed: 'Sin reclamar',
    assigned: 'Asignado',
    active: 'Activo',
    revoked: 'Revocado',
};

const classes: Record<string, string> = {
    unclaimed: 'border-foreground/15 text-muted-foreground',
    assigned: 'border-amber-500/30 text-amber-700',
    active: 'border-emerald-500/30 text-emerald-700',
    revoked: 'border-red-500/30 text-red-700',
};

const label = computed(() => labels[props.status] ?? props.status);
const className = computed(
    () => classes[props.status] ?? 'border-foreground/15 text-muted-foreground',
);
</script>

<template>
    <div class="overflow-hidden rounded-2xl border border-border bg-card/30">
        <div
            class="flex aspect-square items-center justify-center bg-background"
        >
            <img
                v-if="imageUrl"
                :src="imageUrl"
                :alt="product"
                class="size-full object-cover"
            />
            <Package v-else class="size-10 text-muted-foreground/80" />
        </div>
        <div class="p-4">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <p class="font-medium text-foreground">{{ product }}</p>
                    <p v-if="variant" class="text-sm text-muted-foreground/80">
                        {{ variant }}
                    </p>
                </div>
                <Badge variant="outline" :class="className">{{ label }}</Badge>
            </div>
            <div
                class="mt-3 flex items-center justify-between text-xs text-muted-foreground/80"
            >
                <span v-if="acquiredAt">Adquirido {{ acquiredAt }}</span>
                <span v-if="assetCode" class="flex items-center gap-1"
                    ><QrCode class="size-3.5" /> {{ assetCode }}</span
                >
            </div>
            <div
                v-if="usageHistory?.length"
                class="mt-3 border-t border-border pt-3"
            >
                <p
                    class="text-[10px] tracking-wide text-muted-foreground/80 uppercase"
                >
                    Usado en
                </p>
                <p
                    v-for="entry in usageHistory"
                    :key="entry.event_participant_id"
                    class="mt-1 truncate text-xs text-muted-foreground"
                >
                    {{ entry.event ?? entry.edition ?? 'Evento' }}
                </p>
            </div>
        </div>
    </div>
</template>
