<script setup lang="ts">
/**
 * Renders a minor-unit integer amount (cents) as a formatted currency
 * string — the single place that knows minor-units-to-display math, so no
 * page divides by 100 by hand (brief §60/§151: money is never a float).
 * Always "$1,200.00 MXN": the digits follow the active language, the ISO
 * code always follows the amount (see `formatMoney` in `@/i18n`).
 */
import { computed } from 'vue';
import { formatMoney, useI18n } from '@/i18n';

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
    formatMoney(props.minor, props.currency, props.locale ?? regional.value),
);
</script>

<template>
    <span>{{ formatted }}</span>
</template>
