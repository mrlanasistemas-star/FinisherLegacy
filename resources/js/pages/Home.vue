<script setup lang="ts">
/**
 * Home — the Finisher Legacy Tag, explained fast on a light page
 * (2026-11): a compact hero of five scenes (what it is → the tag → tap →
 * the story opens → CTA, see HomeHero.vue) instead of a long scroll story,
 * then the real collections, community, FAQ and the final CTA.
 *
 * Real data only: published collections and tag products from
 * HomeController; illustrative pieces (3D tag, phone, example story) are
 * labelled as such. Map: docs/architecture/public-experience.md.
 */
import CollectionsShowcase from '@/components/home/CollectionsShowcase.vue';
import CommunityTeaser from '@/components/home/CommunityTeaser.vue';
import HomeFaq from '@/components/home/HomeFaq.vue';
import HomeFinalCta from '@/components/home/HomeFinalCta.vue';
import HomeHero from '@/components/home/HomeHero.vue';
import SeoHead from '@/components/public/SeoHead.vue';
import type { PublicProductCard } from '@/types';
import type { CollectionCardData } from '@/types/collections';

defineProps<{
    collections: CollectionCardData[];
    tagProducts: PublicProductCard[];
}>();

const origin = typeof window === 'undefined' ? '' : window.location.origin;

const jsonLd = {
    '@context': 'https://schema.org',
    '@type': 'Organization',
    name: 'Finisher Legacy',
    slogan: 'Haz que perdure.',
    url: origin ? `${origin}/` : undefined,
    logo: origin ? `${origin}/images/brand/logo/fl-monograma.png` : undefined,
};
</script>

<template>
    <SeoHead
        title="Finisher Legacy — El tag que guarda la historia de tu carrera"
        description="El Finisher Legacy Tag: un tag de colección con chip NFC y correa. Lo acercas a tu celular y se abre la historia de tu carrera: tu tiempo, tus fotos y tus dedicatorias."
        image="/images/brand/og-finisher-legacy.png"
        :json-ld="jsonLd"
    />

    <div class="overflow-x-clip">
        <HomeHero :collections="collections" />
        <CollectionsShowcase
            :collections="collections"
            :tag-products="tagProducts"
        />
        <CommunityTeaser />
        <HomeFaq />
        <HomeFinalCta />
    </div>
</template>

<style>
/* Home-only finishes (2026-10-29). Prefixed so they never leak meaning. */
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

@media (prefers-reduced-motion: reduce) {
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
