<script setup lang="ts">
/**
 * Home hero — "Tu historia. / Tu legado." (owner spec §38-§39) as a
 * full-bleed cinematic frame (UI System §13): the real training film
 * (HomeHeroMedia: video → poster → CSS fallback, offscreen-pause and
 * reduced-motion already handled) washed warm, the oversized Fraunces
 * display over a paper scrim that only deepens under the text quadrant,
 * and ONE floating product layer (the phone showing a Legacy profile).
 *
 * The NFC/plate motif is intentionally NOT repeated here — it lands once,
 * powerfully, in NfcMoment. Motion is enhancement only: the copy rises in
 * a short choreographed sequence (media first, headline lines, then the
 * CTAs), all pure CSS and disabled under prefers-reduced-motion.
 */
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import PhoneProfile from '@/components/public/home/PhoneProfile.vue';
import HomeHeroMedia from '@/components/public/media/HomeHeroMedia.vue';
import { Button } from '@/components/ui/button';
import { howItWorks } from '@/routes';
import { index as eventsIndex } from '@/routes/events';
import { index as storeIndex } from '@/routes/store/products';
</script>

<template>
    <section
        class="relative isolate flex min-h-[calc(100svh-4rem)] items-center overflow-hidden lg:items-end"
        aria-labelledby="hero-title"
    >
        <!-- Cinematic media: warm-washed film/poster, full-bleed -->
        <HomeHeroMedia />

        <!-- Paper scrim: deepens the bottom-left text quadrant, lets the
             photograph breathe toward the top-right (no flat gray). Sits
             above the media (DOM order) but below the content. -->
        <div
            class="pointer-events-none absolute inset-0 z-0 bg-[linear-gradient(60deg,var(--background)_0%,color-mix(in_srgb,var(--background)_55%,transparent)_38%,transparent_72%)]"
            aria-hidden="true"
        />

        <div
            class="fl-container relative z-10 w-full pt-28 pb-14 sm:pt-32 lg:pt-24 lg:pb-24"
        >
            <div class="max-w-2xl lg:max-w-3xl">
                <p class="fl-hero-rise fl-eyebrow flex items-center gap-3">
                    <span class="h-px w-10 bg-fl-gold" aria-hidden="true" />
                    Finisher Legacy
                </p>
                <h1
                    id="hero-title"
                    class="fl-display mt-6 text-[clamp(3rem,9vw,7rem)] leading-[0.9] tracking-[-0.03em]"
                >
                    <span class="fl-hero-rise block" style="--d: 120ms"
                        >Tu historia.</span
                    >
                    <span class="fl-hero-rise block italic" style="--d: 240ms"
                        >Tu legado.</span
                    >
                </h1>
                <p
                    class="fl-hero-rise mt-7 max-w-md text-lg leading-relaxed text-foreground/80 sm:text-xl"
                    style="--d: 360ms"
                >
                    Corres, terminas y lo guardas. Tus carreras, tu medalla, tus
                    fotos y tu equipo, conectados en un solo lugar.
                </p>
                <div
                    class="fl-hero-rise mt-9 flex flex-wrap items-center gap-x-4 gap-y-3"
                    style="--d: 480ms"
                >
                    <Button as-child size="lg" class="h-12 rounded-full px-6">
                        <Link :href="howItWorks()">
                            Descubrir Finisher Legacy
                            <ArrowRight class="size-4" />
                        </Link>
                    </Button>
                    <Button
                        as-child
                        size="lg"
                        variant="outline"
                        class="h-12 rounded-full bg-card/80 px-6 backdrop-blur"
                    >
                        <Link :href="eventsIndex()">Explorar eventos</Link>
                    </Button>
                    <Link
                        :href="storeIndex()"
                        class="group inline-flex min-h-11 items-center gap-1.5 text-sm font-semibold text-foreground"
                    >
                        <span
                            class="underline decoration-fl-gold decoration-2 underline-offset-[6px] transition-colors group-hover:decoration-foreground"
                            >Ver tienda</span
                        >
                        <ArrowRight
                            class="size-4 transition-transform group-hover:translate-x-0.5"
                        />
                    </Link>
                </div>
            </div>
        </div>

        <!-- Single floating product layer: the digital profile (wide
             screens only, so it never crowds the headline) -->
        <div
            class="fl-hero-phone pointer-events-none absolute top-1/2 right-[4vw] z-10 hidden w-[clamp(13rem,16vw,16rem)] -translate-y-1/2 xl:block"
            aria-hidden="true"
        >
            <PhoneProfile glow />
        </div>
    </section>
</template>

<style scoped>
.fl-hero-rise {
    animation: fl-hero-rise 800ms cubic-bezier(0.16, 1, 0.3, 1) both;
    animation-delay: var(--d, 0ms);
}
.fl-hero-phone {
    animation: fl-hero-rise 900ms cubic-bezier(0.16, 1, 0.3, 1) both;
    animation-delay: 620ms;
}

@keyframes fl-hero-rise {
    from {
        opacity: 0;
        translate: 0 20px;
    }
    to {
        opacity: 1;
        translate: 0 0;
    }
}

@media (prefers-reduced-motion: reduce) {
    .fl-hero-rise,
    .fl-hero-phone {
        animation: none;
    }
}
</style>
