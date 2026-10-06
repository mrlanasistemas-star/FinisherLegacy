<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';

const props = defineProps<{ status: string }>();

const labels: Record<string, string> = {
    pending: 'Pendiente',
    confirmed: 'Confirmado',
    cancelled: 'Cancelado',
    completed: 'Completado',
};

const classes: Record<string, string> = {
    pending: 'border-amber-500/30 text-amber-700',
    confirmed: 'border-sky-500/30 text-sky-700',
    cancelled: 'border-red-500/30 text-red-700',
    completed: 'border-emerald-500/30 text-emerald-700',
};

const label = computed(() => labels[props.status] ?? props.status);
const className = computed(
    () => classes[props.status] ?? 'border-foreground/15 text-muted-foreground',
);
</script>

<template>
    <Badge variant="outline" :class="className">{{ label }}</Badge>
</template>
