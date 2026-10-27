<script setup lang="ts">
/**
 * Home — the Finisher Legacy Tag, told in three layers: the OBJECT
 * (a premium physical memory), the STORY (tap the phone / scan the QR)
 * and PERMANENCE (the story keeps growing). Dark cinematic chapters
 * alternate with warm paper ones. Real data only: published collections
 * and tag products from HomeController; illustrative pieces (the drawn
 * tag, the example Legacy) are labelled as such.
 * Map: docs/architecture/public-experience.md.
 */
import { computed } from 'vue';
import CollectionsShowcase from '@/components/home/CollectionsShowcase.vue';
import FinishStory from '@/components/home/FinishStory.vue';
import HomeFinalCta from '@/components/home/HomeFinalCta.vue';
import HomeHero from '@/components/home/HomeHero.vue';
import LegacySequence from '@/components/home/LegacySequence.vue';
import RecipientsBlock from '@/components/home/RecipientsBlock.vue';
import StoryPreview from '@/components/home/StoryPreview.vue';
import TagObject from '@/components/home/TagObject.vue';
import TapDemo from '@/components/home/TapDemo.vue';
import SeoHead from '@/components/public/SeoHead.vue';
import type { PublicProductCard } from '@/types';
import type { CollectionCardData } from '@/types/collections';
import { collectionSubtitle } from '@/types/collections';

const props = defineProps<{
    collections: CollectionCardData[];
    tagProducts: PublicProductCard[];
}>();

/** The drawn tag wears the first real collection; otherwise a neutral one. */
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
        title="Finisher Legacy — El recuerdo físico de tu logro"
        description="El Finisher Legacy Tag: un tag de colección con NFC y QR para guardar la historia de tu carrera. Lo llevas a casa, lo activas y tu historia vive dentro."
        image="/images/brand/og-finisher-legacy.jpg"
        :json-ld="jsonLd"
    />

    <HomeHero :featured="collections.length ? tag : null" />
    <FinishStory />
    <TagObject :tag="tag" />
    <TapDemo :tag="tag" />
    <StoryPreview />
    <CollectionsShowcase
        :collections="collections"
        :tag-products="tagProducts"
    />
    <RecipientsBlock />
    <LegacySequence />
    <HomeFinalCta />
</template>
