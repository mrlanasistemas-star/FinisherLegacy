<script setup lang="ts">
/**
 * Per-page SEO in one place: <title>, meta description, canonical and the
 * OpenGraph/Twitter overrides of the site-wide defaults in app.blade.php
 * (Inertia dedupes by name/property, so these replace — never duplicate —
 * the defaults). Canonical comes from the existing useCanonicalUrl().
 */
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useCanonicalUrl } from '@/composables/useCanonicalUrl';

const props = withDefaults(
    defineProps<{
        title: string;
        description: string;
        image?: string | null;
        type?: 'website' | 'profile' | 'article' | 'product';
        noindex?: boolean;
    }>(),
    {
        image: null,
        type: 'website',
        noindex: false,
    },
);

const canonicalUrl = useCanonicalUrl();

// The document title gets " | Finisher Legacy" from app.ts; social cards
// need the full branded string themselves.
const socialTitle = computed(() =>
    props.title.includes('Finisher Legacy')
        ? props.title
        : `${props.title} | Finisher Legacy`,
);
</script>

<template>
    <Head :title="title">
        <meta name="description" :content="description" />
        <meta v-if="noindex" name="robots" content="noindex" />
        <link v-if="canonicalUrl" rel="canonical" :href="canonicalUrl" />
        <meta property="og:type" :content="type" />
        <meta property="og:title" :content="socialTitle" />
        <meta property="og:description" :content="description" />
        <meta v-if="canonicalUrl" property="og:url" :content="canonicalUrl" />
        <meta v-if="image" property="og:image" :content="image" />
        <meta name="twitter:title" :content="socialTitle" />
        <meta name="twitter:description" :content="description" />
        <meta v-if="image" name="twitter:image" :content="image" />
        <slot />
    </Head>
</template>
