<script setup lang="ts">
/**
 * A clickable scroll-down cue — nudges the visitor to the next scene
 * instead of leaving discovery entirely to chance. Scrolls by ~85% of the
 * viewport rather than to a named anchor, so it works from anywhere without
 * wiring section ids together.
 */
import { ChevronDown } from '@lucide/vue';
import { useReducedMotion } from '@/composables/useReducedMotion';

withDefaults(
    defineProps<{
        label?: string;
    }>(),
    {
        label: 'Descubre más',
    },
);

const prefersReducedMotion = useReducedMotion();

function scrollNext() {
    window.scrollBy({
        top: window.innerHeight * 0.85,
        behavior: prefersReducedMotion.value ? 'auto' : 'smooth',
    });
}
</script>

<template>
    <button
        type="button"
        class="fl-focus-glow group flex flex-col items-center gap-2 text-muted-foreground/80 transition-colors hover:text-fl-gold-ink"
        @click="scrollNext"
    >
        <span class="text-[10px] font-semibold tracking-[0.3em] uppercase">{{
            label
        }}</span>
        <ChevronDown class="size-4 animate-bounce motion-reduce:animate-none" />
    </button>
</template>
