<script setup lang="ts">
/**
 * Home hero — "Tu historia. / Tu legado." (owner spec §38-§39).
 *
 * The owner asked for the original photograph back (not the starting-blocks
 * film): the real finish-line celebration portrait — an athlete crossing the
 * line with their bib and medal. Because it is a PORTRAIT, the hero is a
 * split-editorial (UI System §7 module 5): warm-paper copy on one side, a tall
 * media panel holding the full portrait on the other, so the athlete + medal
 * are shown whole and never awkwardly cropped. On phones it stacks — the photo
 * is a full-bleed tall band on top, copy below — and stays immersive via svh.
 *
 * Motion is enhancement only and reduced-motion safe:
 *  - a slow scroll ken-burns (scale 1→1.06 + gentle drift) driven by
 *    `useParallax` (transform-only, rAF, offscreen-gated),
 *  - a choreographed entrance: the media reveals first, then the headline
 *    lines rise in sequence, then the CTAs, then the single gold "Conectado"
 *    chip (< ~1.2s total, pure CSS, disabled under prefers-reduced-motion).
 *
 * ONE floating accent only (the gold NFC chip); gold otherwise lives as a
 * hairline and a single word. The NFC/plate motif is NOT rebuilt here — it
 * lands once, in NfcMoment.
 */
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Nfc } from '@lucide/vue';
import { useTemplateRef } from 'vue';
import { Button } from '@/components/ui/button';
import { useParallax } from '@/composables/useParallax';
import { MEDIA } from '@/config/media';
import { howItWorks } from '@/routes';
import { index as eventsIndex } from '@/routes/events';
import { index as storeIndex } from '@/routes/store/products';

const photo = MEDIA.photo.celebration;

// Drives the scroll ken-burns on the portrait (writes `--sp` on the media panel).
const mediaEl = useTemplateRef<HTMLElement>('media');
useParallax(mediaEl);
</script>

<template>
    <section
        class="relative isolate grid overflow-hidden lg:min-h-[calc(100svh-4rem)] lg:grid-cols-[minmax(0,1fr)_minmax(0,1.05fr)]"
        aria-labelledby="hero-title"
    >
        <!-- COPY — warm paper / cream, editorial -->
        <div
            class="order-2 flex items-center bg-[linear-gradient(180deg,var(--fl-cream),var(--background))] lg:order-1"
        >
            <div
                class="w-full max-w-xl px-6 py-14 sm:px-10 sm:py-20 lg:max-w-lg lg:py-24 lg:pr-10 lg:pl-10 xl:max-w-xl xl:pl-16"
            >
                <div>
                    <p class="fl-hero-rise fl-eyebrow flex items-center gap-3">
                        <span class="h-px w-10 bg-fl-gold" aria-hidden="true" />
                        Finisher Legacy
                    </p>
                    <h1
                        id="hero-title"
                        class="fl-display mt-6 text-[clamp(2.8rem,7vw,5.5rem)] leading-[0.92] tracking-[-0.03em]"
                    >
                        <span class="fl-hero-rise block" style="--d: 120ms"
                            >Tu historia.</span
                        >
                        <span
                            class="fl-hero-rise block italic"
                            style="--d: 240ms"
                            >Tu legado.</span
                        >
                    </h1>
                    <p
                        class="fl-hero-rise mt-7 max-w-md text-lg leading-relaxed text-foreground/80 sm:text-xl"
                        style="--d: 360ms"
                    >
                        Corres, terminas y lo guardas. Tus carreras, tu medalla,
                        tus fotos y tu equipo, conectados en un solo lugar.
                    </p>
                    <div
                        class="fl-hero-rise mt-9 flex flex-wrap items-center gap-x-4 gap-y-3"
                        style="--d: 480ms"
                    >
                        <Button
                            as-child
                            size="lg"
                            class="h-12 rounded-full px-6"
                        >
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
        </div>

        <!-- MEDIA — the celebration portrait, shown whole in a tall panel -->
        <div
            ref="media"
            class="fl-hero-media relative order-1 h-[58svh] min-h-[22rem] overflow-hidden bg-fl-black lg:order-2 lg:h-auto lg:min-h-[calc(100svh-4rem)]"
        >
            <img
                :src="photo.src"
                :srcset="photo.srcset"
                sizes="(min-width: 1024px) 52vw, 100vw"
                :width="photo.width"
                :height="photo.height"
                :alt="photo.alt"
                fetchpriority="high"
                decoding="async"
                class="fl-hero-img size-full object-cover object-[50%_28%]"
            />
            <!-- Subtle scrim, bottom + inner edge only, for the floating chip -->
            <div
                class="pointer-events-none absolute inset-0 bg-[linear-gradient(to_top,rgb(23_23_20/0.45),transparent_42%)] lg:bg-[linear-gradient(to_right,rgb(23_23_20/0.28),transparent_30%),linear-gradient(to_top,rgb(23_23_20/0.4),transparent_38%)]"
                aria-hidden="true"
            />

            <!-- Single floating accent: the gold "Conectado" NFC chip -->
            <div
                class="fl-hero-chip pointer-events-none absolute bottom-5 left-5 flex items-center gap-2 rounded-full border border-fl-gold/40 bg-fl-black/55 px-3.5 py-2 text-white/90 backdrop-blur sm:bottom-7 sm:left-7"
                aria-hidden="true"
            >
                <Nfc class="size-4 text-fl-gold-soft" />
                <span
                    class="text-[11px] font-semibold tracking-[0.18em] uppercase"
                    >Conectado</span
                >
            </div>
        </div>
    </section>
</template>

<style scoped>
/* --- Scroll ken-burns on the portrait (transform-only, var-driven). --- */
.fl-hero-img {
    transform: none;
    will-change: transform;
}

@media (prefers-reduced-motion: no-preference) {
    .fl-hero-img {
        /* `--sp` runs 0 (at load) → -1 (scrolled a viewport up): scale up to
           1.06 and drift the portrait upward as the hero leaves. */
        transform: scale(calc(1 + var(--sp, 0) * -0.06))
            translateY(calc(var(--sp, 0) * 20px));
    }

    /* --- Choreographed entrance --- */
    .fl-hero-media {
        animation: fl-hero-media 900ms cubic-bezier(0.16, 1, 0.3, 1) both;
    }
    .fl-hero-rise {
        animation: fl-hero-rise 800ms cubic-bezier(0.16, 1, 0.3, 1) both;
        animation-delay: var(--d, 0ms);
    }
    .fl-hero-chip {
        animation: fl-hero-rise 700ms cubic-bezier(0.16, 1, 0.3, 1) both;
        animation-delay: 760ms;
    }
}

@keyframes fl-hero-media {
    from {
        opacity: 0;
        transform: scale(1.04);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
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
</style>
