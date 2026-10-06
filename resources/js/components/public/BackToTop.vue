<script setup lang="ts">
/**
 * Floating "back to top" button — appears once the visitor has scrolled
 * past the hero, so long scenes (Home, Cómo funciona) always have a fast
 * way back up. Purely a scroll shortcut, not a nav landmark.
 */
import { ArrowUp } from '@lucide/vue';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { useReducedMotion } from '@/composables/useReducedMotion';

const visible = ref(false);
const prefersReducedMotion = useReducedMotion();

function handleScroll() {
    visible.value = window.scrollY > window.innerHeight * 0.6;
}

function scrollToTop() {
    window.scrollTo({
        top: 0,
        behavior: prefersReducedMotion.value ? 'auto' : 'smooth',
    });
}

onMounted(() => {
    handleScroll();
    window.addEventListener('scroll', handleScroll, { passive: true });
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', handleScroll);
});
</script>

<template>
    <Transition name="fl-back-to-top">
        <button
            v-if="visible"
            type="button"
            aria-label="Volver arriba"
            class="fl-focus-glow fixed right-5 bottom-5 z-40 flex size-11 items-center justify-center rounded-full border border-border bg-card text-foreground shadow-[0_8px_24px_-12px_rgb(23_23_20/0.25)] transition-all duration-200 hover:scale-105 hover:border-fl-gold hover:text-fl-gold-ink active:scale-95 sm:right-8 sm:bottom-8"
            @click="scrollToTop"
        >
            <ArrowUp class="size-5" />
        </button>
    </Transition>
</template>

<style scoped>
.fl-back-to-top-enter-active,
.fl-back-to-top-leave-active {
    transition:
        opacity 200ms ease,
        transform 200ms ease;
}
.fl-back-to-top-enter-from,
.fl-back-to-top-leave-to {
    opacity: 0;
    transform: translateY(8px);
}

@media (prefers-reduced-motion: reduce) {
    .fl-back-to-top-enter-active,
    .fl-back-to-top-leave-active {
        transition: none;
    }
}
</style>
