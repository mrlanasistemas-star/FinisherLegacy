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
        /**
         * Optional schema.org structured data (e.g. a SportsEvent) rendered
         * as a single <script type="application/ld+json"> inside the head.
         * Pass a plain object of already-real values — never fabricate data.
         */
        jsonLd?: Record<string, unknown> | null;
    }>(),
    {
        image: null,
        type: 'website',
        noindex: false,
        jsonLd: null,
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
        <!--
            Rendered as a dynamic component (not a literal <script> element) on
            purpose: a literal script tag in an SFC template trips Vue's
            "side-effect tags are ignored in client templates" compiler error.
            :is="'script'" produces a real ld+json script in the <Head> without
            that error. v-text sets the serialized structured data as its body.
        -->
        <!-- eslint-disable vue/no-v-text-v-html-on-component -- v-text is correct here: :is="'script'" is a native element, and v-text sets raw ld+json text (a slot child would HTML-escape "<" and corrupt the JSON). -->
        <component
            v-if="jsonLd"
            :is="'script'"
            type="application/ld+json"
            v-text="JSON.stringify(jsonLd)"
        />
        <!-- eslint-enable vue/no-v-text-v-html-on-component -->
        <slot />
    </Head>
</template>
