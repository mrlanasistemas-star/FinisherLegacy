<script setup lang="ts">
/**
 * A real count (seguidores, publicaciones…) that counts up the first time
 * it scrolls into view. Fail-open: the server render, reduced motion and
 * no-JS all show the final number; `null` renders an em dash.
 */
import { useIntersectionObserver } from '@vueuse/core';
import { computed, onBeforeUnmount, ref, useTemplateRef, watch } from 'vue';
import { useReducedMotion } from '@/composables/useReducedMotion';
import { useI18n } from '@/i18n';

const props = defineProps<{ value: number | null }>();

const el = useTemplateRef<HTMLElement>('el');
const reducedMotion = useReducedMotion();
const shown = ref(props.value ?? 0);
const { regional } = useI18n();
const formatter = computed(
    () => new Intl.NumberFormat(regional.value, { notation: 'compact' }),
);
let frame: number | null = null;
let played = false;

const text = computed(() =>
    props.value === null
        ? '—'
        : formatter.value.format(Math.round(shown.value)),
);

function play() {
    const target = props.value ?? 0;

    if (played || reducedMotion.value || target <= 0) {
        return;
    }

    played = true;
    const start = performance.now();
    const duration = Math.min(1400, 600 + target * 8);

    const step = (now: number) => {
        const progress = Math.min((now - start) / duration, 1);
        shown.value = target * (1 - Math.pow(1 - progress, 3));

        if (progress < 1) {
            frame = requestAnimationFrame(step);
        } else {
            shown.value = target;
            frame = null;
        }
    };

    shown.value = 0;
    frame = requestAnimationFrame(step);
}

const { stop } = useIntersectionObserver(
    el,
    ([entry]) => {
        if (entry?.isIntersecting) {
            play();
            stop();
        }
    },
    { threshold: 0.5 },
);

watch(
    () => props.value,
    (value) => {
        if (frame !== null) {
            cancelAnimationFrame(frame);
            frame = null;
        }

        shown.value = value ?? 0;
    },
);

onBeforeUnmount(() => {
    if (frame !== null) {
        cancelAnimationFrame(frame);
    }
});
</script>

<template>
    <span ref="el" class="tabular-nums">{{ text }}</span>
</template>
