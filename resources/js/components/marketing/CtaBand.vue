<script setup lang="ts">
/**
 * Closing call-to-action band for public pages: a Negro Carbono block with
 * the official "MAKE IT LAST" signature IMAGE (never typed), a headline,
 * a line of copy and an `actions` slot. Style the slot's links with the
 * `mk-cta-primary` / `mk-cta-secondary` classes defined here.
 */
import { vReveal } from '@/composables/useReveal';

withDefaults(
    defineProps<{
        title: string;
        accent?: string;
        text?: string;
        /** Show the MAKE IT LAST signature above the headline. */
        signature?: boolean;
    }>(),
    { signature: true },
);
</script>

<template>
    <section class="fl-container py-12 sm:py-16">
        <div
            v-reveal="{ y: 28 }"
            class="mk-cta relative isolate overflow-hidden rounded-hero bg-fl-black px-6 py-14 text-center text-white sm:px-12 sm:py-20"
        >
            <div class="mk-cta__glow" aria-hidden="true" />
            <img
                v-if="signature"
                src="/images/brand/logo/fl-firma-make-it-last.png"
                alt="MAKE IT LAST"
                width="1200"
                height="98"
                loading="lazy"
                class="mx-auto h-auto w-40 opacity-90 sm:w-52"
            />
            <h2
                class="mx-auto mt-7 max-w-3xl font-serif text-[2rem] leading-[1.08] font-semibold tracking-tight text-white sm:text-5xl"
            >
                {{ title }}
                <span
                    v-if="accent"
                    class="block font-medium text-fl-gold-soft italic"
                    >{{ accent }}</span
                >
            </h2>
            <p
                v-if="text"
                class="mx-auto mt-5 max-w-xl text-[0.95rem] leading-relaxed text-white/70 sm:text-base"
            >
                {{ text }}
            </p>
            <div
                v-if="$slots.actions"
                class="mt-9 flex flex-wrap items-center justify-center gap-3"
            >
                <slot name="actions" />
            </div>
        </div>
    </section>
</template>

<style scoped>
.mk-cta::before {
    content: '';
    position: absolute;
    inset: 0 0 auto;
    height: 1px;
    background: linear-gradient(
        90deg,
        transparent,
        #c9ae8b 35%,
        #e6d1b5 50%,
        #c9ae8b 65%,
        transparent
    );
    opacity: 0.8;
}
.mk-cta__glow {
    position: absolute;
    z-index: -1;
    left: 50%;
    top: -60%;
    width: min(60rem, 140%);
    aspect-ratio: 1;
    border-radius: 9999px;
    background: radial-gradient(
        closest-side,
        rgb(201 174 139 / 0.22),
        transparent
    );
    transform: translateX(-50%);
    animation: mk-cta-breathe 9s ease-in-out infinite alternate;
    pointer-events: none;
}
@keyframes mk-cta-breathe {
    to {
        transform: translateX(-50%) scale(1.15);
        opacity: 0.7;
    }
}
/* Buttons placed on the carbon band. */
.mk-cta :deep(.mk-cta-primary),
.mk-cta :deep(.mk-cta-secondary) {
    display: inline-flex;
    min-height: 3rem;
    align-items: center;
    gap: 0.5rem;
    border-radius: 9999px;
    padding-inline: 1.75rem;
    font-size: 0.9375rem;
    font-weight: 600;
    transition:
        transform 280ms cubic-bezier(0.16, 1, 0.3, 1),
        background-color 160ms ease,
        border-color 160ms ease;
}
.mk-cta :deep(.mk-cta-primary) {
    background: #e6d1b5;
    color: #0b0b0c;
}
.mk-cta :deep(.mk-cta-primary:hover) {
    background: #fff;
    transform: translateY(-2px);
}
.mk-cta :deep(.mk-cta-secondary) {
    border: 1px solid rgb(255 255 255 / 0.28);
    color: #fff;
}
.mk-cta :deep(.mk-cta-secondary:hover) {
    border-color: #c9ae8b;
    transform: translateY(-2px);
}
.mk-cta :deep(a:focus-visible) {
    outline: 2px solid #e6d1b5;
    outline-offset: 3px;
}
@media (prefers-reduced-motion: reduce) {
    .mk-cta__glow {
        animation: none;
    }
    .mk-cta :deep(.mk-cta-primary),
    .mk-cta :deep(.mk-cta-secondary) {
        transition: none;
        transform: none !important;
    }
}
</style>
