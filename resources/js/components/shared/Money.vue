<script setup lang="ts">
/**
 * Renders a minor-unit integer amount (cents) as a formatted currency
 * string — the single place that knows minor-units-to-display math, so no
 * page divides by 100 by hand (brief §60/§151: money is never a float).
 */
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        minor: number;
        currency?: string;
        locale?: string;
    }>(),
    {
        currency: 'MXN',
        locale: 'es-MX',
    },
);

const formatted = computed(() =>
    new Intl.NumberFormat(props.locale, {
        style: 'currency',
        currency: props.currency,
    }).format(props.minor / 100),
);
</script>

<template>
    <span>{{ formatted }}</span>
</template>
