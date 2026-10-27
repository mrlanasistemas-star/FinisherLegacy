<script setup lang="ts">
/**
 * Home hero — the concept in one screen: "El recuerdo físico de tu logro.
 * Tu historia vive dentro." over the real start-line footage.
 *
 * Media: the looping video only plays ≥md and without reduced motion
 * (mobile gets the poster — no heavy autoplay on phones); it pauses when
 * the hero leaves the viewport. The poster is an absolutely-filled
 * <picture>, so nothing shifts while it loads (no CLS). The tag on the
 * right is the illustrative TagVisualizer (no tag photography yet).
 */
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import { useIntersectionObserver, useMediaQuery } from '@vueuse/core';
import { computed, onMounted, ref, useTemplateRef } from 'vue';
import TagVisualizer from '@/components/tag/TagVisualizer.vue';
import { useReducedMotion } from '@/composables/useReducedMotion';
import { ACTIVATE_URL } from '@/config/public-links';
import { howItWorks } from '@/routes';
import { index as collectionsIndex } from '@/routes/collections';

const props = defineProps<{
    /** First real collection, if any — the drawn tag wears its name. */
    featured?: {
        name: string;
        subtitle: string | null;
        year: number | null;
        accent: string | null;
    } | null;
}>();

const POSTER = '/media/home/hero/finisher-hero-poster.webp';
const POSTER_MOBILE = '/media/home/hero/finisher-hero-poster-mobile.webp';

const reducedMotion = useReducedMotion();
const wide = useMediaQuery('(min-width: 768px)');
const videoFailed = ref(false);
const mounted = ref(false);
const root = useTemplateRef<HTMLElement>('root');
const video = useTemplateRef<HTMLVideoElement>('video');

const playVideo = computed(
    () =>
        mounted.value &&
        wide.value &&
        !reducedMotion.value &&
        !videoFailed.value,
);

onMounted(() => {
    mounted.value = true;
});

useIntersectionObserver(root, ([entry]) => {
    if (!video.value) {
        return;
    }

    if (entry?.isIntersecting) {
        video.value.play().catch(() => {
            videoFailed.value = true;
        });
    } else {
        video.value.pause();
    }
});

const tag = computed(() => ({
    name: props.featured?.name ?? 'Tu colección',
    subtitle: props.featured?.subtitle ?? 'Destino · Disciplina',
    year: props.featured?.year ?? null,
    accent: props.featured?.accent ?? null,
}));
</script>

<template>
    <section
        ref="root"
        class="relative -mt-16 flex min-h-[100svh] items-end overflow-hidden bg-fl-black text-white lg:items-center"
        aria-labelledby="home-hero-title"
    >
        <!-- Media layer -->
        <div class="absolute inset-0" aria-hidden="true">
            <picture>
                <source media="(min-width: 768px)" :srcset="POSTER" />
                <img
                    :src="POSTER_MOBILE"
                    alt=""
                    width="750"
                    height="422"
                    fetchpriority="high"
                    decoding="async"
                    class="absolute inset-0 size-full object-cover object-[50%_60%]"
                />
            </picture>
            <video
                v-if="playVideo"
                ref="video"
                class="absolute inset-0 size-full object-cover"
                :poster="POSTER"
                autoplay
                muted
                loop
                playsinline
                preload="metadata"
                @error="videoFailed = true"
            >
                <source
                    src="/media/home/hero/finisher-hero-desktop.webm"
                    type="video/webm"
                />
                <source
                    src="/media/home/hero/finisher-hero-desktop.mp4"
                    type="video/mp4"
                />
            </video>
            <!-- Legibility: ink from the bottom-left, where the words are -->
            <div
                class="absolute inset-0 bg-gradient-to-t from-fl-black via-fl-black/70 to-fl-black/30"
            />
            <div
                class="absolute inset-0 bg-gradient-to-r from-fl-black/80 via-fl-black/20 to-transparent"
            />
        </div>

        <div
            class="fl-container relative grid w-full items-center gap-10 pt-28 pb-14 sm:pb-20 lg:grid-cols-12 lg:pt-32 lg:pb-24"
        >
            <div class="hero-copy lg:col-span-7">
                <p
                    class="flex items-center gap-3 text-[11px] font-semibold tracking-[0.24em] text-fl-gold uppercase"
                >
                    <span class="h-px w-10 bg-fl-gold" aria-hidden="true" />
                    Finisher Legacy Tag
                </p>
                <h1
                    id="home-hero-title"
                    class="mt-6 font-serif text-[clamp(2.5rem,5.4vw,4.75rem)] leading-[1.04] font-medium tracking-[-0.025em] text-white"
                >
                    El recuerdo físico de tu logro.
                    <span class="mt-1 block text-fl-gold-soft italic"
                        >Tu historia vive dentro.</span
                    >
                </h1>
                <p
                    class="mt-6 max-w-[34rem] text-base leading-relaxed text-white/75 sm:text-lg"
                >
                    Un tag de colección con NFC y QR. Lo llevas a casa, lo
                    activas y guardas ahí la historia de tu carrera.
                </p>

                <div class="mt-9 flex flex-wrap items-center gap-x-8 gap-y-3">
                    <Link
                        :href="collectionsIndex()"
                        class="inline-flex h-11 items-center justify-center gap-2 rounded-full bg-white px-6 text-sm font-semibold tracking-wide text-fl-black transition-colors hover:bg-fl-paper focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:ring-offset-2 focus-visible:ring-offset-fl-black focus-visible:outline-none"
                    >
                        Explorar colecciones
                        <ArrowRight class="size-4" aria-hidden="true" />
                    </Link>
                    <Link
                        :href="howItWorks()"
                        class="inline-flex min-h-11 items-center gap-2 text-sm font-medium text-white underline decoration-fl-gold decoration-1 underline-offset-[6px] transition-colors hover:decoration-2"
                    >
                        Cómo funciona
                    </Link>
                </div>
                <a
                    :href="ACTIVATE_URL"
                    class="mt-6 inline-flex min-h-11 items-center gap-2 text-sm text-white/65 underline-offset-4 transition-colors hover:text-white hover:underline"
                >
                    ¿Ya tienes tu tag?
                    <span class="font-semibold text-fl-gold-soft"
                        >Actívalo</span
                    >
                    <ArrowRight class="size-3.5" aria-hidden="true" />
                </a>
            </div>

            <div
                class="hero-tag relative mx-auto hidden w-full max-w-[17rem] lg:col-span-5 lg:block lg:max-w-[19rem]"
            >
                <TagVisualizer
                    :name="tag.name"
                    :subtitle="tag.subtitle"
                    :year="tag.year"
                    :accent="tag.accent"
                />
                <p
                    class="mt-4 text-center text-[11px] tracking-[0.18em] text-white/40 uppercase"
                >
                    Render ilustrativo
                </p>
            </div>
        </div>

        <!-- Scroll cue: a single hairline, no bouncing arrow -->
        <div
            class="pointer-events-none absolute inset-x-0 bottom-0 flex justify-center pb-5"
            aria-hidden="true"
        >
            <span
                class="hero-cue block h-10 w-px bg-gradient-to-b from-transparent to-fl-gold/70"
            />
        </div>
    </section>
</template>

<style scoped>
.hero-copy {
    animation: hero-rise 900ms cubic-bezier(0.16, 1, 0.3, 1) both;
}

.hero-tag {
    animation: hero-tag-in 1200ms 250ms cubic-bezier(0.16, 1, 0.3, 1) both;
}

@keyframes hero-rise {
    from {
        opacity: 0;
        transform: translateY(18px);
    }
}

@keyframes hero-tag-in {
    from {
        opacity: 0;
        transform: translateY(28px) rotate(-4deg);
    }
}

@media (prefers-reduced-motion: reduce) {
    .hero-copy,
    .hero-tag {
        animation: none;
    }
}
</style>
