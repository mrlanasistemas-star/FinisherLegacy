<script setup lang="ts">
/**
 * Animated voice waveform (decorative): a row of bars that breathe like a
 * playing voice message. Pure CSS, scaleY only; static bars under
 * prefers-reduced-motion. `tone` picks gold bars for dark or light
 * surfaces.
 */
withDefaults(
    defineProps<{
        bars?: number;
        tone?: 'gold' | 'ink' | 'light';
        playing?: boolean;
    }>(),
    { bars: 28, tone: 'gold', playing: true },
);

// Deterministic pseudo-random heights so SSR and client render the same.
const height = (i: number) => 28 + ((i * 37 + (i % 5) * 19) % 68);
const delay = (i: number) => ((i * 53) % 900) - 900;
</script>

<template>
    <span
        class="mk-wave"
        :class="[`mk-wave--${tone}`, playing ? 'mk-wave--playing' : '']"
        aria-hidden="true"
    >
        <span
            v-for="i in bars"
            :key="i"
            :style="{
                height: `${height(i)}%`,
                animationDelay: `${delay(i)}ms`,
            }"
        />
    </span>
</template>

<style scoped>
.mk-wave {
    display: inline-flex;
    height: 2rem;
    align-items: center;
    gap: 3px;
}
.mk-wave span {
    width: 3px;
    border-radius: 9999px;
    background: #c9ae8b;
    transform-origin: center;
}
.mk-wave--ink span {
    background: #0b0b0c;
}
.mk-wave--light span {
    background: rgb(255 255 255 / 0.85);
}
.mk-wave--playing span {
    animation: mk-wave 1.1s ease-in-out infinite alternate;
}
@keyframes mk-wave {
    from {
        transform: scaleY(0.35);
    }
    to {
        transform: scaleY(1);
    }
}
@media (prefers-reduced-motion: reduce) {
    .mk-wave--playing span {
        animation: none;
    }
}
</style>
