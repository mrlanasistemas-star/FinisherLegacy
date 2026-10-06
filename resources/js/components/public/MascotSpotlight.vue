<script setup lang="ts">
/**
 * The mascot as a free-floating character, not a bordered/blurred card
 * (brand system §23). Only one pose asset ships today (mascot-hero.jpeg,
 * already alpha-masked in FinisherMascot.vue) — this component can't
 * fabricate new poses without real generated assets, so it leans on a
 * gentle float + fade-in instead of a pose change to feel alive. Used on
 * Home right before the final CTA, and on the prerregistro confirmation
 * page — both genuinely celebratory moments.
 */
import FinisherMascot from '@/components/public/FinisherMascot.vue';

withDefaults(
    defineProps<{
        title: string;
        description?: string;
    }>(),
    {},
);
</script>

<template>
    <div
        class="flex flex-col items-center gap-5 text-center sm:flex-row sm:gap-8 sm:text-left"
    >
        <FinisherMascot variant="hero" class="fl-mascot-float shrink-0" />

        <div class="max-w-md">
            <h3 class="text-xl font-bold text-foreground">{{ title }}</h3>
            <p v-if="description" class="mt-2 text-sm text-muted-foreground">
                {{ description }}
            </p>
            <div v-if="$slots.default" class="mt-4">
                <slot />
            </div>
        </div>
    </div>
</template>

<style scoped>
.fl-mascot-float {
    animation: fl-mascot-float 4.5s ease-in-out infinite;
}

@keyframes fl-mascot-float {
    0%,
    100% {
        transform: translateY(0) rotate(-1deg);
    }
    50% {
        transform: translateY(-10px) rotate(1deg);
    }
}

@media (prefers-reduced-motion: reduce) {
    .fl-mascot-float {
        animation: none;
    }
}
</style>
