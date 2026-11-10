<script setup lang="ts">
/**
 * Home — everything Finisher Legacy is, told as one product story on a
 * light page (2026-11 redesign). The hero (HomeHero.vue, unchanged) opens
 * with the tag; the rest of the page explains the whole ecosystem:
 *
 *   sports strip → ecosystem bento → how it works → memories →
 *   smart portrait frame → training / «Envíame ánimo» (carbon) →
 *   store showcase → community → event photos → app (carbon) → FAQ → CTA
 *
 * Real data only (HomeController): published collections, tag products
 * and the priced products of the store. Every mock-up on the page (phone
 * screens, feed, player, result card) is an illustration and says so; no
 * testimonials, counts or people are invented. Shared motion rules live in
 * the <style> block below and in components/home/homeMotion.ts.
 * Map: docs/architecture/public-experience.md.
 */
import { computed } from 'vue';
import HomeApp from '@/components/home/HomeApp.vue';
import HomeCommunity from '@/components/home/HomeCommunity.vue';
import HomeEcosystem from '@/components/home/HomeEcosystem.vue';
import HomeFaq from '@/components/home/HomeFaq.vue';
import HomeFinalCta from '@/components/home/HomeFinalCta.vue';
import HomeHero from '@/components/home/HomeHero.vue';
import HomeHowItWorks from '@/components/home/HomeHowItWorks.vue';
import HomeMemories from '@/components/home/HomeMemories.vue';
import HomePhotos from '@/components/home/HomePhotos.vue';
import HomePortrait from '@/components/home/HomePortrait.vue';
import HomeProducts from '@/components/home/HomeProducts.vue';
import HomeSports from '@/components/home/HomeSports.vue';
import HomeTraining from '@/components/home/HomeTraining.vue';
import SeoHead from '@/components/public/SeoHead.vue';
import type { TagArt } from '@/components/three/tagStageContext';
import { useI18n } from '@/i18n';
import type { PublicProductCard } from '@/types';
import type { CollectionCardData } from '@/types/collections';
import { collectionTagArts } from '@/types/collections';

const { t } = useI18n();

const props = withDefaults(
    defineProps<{
        collections: CollectionCardData[];
        tagProducts: PublicProductCard[];
        gearProducts?: PublicProductCard[];
    }>(),
    { gearProducts: () => [] },
);

/** One tag artwork per published collection; a neutral tag when none. */
const arts = computed<TagArt[]>(() =>
    props.collections.length
        ? props.collections.map((c) => collectionTagArts(c)[0])
        : [
              {
                  name: t('Tu colección'),
                  subtitle: null,
                  year: null,
                  accent: null,
              },
          ],
);

const origin = typeof window === 'undefined' ? '' : window.location.origin;

const jsonLd = computed(() => ({
    '@context': 'https://schema.org',
    '@type': 'Organization',
    name: 'Finisher Legacy',
    slogan: t('Haz que perdure.'),
    url: origin ? `${origin}/` : undefined,
    logo: origin ? `${origin}/images/brand/logo/fl-monograma.png` : undefined,
}));
</script>

<template>
    <SeoHead
        :title="
            $t('Finisher Legacy — El tag que guarda la historia de tu carrera')
        "
        :description="
            $t(
                'Finisher Legacy: un tag de colección con NFC que abre el recuerdo de tu carrera, más comunidad, mensajes de ánimo para entrenar, fotos de evento, tienda y app para atletas.',
            )
        "
        image="/images/brand/og-finisher-legacy.png"
        :json-ld="jsonLd"
    />

    <div class="overflow-x-clip">
        <HomeHero :collections="collections" />
        <HomeSports />
        <HomeEcosystem :art="arts[0]" :gear-products="gearProducts" />
        <HomeHowItWorks :art="arts[0]" />
        <HomeMemories />
        <HomePortrait />
        <HomeTraining />
        <HomeProducts
            :collections="collections"
            :tag-products="tagProducts"
            :gear-products="gearProducts"
        />
        <HomeCommunity />
        <HomePhotos />
        <HomeApp />
        <HomeFaq />
        <HomeFinalCta :arts="arts" />
    </div>
</template>

<style>
/* Home-only finishes. Prefixed so they never leak meaning. */
.home-grain {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='160' height='160'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='2' stitchTiles='stitch'/%3E%3CfeColorMatrix values='0 0 0 0 0.09 0 0 0 0 0.09 0 0 0 0 0.08 0 0 0 0.09 0'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
    background-size: 160px 160px;
}

/* A soft light sweep across primary buttons on hover. */
.home-shine {
    position: relative;
    overflow: hidden;
    isolation: isolate;
}
.home-shine::after {
    content: '';
    position: absolute;
    inset: 0;
    z-index: -1;
    background: linear-gradient(
        110deg,
        transparent 30%,
        rgb(255 255 255 / 0.22) 50%,
        transparent 70%
    );
    transform: translateX(-120%);
    transition: transform 700ms cubic-bezier(0.16, 1, 0.3, 1);
}
.home-shine:hover::after {
    transform: translateX(120%);
}

/* Cards that lift a little under the pointer. */
.home-lift {
    transition:
        transform 400ms cubic-bezier(0.16, 1, 0.3, 1),
        box-shadow 400ms cubic-bezier(0.16, 1, 0.3, 1),
        border-color 300ms ease;
}
.home-lift:hover {
    transform: translateY(-3px);
}

/*
  Looping illustrations (2026-11). A section marked with v-stage
  (.home-stage) only animates while it is on screen (.is-in).
*/
.home-stage:not(.is-in) *,
.home-stage:not(.is-in) *::before,
.home-stage:not(.is-in) *::after {
    animation-play-state: paused !important;
}

/* Gentle vertical float; --r keeps a resting rotation. */
.home-float {
    animation: home-float 7s ease-in-out infinite;
}
@keyframes home-float {
    0%,
    100% {
        transform: translate3d(0, -6px, 0) rotate(var(--r, 0deg));
    }
    50% {
        transform: translate3d(0, 6px, 0) rotate(var(--r, 0deg));
    }
}

/* NFC ripple ring: position/size it with utilities. */
.home-ripple {
    position: absolute;
    border-radius: 9999px;
    border: 1px solid color-mix(in srgb, var(--fl-gold) 80%, transparent);
    opacity: 0;
    animation: home-ripple 3s cubic-bezier(0.2, 0.6, 0.3, 1) infinite;
    pointer-events: none;
}
@keyframes home-ripple {
    0% {
        transform: scale(0.45);
        opacity: 0.9;
    }
    100% {
        transform: scale(1.7);
        opacity: 0;
    }
}

/* Audio bars: children scale on Y. */
.home-eq > span {
    transform-origin: center;
    animation: home-eq 1.2s ease-in-out infinite;
}
@keyframes home-eq {
    0%,
    100% {
        transform: scaleY(0.35);
    }
    50% {
        transform: scaleY(1);
    }
}

/* A heart that rises and fades (--dx drifts it sideways). */
@keyframes home-heart {
    0% {
        transform: translate3d(0, 0, 0) scale(0.6);
        opacity: 0;
    }
    15% {
        opacity: 1;
    }
    100% {
        transform: translate3d(var(--dx, 0), -64px, 0) scale(1.15);
        opacity: 0;
    }
}

@media (prefers-reduced-motion: reduce) {
    .home-stage *,
    .home-stage *::before,
    .home-stage *::after {
        animation: none !important;
    }
    .home-ripple {
        opacity: 0.35;
    }
    .home-shine::after {
        display: none;
    }
    .home-lift,
    .home-lift:hover {
        transition: none;
        transform: none;
    }
}
</style>
