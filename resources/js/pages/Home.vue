<script setup lang="ts">
/**
 * Home — the Finisher Legacy Tag, explained on a light, warm-paper page
 * (2026-10-29): hero with the interactive 3D tag → cómo funciona (3 steps)
 * → frente y reverso → real collections → what the story page looks like
 * → community → FAQ → final CTA. No dark sections.
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
        description="El Finisher Legacy Tag: un tag de colección con NFC y QR. Te llega listo con su correa; lo acercas a tu celular y se abre la historia de tu carrera."
        image="/images/brand/og-finisher-legacy.jpg"
        :json-ld="jsonLd"
    />

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
</template>
