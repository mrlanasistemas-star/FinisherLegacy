<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { InertiaLinkProps } from '@inertiajs/vue3';
import MagneticButton from '@/components/public/MagneticButton.vue';
import FinalMedia from '@/components/public/media/FinalMedia.vue';
import { Button } from '@/components/ui/button';

withDefaults(
    defineProps<{
        title: string;
        description?: string;
        primaryLabel: string;
        primaryHref: NonNullable<InertiaLinkProps['href']>;
        secondaryLabel?: string;
        secondaryHref?: NonNullable<InertiaLinkProps['href']>;
        /** Let the mascot peek from the corner, celebrating — optional,
         * never a boxed/framed section of its own (brand system §23). */
        showMascot?: boolean;
        /** Try a real closing video/photo behind the gradient — optional,
         * see public/media/home/final/README.md. */
        cinematic?: boolean;
    }>(),
    {
        showMascot: false,
        cinematic: false,
    },
);
</script>

<template>
    <section class="relative overflow-hidden border-t border-border">
        <div
            class="absolute inset-0 bg-gradient-to-br from-card via-background to-background"
        />
        <div
            class="absolute -top-32 left-1/2 h-64 w-[36rem] -translate-x-1/2 rounded-full bg-fl-gold/10 blur-3xl"
        />
        <div class="legacy-line absolute inset-x-0 top-0" />
        <FinalMedia v-if="cinematic" />

        <img
            v-if="showMascot"
            src="/images/brand/mascot/mascot-hero.png"
            alt=""
            aria-hidden="true"
            loading="lazy"
            decoding="async"
            class="fl-cta-mascot pointer-events-none absolute -bottom-2 left-2 hidden h-44 w-auto object-contain sm:block lg:left-10 lg:h-60"
        />

        <div
            class="relative mx-auto flex max-w-4xl flex-col items-center gap-6 px-4 py-24 text-center sm:px-6 lg:px-8"
        >
            <h2
                class="text-3xl font-bold tracking-tight text-foreground sm:text-4xl"
            >
                {{ title }}
            </h2>
            <p
                v-if="description"
                class="max-w-xl text-base text-muted-foreground sm:text-lg"
            >
                {{ description }}
            </p>
            <div class="mt-2 flex flex-col gap-3 sm:flex-row">
                <MagneticButton>
                    <Button
                        as-child
                        size="lg"
                        class="bg-fl-gold px-8 text-fl-black hover:bg-fl-gold-soft"
                    >
                        <Link :href="primaryHref">{{ primaryLabel }}</Link>
                    </Button>
                </MagneticButton>
                <Button
                    v-if="secondaryLabel && secondaryHref"
                    as-child
                    size="lg"
                    variant="outline"
                    class="border-foreground/15 bg-transparent px-8 text-foreground hover:bg-foreground/5 hover:text-foreground"
                >
                    <Link :href="secondaryHref">{{ secondaryLabel }}</Link>
                </Button>
            </div>
        </div>
    </section>
</template>

<style scoped>
.fl-cta-mascot {
    filter: drop-shadow(
        0 16px 32px color-mix(in srgb, var(--fl-gold) 30%, transparent)
    );
    animation: fl-cta-mascot-float 4.5s ease-in-out infinite;
}

@keyframes fl-cta-mascot-float {
    0%,
    100% {
        transform: translateY(0) rotate(-1deg);
    }
    50% {
        transform: translateY(-10px) rotate(1deg);
    }
}

@media (prefers-reduced-motion: reduce) {
    .fl-cta-mascot {
        animation: none;
    }
}
</style>
