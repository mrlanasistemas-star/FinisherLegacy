<script setup lang="ts">
/**
 * Marketing hero shared by the public landing pages: gold eyebrow, a big
 * Montserrat headline whose optional `accent` line is set in gold italics,
 * a lede, an `actions` slot (CTA buttons) and a `visual` slot (phone, tag,
 * photo…) that sits on the right from lg up and below the copy on phones.
 * A soft gold aura drifts behind it (static under reduced motion).
 */
import { vReveal } from '@/composables/useReveal';

withDefaults(
    defineProps<{
        eyebrow?: string;
        title: string;
        /** Second headline line, in gold italics. */
        accent?: string;
        lede?: string;
        align?: 'left' | 'center';
        /** `paper` (default) or a warmer `cream` wash. */
        tone?: 'paper' | 'cream';
    }>(),
    { align: 'left', tone: 'paper' },
);
</script>

<template>
    <section
        class="mk-hero relative isolate overflow-hidden"
        :class="tone === 'cream' ? 'bg-fl-cream/60' : 'bg-background'"
    >
        <div class="mk-hero__aura" aria-hidden="true" />
        <div class="mk-hero__aura mk-hero__aura--b" aria-hidden="true" />
        <div
            class="fl-container relative grid items-center gap-12 py-14 sm:py-20 lg:gap-16 lg:py-24"
            :class="
                $slots.visual && align === 'left'
                    ? 'lg:grid-cols-[minmax(0,1.05fr)_minmax(0,0.95fr)]'
                    : ''
            "
        >
            <div
                class="flex flex-col"
                :class="
                    align === 'center'
                        ? 'mx-auto max-w-3xl items-center text-center'
                        : 'items-start'
                "
            >
                <p
                    v-if="eyebrow"
                    v-reveal
                    class="fl-eyebrow inline-flex items-center gap-3"
                >
                    <span class="h-px w-8 bg-fl-gold" aria-hidden="true" />
                    {{ eyebrow }}
                </p>
                <h1
                    v-reveal="{ delay: 60 }"
                    class="fl-display mt-5 text-[2.4rem] leading-[1.04] sm:text-6xl lg:text-[4.1rem]"
                >
                    {{ title }}
                    <span
                        v-if="accent"
                        class="block font-medium text-fl-gold-ink italic"
                        >{{ accent }}</span
                    >
                </h1>
                <p
                    v-if="lede"
                    v-reveal="{ delay: 140 }"
                    class="mt-6 max-w-xl text-base leading-relaxed text-muted-foreground sm:text-lg"
                >
                    {{ lede }}
                </p>
                <div
                    v-if="$slots.actions"
                    v-reveal="{ delay: 220 }"
                    class="mt-8 flex flex-wrap items-center gap-3"
                    :class="align === 'center' ? 'justify-center' : ''"
                >
                    <slot name="actions" />
                </div>
                <div
                    v-if="$slots.default"
                    v-reveal="{ delay: 300 }"
                    class="mt-8 w-full"
                >
                    <slot />
                </div>
            </div>
            <div
                v-if="$slots.visual"
                v-reveal="{ delay: 180, y: 32 }"
                class="relative flex justify-center"
            >
                <slot name="visual" />
            </div>
        </div>
    </section>
</template>

<style scoped>
.mk-hero__aura {
    position: absolute;
    z-index: -1;
    top: -22%;
    right: -8%;
    width: min(46rem, 90vw);
    aspect-ratio: 1;
    border-radius: 9999px;
    background: radial-gradient(
        closest-side,
        rgb(201 174 139 / 0.3),
        rgb(201 174 139 / 0.08) 55%,
        transparent
    );
    animation: mk-drift 16s ease-in-out infinite alternate;
    pointer-events: none;
}
.mk-hero__aura--b {
    top: auto;
    right: auto;
    bottom: -40%;
    left: -14%;
    width: min(34rem, 80vw);
    background: radial-gradient(
        closest-side,
        rgb(230 209 181 / 0.35),
        transparent
    );
    animation-duration: 22s;
    animation-direction: alternate-reverse;
}
@keyframes mk-drift {
    from {
        transform: translate3d(0, 0, 0) scale(1);
    }
    to {
        transform: translate3d(-4%, 5%, 0) scale(1.08);
    }
}
@media (prefers-reduced-motion: reduce) {
    .mk-hero__aura {
        animation: none;
    }
}
</style>
