<script setup lang="ts">
/**
 * Renders a minor-unit integer amount (cents) as a formatted currency
 * string — the single place that knows minor-units-to-display math, so no
 * page divides by 100 by hand (brief §60/§151: money is never a float).
 * Numbers follow the active language; the currency never changes.
 */
import { computed } from 'vue';
import { useI18n } from '@/i18n';

const props = withDefaults(
    defineProps<{
        minor: number;
        currency?: string;
        locale?: string;
    }>(),
    {
        currency: 'MXN',
    },
);

const { regional } = useI18n();

const formatted = computed(() =>
    new Intl.NumberFormat(props.locale ?? regional.value, {
        style: 'currency',
        currency: props.currency,
    }).format(props.minor / 100),
);
</script>

<template>
    <span>{{ formatted }}</span>
</template>
