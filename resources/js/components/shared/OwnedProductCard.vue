<script setup lang="ts">
/**
 * "MI EQUIPO" — the Digital Closet (brief §84-§93/§103): what the athlete
 * actually owns (Trisuit, FAST T1 Socks, Chill Band, racepack, etc.), no
 * PII, no tracking yet (brief §111).
 */
import { Package, QrCode } from '@lucide/vue';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';

const props = defineProps<{
    product: string;
    variant: string | null;
    imageUrl: string | null;
    status: string;
    acquiredAt: string | null;
    assetCode: string | null;
}>();

const labels: Record<string, string> = {
    unclaimed: 'Sin reclamar',
    assigned: 'Asignado',
    active: 'Activo',
    revoked: 'Revocado',
};

const classes: Record<string, string> = {
    unclaimed: 'border-white/20 text-white/50',
    assigned: 'border-amber-500/30 text-amber-400',
    active: 'border-emerald-500/30 text-emerald-400',
    revoked: 'border-red-500/30 text-red-400',
};

const label = computed(() => labels[props.status] ?? props.status);
const className = computed(
    () => classes[props.status] ?? 'border-white/20 text-white/50',
);
</script>

<template>
    <div
        class="overflow-hidden rounded-2xl border border-white/10 bg-fl-graphite/30"
    >
        <div class="flex aspect-square items-center justify-center bg-fl-black">
            <img
                v-if="imageUrl"
                :src="imageUrl"
                :alt="product"
                class="size-full object-cover"
            />
            <Package v-else class="size-10 text-white/10" />
        </div>
        <div class="p-4">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <p class="font-medium text-white">{{ product }}</p>
                    <p v-if="variant" class="text-sm text-white/40">
                        {{ variant }}
                    </p>
                </div>
                <Badge variant="outline" :class="className">{{ label }}</Badge>
            </div>
            <div
                class="mt-3 flex items-center justify-between text-xs text-white/40"
            >
                <span v-if="acquiredAt">Adquirido {{ acquiredAt }}</span>
                <span v-if="assetCode" class="flex items-center gap-1"
                    ><QrCode class="size-3.5" /> {{ assetCode }}</span
                >
            </div>
        </div>
    </div>
</template>
