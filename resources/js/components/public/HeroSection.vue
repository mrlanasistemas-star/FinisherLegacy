<script setup lang="ts">
/**
 * Full-bleed cinematic hero: video as the base scene, with the Legacy
 * Plate itself as the hero's second visual layer on wide desktop (xl+),
 * floating in the section's own right-side gutter — it never shrinks the
 * text column to make room for itself (that was tried and made the H1 read
 * cramped; the plate just occupies space the text wasn't using). The H1
 * container is intentionally wide (max-w-5xl) so "TU META TERMINA." /
 * "TU HISTORIA NO." each render as one confident line instead of wrapping.
 */
import { Link } from '@inertiajs/vue3';
import type { InertiaLinkProps } from '@inertiajs/vue3';
import MagneticButton from '@/components/public/MagneticButton.vue';
import HeroPlateFeature from '@/components/public/media/HeroPlateFeature.vue';
import HomeHeroMedia from '@/components/public/media/HomeHeroMedia.vue';
import ScrollCueButton from '@/components/public/ScrollCueButton.vue';
import { Button } from '@/components/ui/button';

defineProps<{
    title: string;
    subtitle: string;
    primaryLabel: string;
    primaryHref: NonNullable<InertiaLinkProps['href']>;
    secondaryLabel: string;
    secondaryHref: NonNullable<InertiaLinkProps['href']>;
    tertiaryLabel?: string;
    tertiaryHref?: NonNullable<InertiaLinkProps['href']>;
}>();
</script>

<template>
    <section
        class="relative flex min-h-[100svh] min-h-screen items-center overflow-hidden bg-fl-black"
    >
        <!-- Cinematic scene: video → poster photo → CSS scene cascade, see
             public/media/home/hero/README.md for the asset contract. -->
        <HomeHeroMedia />

        <!-- Giant background numerals — art direction, not a data table
             (brand system §23.5): the race stats become part of the scene
             instead of sitting in a tidy row. -->
        <div
            class="legacy-numeric pointer-events-none absolute inset-0 hidden overflow-hidden opacity-[0.07] select-none lg:block"
            aria-hidden="true"
        >
            <span
                class="absolute -top-10 -right-16 text-[16rem] leading-none font-black tracking-tighter text-white xl:text-[20rem]"
                >42.195</span
            >
            <span
                class="absolute bottom-10 -left-10 text-[9rem] leading-none font-black tracking-tighter text-white xl:text-[11rem]"
                >03:42:18</span
            >
        </div>

        <HeroPlateFeature />

        <div
            class="relative mx-auto w-full max-w-5xl px-4 py-28 pt-32 sm:px-6 lg:px-8 2xl:max-w-6xl"
        >
            <div class="flex flex-col items-start gap-8">
                <span
                    class="inline-flex items-center gap-2 text-xs font-semibold tracking-[0.4em] text-fl-gold-soft uppercase"
                >
                    <span
                        class="h-px w-6 bg-fl-gold-soft/60"
                        aria-hidden="true"
                    />
                    Finisher Legacy
                </span>

                <!-- Sized as a stepped ladder, not a vw-fluid clamp: a
                     fluid clamp scales with the VIEWPORT, but this
                     container caps out at max-w-5xl/6xl — past that cap the
                     text kept growing past its own box and wrapped each
                     line into 2-3 sub-lines. Each step below was checked
                     against this container's actual available width so
                     "TU META TERMINA." / "TU HISTORIA NO." always render
                     as exactly one line each from `sm:` up. -->
                <h1
                    class="text-3xl leading-[0.95] font-black tracking-tight text-white sm:text-4xl sm:whitespace-nowrap md:text-5xl lg:text-6xl xl:text-7xl"
                >
                    <template
                        v-for="(line, index) in title.split('\n')"
                        :key="index"
                    >
                        <span
                            class="fl-hero-line inline-block"
                            :class="{ 'text-fl-gold-soft': index === 1 }"
                            :style="{ animationDelay: `${index * 140}ms` }"
                            >{{ line }}</span
                        >
                        <br v-if="index < title.split('\n').length - 1" />
                    </template>
                </h1>

                <p class="max-w-lg text-lg leading-relaxed text-white/70">
                    {{ subtitle }}
                </p>

                <div class="flex flex-col gap-3 sm:flex-row">
                    <MagneticButton>
                        <Button
                            as-child
                            size="lg"
                            class="bg-fl-gold px-8 text-base text-fl-black hover:bg-fl-gold-soft"
                        >
                            <Link :href="primaryHref">{{ primaryLabel }}</Link>
                        </Button>
                    </MagneticButton>
                    <Button
                        as-child
                        size="lg"
                        variant="outline"
                        class="border-white/30 bg-transparent px-8 text-base text-white hover:bg-white/5 hover:text-white"
                    >
                        <Link :href="secondaryHref">{{ secondaryLabel }}</Link>
                    </Button>
                </div>

                <p class="text-sm text-white/60">
                    Tu Legacy ID te acompaña carrera tras carrera.
                    <Link
                        v-if="tertiaryLabel && tertiaryHref"
                        :href="tertiaryHref"
                        class="ml-2 font-semibold text-fl-gold-soft underline-offset-4 hover:text-fl-gold hover:underline"
                    >
                        {{ tertiaryLabel }}
                    </Link>
                </p>
            </div>
        </div>

        <div class="absolute inset-x-0 bottom-6 hidden justify-center sm:flex">
            <ScrollCueButton />
        </div>
    </section>
</template>

<style scoped>
/* Headline lines rise and clip in on load, staggered per line — a small
   entrance beat, never the mechanism that makes the H1 visible at all.
   Base state is fully visible; the animation only applies inside
   @supports for a browser that can actually run it (brief item C5/C6) —
   a browser without clip-path support, or any other reason this rule
   doesn't apply, still renders the headline immediately. */
.fl-hero-line {
    opacity: 1;
}

@supports (clip-path: inset(0 0 0 0)) {
    .fl-hero-line {
        animation: fl-hero-line-in 700ms cubic-bezier(0.16, 1, 0.3, 1) both;
    }

    @keyframes fl-hero-line-in {
        from {
            opacity: 0;
            transform: translateY(0.4em);
            clip-path: inset(0 0 100% 0);
        }
        to {
            opacity: 1;
            transform: translateY(0);
            clip-path: inset(0 0 0 0);
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .fl-hero-line {
            animation: none;
        }
    }
}
</style>
