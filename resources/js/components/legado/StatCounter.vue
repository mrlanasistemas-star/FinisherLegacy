<script setup lang="ts">
/**
 * One of the athlete's real totals (carreras, kilómetros, temporadas) with
 * a subtle count-up the first time it scrolls into view. Fail-open: the
 * server render and any no-JS / reduced-motion visit show the final number
 * — the animation only ever starts after the element is confirmed visible.
 */
import { useIntersectionObserver } from '@vueuse/core';
import { computed, onBeforeUnmount, ref, useTemplateRef, watch } from 'vue';
import { useReducedMotion } from '@/composables/useReducedMotion';

const props = withDefaults(
    defineProps<{
        value: number;
        label: string;
        decimals?: number;
        suffix?: string;
        hint?: string | null;
    }>(),
    { decimals: 0, suffix: '', hint: null },
);

const el = useTemplateRef<HTMLElement>('el');
const reducedMotion = useReducedMotion();
const shown = ref(props.value);
let frame: number | null = null;
let played = false;

const formatted = computed(() =>
    shown.value.toLocaleString('es-MX', {
        minimumFractionDigits: 0,
        maximumFractionDigits: props.decimals,
    }),
);

function play() {
    if (played || reducedMotion.value || props.value <= 0) {
        return;
    }

    played = true;
    const target = props.value;
    const start = performance.now();
    const duration = 1100;
    const factor = 10 ** props.decimals;

    const step = (now: number) => {
        const progress = Math.min((now - start) / duration, 1);
        const eased = 1 - Math.pow(1 - progress, 3);
        shown.value = Math.round(target * eased * factor) / factor;

        if (progress < 1) {
            frame = requestAnimationFrame(step);
        } else {
            shown.value = target;
        }
    };

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
    { threshold: 0.4 },
);

watch(
    () => props.value,
    (value) => {
        if (frame !== null) {
            cancelAnimationFrame(frame);
            frame = null;
        }

        shown.value = value;
    },
);

onBeforeUnmount(() => {
    if (frame !== null) {
        cancelAnimationFrame(frame);
    }

    shown.value = props.value;
});
</script>

<template>
    <div ref="el" class="min-w-0">
        <p
            class="legacy-numeric font-serif text-3xl leading-none font-medium tracking-tight break-words text-foreground tabular-nums sm:text-[2.5rem]"
        >
            {{ formatted
            }}<span
                v-if="suffix"
                class="ml-1 text-lg font-normal text-muted-foreground sm:text-xl"
                >{{ suffix }}</span
            >
        </p>
        <p class="mt-2 text-sm font-medium text-foreground">{{ label }}</p>
        <p v-if="hint" class="mt-0.5 text-xs text-muted-foreground">
            {{ hint }}
        </p>
    </div>
</template>
