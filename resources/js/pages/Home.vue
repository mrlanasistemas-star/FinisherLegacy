<script setup lang="ts">
/**
 * Home — the Finisher Legacy Tag, explained on a light, warm-paper page
 * (2026-10-29): hero → cómo funciona (3 steps) → dos caras → real
 * collections → what the story page looks like → community → FAQ → final
 * CTA. No dark sections.
 *
 * ONE 3D tag travels with the scroll (TagStage): each section places a
 * <TagAnchor> in a column it keeps free and the tag flies between them,
 * turning, pulsing or changing collection to illustrate each section
 * (see components/three/TagStage.vue). Phones / reduced motion get static
 * posters outside the hero.
 *
 * Real data only: published collections and tag products from
 * HomeController; illustrative pieces (3D/SVG tag, example story) are
 * labelled as such. Map: docs/architecture/public-experience.md.
 */
import { computed } from 'vue';
import CollectionsShowcase from '@/components/home/CollectionsShowcase.vue';
import CommunityTeaser from '@/components/home/CommunityTeaser.vue';
import HomeFaq from '@/components/home/HomeFaq.vue';
import HomeFinalCta from '@/components/home/HomeFinalCta.vue';
import HomeHero from '@/components/home/HomeHero.vue';
import HomeSteps from '@/components/home/HomeSteps.vue';
import StoryPreview from '@/components/home/StoryPreview.vue';
import TagFaces from '@/components/home/TagFaces.vue';
import SeoHead from '@/components/public/SeoHead.vue';
import TagStage from '@/components/three/TagStage.vue';
import type { PublicProductCard } from '@/types';
import type { CollectionCardData } from '@/types/collections';
import { collectionSubtitle } from '@/types/collections';

const props = defineProps<{
    collections: CollectionCardData[];
    tagProducts: PublicProductCard[];
}>();

/** The drawn / 3D tag wears the first real collection; otherwise a neutral one. */
const tag = computed(() => {
    const first = props.collections[0];

    return first
        ? {
              name: first.name,
              subtitle: collectionSubtitle(first),
              year: first.year,
              accent: first.accent_color,
          }
        : {
              name: 'Tu colección',
              subtitle: 'Destino · Disciplina',
              year: null,
              accent: null,
          };
});

const origin = typeof window === 'undefined' ? '' : window.location.origin;

const jsonLd = {
    '@context': 'https://schema.org',
    '@type': 'Organization',
    name: 'Finisher Legacy',
    url: origin ? `${origin}/` : undefined,
    logo: origin ? `${origin}/images/brand/logo/logo-mark.png` : undefined,
};
</script>

<template>
    <SeoHead
        title="Finisher Legacy — El tag que guarda la historia de tu carrera"
        description="El Finisher Legacy Tag: un tag de colección con NFC. Te llega listo con su correa; lo acercas a tu celular y se abre la historia de tu carrera."
        image="/images/brand/og-finisher-legacy.jpg"
        :json-ld="jsonLd"
    />

    <TagStage :art="tag">
        <div class="overflow-x-clip">
            <HomeHero :tag="tag" />
            <HomeSteps />
            <TagFaces :tag="tag" />
            <CollectionsShowcase
                :collections="collections"
                :tag-products="tagProducts"
            />
            <StoryPreview :tag="tag" />
            <CommunityTeaser />
            <HomeFaq />
            <HomeFinalCta />
        </div>
    </TagStage>
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
