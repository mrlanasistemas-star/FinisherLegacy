<script setup lang="ts">
/**
 * Floating "back to top" button — appears once the visitor has scrolled
 * past the hero, so long scenes (Home, Cómo funciona) always have a fast
 * way back up. Purely a scroll shortcut, not a nav landmark.
 *
 * Stacks above the mascot guide (same right edge, centered on its bubble)
 * and steps aside while the guide's speech card is open.
 */
import { ArrowUp } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { guideState } from '@/components/mascot/guideStore';
import { useReducedMotion } from '@/composables/useReducedMotion';

const visible = ref(false);
const prefersReducedMotion = useReducedMotion();

/** Room taken by the guide below us: its 64px bubble, or the 40px tab. */
const guideStack = computed(() =>
    !guideState.mounted ? '0px' : guideState.hidden ? '3.25rem' : '4.75rem',
);

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
            v-if="visible && !guideState.open"
            type="button"
            aria-label="Volver arriba"
            :style="{ '--fl-guide-stack': guideStack }"
            class="fl-focus-glow fixed right-[26px] bottom-[calc(1rem+var(--fl-guide-stack)+var(--fl-bottom-bar,0px)+var(--fl-site-tabbar,0px))] z-40 flex size-11 items-center justify-center rounded-full border border-border bg-card text-foreground shadow-[0_8px_24px_-12px_rgb(23_23_20/0.25)] transition-all duration-200 hover:scale-105 hover:border-fl-gold hover:text-fl-gold-ink active:scale-95 sm:right-[34px] sm:bottom-[calc(1.5rem+var(--fl-guide-stack)+var(--fl-bottom-bar,0px)+var(--fl-site-tabbar,0px))]"
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
