<script setup lang="ts">
/**
 * CSS phone frame for REAL app/web screenshots (never mock data). Pass a
 * `src` (the screenshot fills the screen, top-aligned) or use the default
 * slot for a custom screen. `float` adds a slow hover-in-the-air drift and
 * `tilt` a slight rotation; both are static under prefers-reduced-motion.
 */
withDefaults(
    defineProps<{
        src?: string | null;
        alt?: string;
        float?: boolean;
        tilt?: 'none' | 'left' | 'right';
        /** Eager for above-the-fold heroes. */
        eager?: boolean;
    }>(),
    { src: null, alt: '', float: false, tilt: 'none', eager: false },
);
</script>

<template>
    <div
        class="mk-phone"
        :class="[
            float ? 'mk-phone--float' : '',
            tilt !== 'none' ? `mk-phone--${tilt}` : '',
        ]"
    >
        <div class="mk-phone__body">
            <span class="mk-phone__island" aria-hidden="true" />
            <div class="mk-phone__screen">
                <img
                    v-if="src"
                    :src="src"
                    :alt="alt"
                    width="540"
                    height="1130"
                    :loading="eager ? 'eager' : 'lazy'"
                    decoding="async"
                    class="block size-full object-cover object-top"
                />
                <slot v-else />
            </div>
        </div>
    </div>
</template>

<style scoped>
.mk-phone {
    --mk-tilt: 0deg;
    width: 100%;
    max-width: 17.5rem;
    transform: rotate(var(--mk-tilt));
}
.mk-phone--left {
    --mk-tilt: -4deg;
}
.mk-phone--right {
    --mk-tilt: 4deg;
}
.mk-phone__body {
    position: relative;
    aspect-ratio: 9 / 19;
    border-radius: 2.6rem;
    padding: 0.55rem;
    background: linear-gradient(145deg, #2e2e33, #0b0b0c 45%, #1e1e21);
    box-shadow:
        0 0 0 1px rgb(255 255 255 / 0.08) inset,
        0 2px 0 0 rgb(201 174 139 / 0.35) inset,
        0 40px 70px -34px rgb(11 11 12 / 0.55),
        0 18px 30px -22px rgb(140 116 84 / 0.5);
}
.mk-phone--float .mk-phone__body {
    animation: mk-phone-float 7s ease-in-out infinite;
}
.mk-phone__island {
    position: absolute;
    z-index: 2;
    top: 1.05rem;
    left: 50%;
    width: 26%;
    height: 1.05rem;
    border-radius: 9999px;
    background: #0b0b0c;
    transform: translateX(-50%);
}
.mk-phone__screen {
    position: relative;
    height: 100%;
    overflow: hidden;
    border-radius: 2.1rem;
    background: #f8f7f3;
    padding-top: 2.3rem;
}
@keyframes mk-phone-float {
    0%,
    100% {
        transform: translate3d(0, 0, 0);
    }
    50% {
        transform: translate3d(0, -12px, 0);
    }
}
@media (prefers-reduced-motion: reduce) {
    .mk-phone--float .mk-phone__body {
        animation: none;
    }
}
</style>
