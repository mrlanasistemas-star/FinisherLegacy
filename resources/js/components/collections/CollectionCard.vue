<script setup lang="ts">
/**
 * One published collection: cover (admin-uploaded) or, while there is
 * none, the illustrative tag wearing the collection's name. Price is the
 * real lowest price of its products (ResolveProductPrice), never invented.
 */
import { Link } from '@inertiajs/vue3';
import { ArrowUpRight } from '@lucide/vue';
import { computed } from 'vue';
import I18nT from '@/components/i18n/I18nT.vue';
import Money from '@/components/shared/Money.vue';
import TagFlipCard from '@/components/tag/TagFlipCard.vue';
import { show as collectionShow } from '@/routes/collections';
import type { CollectionCardData } from '@/types/collections';
import { collectionSubtitle, collectionTagArts } from '@/types/collections';

const props = withDefaults(
    defineProps<{
        collection: CollectionCardData;
        tone?: 'dark' | 'light';
        priority?: boolean;
    }>(),
    { tone: 'light', priority: false },
);

const subtitle = computed(() => collectionSubtitle(props.collection));
const dark = computed(() => props.tone === 'dark');
/** Primary tag design (deck pp. 7–8 artwork when the collection has it). */
const art = computed(() => collectionTagArts(props.collection)[0]);
</script>

<template>
    <Link
        :href="collectionShow(collection.slug)"
        class="group block rounded-media focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:ring-offset-4 focus-visible:outline-none"
        :class="
            dark
                ? 'focus-visible:ring-offset-fl-black'
                : 'focus-visible:ring-offset-background'
        "
    >
        <div
            class="relative aspect-[4/5] overflow-hidden rounded-media transition-[transform,box-shadow] duration-500 ease-legacy group-hover:-translate-y-1 group-hover:shadow-fl-float group-focus-visible:-translate-y-1 motion-reduce:transform-none motion-reduce:transition-none"
            :class="dark ? 'bg-fl-graphite' : 'bg-fl-cream ring-1 ring-fl-line'"
        >
            <template v-if="collection.cover_url">
                <img
                    :src="collection.cover_url"
                    :alt="$t('Colección {name}', { name: collection.name })"
                    :loading="priority ? 'eager' : 'lazy'"
                    decoding="async"
                    class="absolute inset-0 size-full object-cover transition-transform duration-[1200ms] ease-out group-hover:scale-[1.06] motion-reduce:transition-none"
                />
                <div
                    class="absolute inset-0 bg-gradient-to-t from-fl-black/25 via-transparent to-transparent"
                />
            </template>
            <template v-else>
                <div
                    class="absolute inset-0"
                    :style="{
                        background: `radial-gradient(70% 55% at 50% 70%, color-mix(in srgb, ${collection.accent_color ?? 'var(--fl-gold)'} 22%, transparent), transparent 70%)`,
                    }"
                    aria-hidden="true"
                />
                <div
                    class="absolute inset-x-0 top-[5%] mx-auto w-[40%] transition-transform duration-700 ease-out group-hover:-translate-y-1 motion-reduce:transition-none"
                >
                    <TagFlipCard :art="art" />
                </div>
            </template>

            <span
                v-if="collection.year"
                class="legacy-numeric absolute top-4 left-4 rounded-full bg-white/90 px-2.5 py-1 text-[11px] font-semibold tracking-[0.16em] text-fl-gold-ink shadow-fl-micro backdrop-blur"
                >{{ collection.year }}</span
            >
            <span
                class="absolute right-4 bottom-4 flex size-10 items-center justify-center rounded-full bg-white text-fl-black shadow-fl-soft ring-1 ring-fl-line transition-transform duration-300 group-hover:translate-x-0.5 group-hover:-translate-y-0.5 motion-reduce:transition-none"
                aria-hidden="true"
            >
                <ArrowUpRight class="size-4" />
            </span>
        </div>

        <div class="mt-5">
            <p
                v-if="subtitle"
                class="text-[11px] font-semibold tracking-[0.2em] uppercase"
                :class="dark ? 'text-fl-gold' : 'text-fl-gold-ink'"
            >
                {{ subtitle }}
            </p>
            <h3
                class="mt-1.5 font-serif text-2xl leading-tight sm:text-[1.7rem]"
                :class="dark ? 'text-white' : 'text-foreground'"
            >
                <span class="fl-link">{{ collection.name }}</span>
            </h3>
            <p
                v-if="collection.tagline"
                class="mt-2 line-clamp-2 text-[15px] leading-relaxed"
                :class="dark ? 'text-white/60' : 'text-muted-foreground'"
            >
                {{ collection.tagline }}
            </p>
            <p
                class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm"
                :class="dark ? 'text-white/55' : 'text-muted-foreground'"
            >
                <span v-if="collection.products_count">
                    {{ $tc('{count} diseño|{count} diseños', collection.products_count) }}
                </span>
                <span v-if="collection.from_price_minor !== null">
                    <I18nT :text="$t('Desde {price}')">
                        <template #price>
                            <Money
                                :minor="collection.from_price_minor"
                                :currency="collection.currency"
                                class="font-semibold"
                                :class="dark ? 'text-white' : 'text-foreground'"
                            />
                        </template>
                    </I18nT>
                </span>
                <span v-if="!collection.products_count">{{ $t('Muy pronto') }}</span>
            </p>
        </div>
    </Link>
</template>
