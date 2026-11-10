<script setup lang="ts">
/**
 * A Finisher Legacy Tag product (or any product of a collection) as a
 * tile: a tag product shows its design render (front, flips to the back
 * on hover or with a button); other products their photo, else the
 * illustrative tag.
 * Price and availability are exactly what the server resolved.
 */
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import Money from '@/components/shared/Money.vue';
import TagFlipCard from '@/components/tag/TagFlipCard.vue';
import TagVisualizer from '@/components/tag/TagVisualizer.vue';
import { show as productShow } from '@/routes/store/products';
import type { TagDesign } from '@/types/collections';
import { tagArt } from '@/types/collections';

const props = withDefaults(
    defineProps<{
        name: string;
        slug: string;
        tagline?: string | null;
        imageUrl?: string | null;
        imageAlt?: string | null;
        priceMinor?: number | null;
        compareAtMinor?: number | null;
        currency?: string;
        available?: boolean;
        statusLabel?: string | null;
        /** Artwork for the drawn tag when there is no photo. */
        artName?: string;
        artSubtitle?: string | null;
        artYear?: number | null;
        accent?: string | null;
        /** Tag products: their `tag_design` → front/back flip render. */
        design?: TagDesign | null;
        tone?: 'dark' | 'light';
    }>(),
    {
        tagline: null,
        imageUrl: null,
        imageAlt: null,
        priceMinor: null,
        compareAtMinor: null,
        currency: 'MXN',
        available: true,
        statusLabel: null,
        artName: undefined,
        artSubtitle: null,
        artYear: null,
        accent: null,
        design: null,
        tone: 'light',
    },
);

/** A tag product always shows its design (front ↔ back), photo or not. */
const designArt = computed(() =>
    props.design
        ? tagArt(
              {
                  name: props.artName ?? props.name,
                  year: props.artYear,
                  accent_color: props.accent,
              },
              props.design,
          )
        : null,
);
</script>

<template>
    <Link
        :href="productShow(slug)"
        class="group block rounded-media focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:ring-offset-4 focus-visible:outline-none"
    >
        <div
            class="fl-card-hover relative aspect-square overflow-hidden rounded-media border border-transparent"
            :class="
                tone === 'dark'
                    ? 'bg-fl-graphite'
                    : 'bg-card ring-1 ring-border'
            "
        >
            <div
                v-if="designArt"
                class="absolute inset-x-0 top-[5%] mx-auto w-[36%]"
            >
                <TagFlipCard :art="designArt" />
            </div>
            <img
                v-else-if="imageUrl"
                :src="imageUrl"
                :alt="imageAlt ?? name"
                loading="lazy"
                decoding="async"
                class="absolute inset-0 size-full object-cover transition-transform duration-[1200ms] ease-out group-hover:scale-[1.06] motion-reduce:transition-none"
            />
            <div
                v-else
                class="absolute inset-x-0 top-[7%] mx-auto w-[38%] transition-transform duration-700 ease-out group-hover:-translate-y-1 motion-reduce:transition-none"
            >
                <TagVisualizer
                    :name="artName ?? name"
                    :subtitle="artSubtitle"
                    :year="artYear"
                    :accent="accent"
                    :tilt="false"
                />
            </div>
            <span
                v-if="!available"
                class="absolute top-3 left-3 rounded-full px-2.5 py-1 text-[10px] font-semibold tracking-[0.14em] uppercase"
                :class="
                    tone === 'dark'
                        ? 'bg-white/10 text-white/80'
                        : 'bg-foreground text-background'
                "
                >{{ statusLabel ?? $t('Agotado') }}</span
            >
        </div>
        <div class="mt-4 flex items-start justify-between gap-4">
            <div class="min-w-0">
                <h3
                    class="font-serif text-xl leading-tight"
                    :class="tone === 'dark' ? 'text-white' : 'text-foreground'"
                >
                    {{ name }}
                </h3>
                <p
                    v-if="tagline"
                    class="mt-1 line-clamp-2 text-sm"
                    :class="
                        tone === 'dark'
                            ? 'text-white/55'
                            : 'text-muted-foreground'
                    "
                >
                    {{ tagline }}
                </p>
            </div>
            <p v-if="priceMinor !== null" class="shrink-0 text-right text-sm">
                <Money
                    :minor="priceMinor"
                    :currency="currency"
                    class="font-semibold"
                    :class="tone === 'dark' ? 'text-white' : 'text-foreground'"
                />
                <Money
                    v-if="compareAtMinor"
                    :minor="compareAtMinor"
                    :currency="currency"
                    class="block text-xs line-through"
                    :class="
                        tone === 'dark'
                            ? 'text-white/45'
                            : 'text-muted-foreground'
                    "
                />
            </p>
        </div>
    </Link>
</template>
