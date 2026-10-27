<script setup lang="ts">
/**
 * A Finisher Legacy Tag product (or any product of a collection) as a
 * tile: uploaded photo when there is one, otherwise the illustrative tag.
 * Price and availability are exactly what the server resolved.
 */
import { Link } from '@inertiajs/vue3';
import Money from '@/components/shared/Money.vue';
import TagVisualizer from '@/components/tag/TagVisualizer.vue';
import { show as productShow } from '@/routes/store/products';

withDefaults(
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
        tone: 'light',
    },
);
</script>

<template>
    <Link
        :href="productShow(slug)"
        class="group block rounded-media focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:ring-offset-4 focus-visible:outline-none"
    >
        <div
            class="relative aspect-square overflow-hidden rounded-media"
            :class="
                tone === 'dark'
                    ? 'bg-fl-graphite'
                    : 'bg-card ring-1 ring-border'
            "
        >
            <img
                v-if="imageUrl"
                :src="imageUrl"
                :alt="imageAlt ?? name"
                loading="lazy"
                decoding="async"
                class="absolute inset-0 size-full object-cover transition-transform duration-[1200ms] ease-out group-hover:scale-[1.03] motion-reduce:transition-none"
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
                >{{ statusLabel ?? 'Agotado' }}</span
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
