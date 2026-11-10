<script setup lang="ts">
/**
 * The Portarretrato inteligente drawn in CSS (strategy deck p. 9): an
 * acrylic slab with four standoffs on a black base engraved "FL · ACERCA
 * TU CELULAR", standing on a desk. The print preview goes in the default
 * slot; nothing here is an image, so it scales to any width.
 */
defineProps<{
    /** Print width / height (20 × 25 cm → 0.8). */
    aspect: number;
    /** Dims the print while a new render is on its way. */
    loading?: boolean;
    monogram: string;
}>();
</script>

<template>
    <div class="frame-scene">
        <div class="frame-wall" aria-hidden="true" />
        <div class="frame-desk" aria-hidden="true" />

        <div class="frame-object">
            <div class="frame-acrylic">
                <span
                    v-for="corner in ['tl', 'tr', 'bl', 'br']"
                    :key="corner"
                    class="frame-standoff"
                    :class="`frame-standoff--${corner}`"
                    aria-hidden="true"
                />
                <div
                    class="frame-print"
                    :class="{ 'frame-print--loading': loading }"
                    :style="{ aspectRatio: String(aspect) }"
                >
                    <slot />
                </div>
                <span class="frame-glare" aria-hidden="true" />
            </div>

            <div class="frame-base">
                <img :src="monogram" alt="" class="frame-base-mark" />
                <span class="frame-base-rule" aria-hidden="true" />
                <span class="frame-base-text">Acerca tu celular</span>
            </div>
            <div class="frame-shadow" aria-hidden="true" />
        </div>
    </div>
</template>

<style scoped>
.frame-scene {
    position: relative;
    overflow: hidden;
    border-radius: var(--fl-radius-hero);
    padding: clamp(1.25rem, 5vw, 2.5rem) clamp(1.25rem, 6vw, 3rem)
        clamp(2.25rem, 7vw, 3.5rem);
    isolation: isolate;
}

/* Warm wall with a soft window light, like the deck's render. */
.frame-wall {
    position: absolute;
    inset: 0;
    z-index: -2;
    background:
        radial-gradient(
            120% 80% at 12% 0%,
            rgb(255 255 255 / 0.9),
            transparent 60%
        ),
        linear-gradient(160deg, #f4eee4 0%, #e9dfd0 55%, #d9ccb8 100%);
}

/* The desk: a walnut strip the frame stands on. */
.frame-desk {
    position: absolute;
    inset: auto 0 0;
    z-index: -1;
    height: 17%;
    background:
        linear-gradient(180deg, rgb(255 255 255 / 0.14), transparent 22%),
        linear-gradient(
            90deg,
            #4a3526 0%,
            #5d4532 38%,
            #47301f 72%,
            #563f2c 100%
        );
    box-shadow: 0 -1px 0 rgb(255 255 255 / 0.25);
}

.frame-object {
    position: relative;
    margin-inline: auto;
    width: min(100%, 23rem);
}

.frame-acrylic {
    position: relative;
    padding: 7.5%;
    border-radius: 0.5rem;
    background: linear-gradient(
        135deg,
        rgb(255 255 255 / 0.55),
        rgb(255 255 255 / 0.18) 45%,
        rgb(255 255 255 / 0.4)
    );
    border: 1px solid rgb(255 255 255 / 0.85);
    box-shadow:
        inset 0 0 0 1px rgb(255 255 255 / 0.35),
        inset 0 0 24px rgb(255 255 255 / 0.35),
        0 1px 1px rgb(11 11 12 / 0.08),
        0 30px 50px -28px rgb(11 11 12 / 0.55);
    backdrop-filter: blur(2px);
}

.frame-print {
    position: relative;
    overflow: hidden;
    width: 100%;
    background: var(--fl-black);
    box-shadow:
        0 0 0 1px rgb(11 11 12 / 0.12),
        0 6px 16px -8px rgb(11 11 12 / 0.5);
    transition:
        opacity 220ms ease,
        filter 220ms ease;
}

.frame-print--loading {
    opacity: 0.72;
    filter: saturate(0.9);
}

.frame-print :deep(img) {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.frame-standoff {
    position: absolute;
    width: 4.6%;
    aspect-ratio: 1;
    border-radius: 9999px;
    background: radial-gradient(
        circle at 32% 28%,
        #ffffff 0%,
        #d8d8d8 34%,
        #8f8f8f 72%,
        #b9b9b9 100%
    );
    box-shadow:
        0 1px 2px rgb(11 11 12 / 0.45),
        inset 0 0 0 1px rgb(255 255 255 / 0.5);
}

.frame-standoff--tl {
    top: 2%;
    left: 1.6%;
}
.frame-standoff--tr {
    top: 2%;
    right: 1.6%;
}
.frame-standoff--bl {
    bottom: 2%;
    left: 1.6%;
}
.frame-standoff--br {
    bottom: 2%;
    right: 1.6%;
}

/* A diagonal reflection across the acrylic. */
.frame-glare {
    pointer-events: none;
    position: absolute;
    inset: 0;
    border-radius: inherit;
    background: linear-gradient(
        112deg,
        transparent 0 34%,
        rgb(255 255 255 / 0.2) 40%,
        rgb(255 255 255 / 0.04) 47%,
        transparent 52%
    );
    mix-blend-mode: screen;
}

.frame-base {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.7rem;
    margin: -1px -3% 0;
    padding: 0.7rem 1rem 0.75rem;
    border-radius: 0.25rem 0.25rem 0.375rem 0.375rem;
    background: linear-gradient(180deg, #232325 0%, #0b0b0c 38%, #060607 100%);
    box-shadow:
        inset 0 1px 0 rgb(255 255 255 / 0.2),
        0 14px 24px -14px rgb(11 11 12 / 0.7);
}

.frame-base-mark {
    height: 0.95rem;
    width: auto;
}

.frame-base-rule {
    width: 1px;
    height: 0.9rem;
    background: color-mix(in srgb, var(--fl-gold) 60%, transparent);
}

.frame-base-text {
    font-family: var(--font-brand, inherit);
    font-size: 0.5625rem;
    font-weight: 600;
    letter-spacing: 0.3em;
    text-transform: uppercase;
    color: var(--fl-gold-soft);
    white-space: nowrap;
}

.frame-shadow {
    position: absolute;
    inset: auto 2% -0.6rem;
    height: 1.1rem;
    border-radius: 50%;
    background: radial-gradient(closest-side, rgb(11 11 12 / 0.5), transparent);
    filter: blur(4px);
}
</style>
