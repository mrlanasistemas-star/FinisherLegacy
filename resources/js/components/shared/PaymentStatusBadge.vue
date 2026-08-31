<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';

const props = defineProps<{ status: string }>();

const labels: Record<string, string> = {
    pending: 'Pendiente',
    authorized: 'Autorizado',
    paid: 'Pagado',
    failed: 'Fallido',
    refunded: 'Reembolsado',
    partially_refunded: 'Reembolso parcial',
    cancelled: 'Cancelado',
};

const classes: Record<string, string> = {
    pending: 'border-amber-500/30 text-amber-400',
    authorized: 'border-sky-500/30 text-sky-400',
    paid: 'border-emerald-500/30 text-emerald-400',
    failed: 'border-red-500/30 text-red-400',
    refunded: 'border-white/20 text-white/50',
    partially_refunded: 'border-orange-500/30 text-orange-400',
    cancelled: 'border-red-500/30 text-red-400',
};

const label = computed(() => labels[props.status] ?? props.status);
const className = computed(
    () => classes[props.status] ?? 'border-white/20 text-white/50',
);
</script>

<template>
    <Badge variant="outline" :class="className">{{ label }}</Badge>
</template>
