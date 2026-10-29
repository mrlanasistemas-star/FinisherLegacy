<script setup lang="ts">
/**
 * "Elige tu colección" — only REAL data: published collections from the
 * DB; if none yet but tag products are published, those; if neither, an
 * honest "las primeras colecciones están por llegar". Light cards (paper
 * stage tinted with each collection's accent), real "desde" prices.
 */
import { Link } from '@inertiajs/vue3';
import { ArrowRight, ArrowUpRight } from '@lucide/vue';
import TagProductTile from '@/components/collections/TagProductTile.vue';
import Reveal from '@/components/motion/Reveal.vue';
import Money from '@/components/shared/Money.vue';
import TagVisualizer from '@/components/tag/TagVisualizer.vue';
import { howItWorks } from '@/routes';
import {
    index as collectionsIndex,
    show as collectionShow,
} from '@/routes/collections';
import type { PublicProductCard } from '@/types';
import type { CollectionCardData } from '@/types/collections';
import { collectionSubtitle } from '@/types/collections';

defineProps<{
    collections: CollectionCardData[];
    tagProducts: PublicProductCard[];
}>();

const stage = (accent: string | null) => ({
    background: `radial-gradient(75% 60% at 50% 62%, color-mix(in srgb, ${accent ?? 'var(--fl-gold)'} 20%, transparent), transparent 72%), var(--fl-paper)`,
});
</script>

<template>
    <section
        class="bg-white py-20 sm:py-24 lg:py-28"
        aria-labelledby="collections-title"
    >
        <div class="fl-container">
            <Reveal
                class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between"
            >
                <div class="max-w-2xl">
                    <p class="fl-eyebrow">Colecciones</p>
                    <h2
                        id="collections-title"
                        class="fl-display mt-4 text-[clamp(2rem,4.2vw,3.25rem)]"
                    >
                        Elige la colección de tu carrera.
                    </h2>
                    <p
                        class="mt-4 max-w-[32rem] text-lg leading-relaxed text-muted-foreground"
                    >
                        Cada destino, disciplina y edición tiene su propio
                        diseño.
                    </p>
                </div>
                <Link
                    v-if="collections.length"
                    :href="collectionsIndex()"
                    class="inline-flex min-h-11 shrink-0 items-center gap-2 text-sm font-semibold text-foreground underline decoration-fl-gold decoration-1 underline-offset-[6px] hover:decoration-2"
                >
                    Ver todas las colecciones
                    <ArrowRight class="size-4" aria-hidden="true" />
                </Link>
            </Reveal>

            <!-- Collections: a rail that peeks the next one on phones -->
            <ul
                v-if="collections.length"
                class="-mx-4 mt-12 flex snap-x snap-mandatory gap-4 overflow-x-auto px-4 pb-4 sm:mx-0 sm:grid sm:snap-none sm:grid-cols-2 sm:gap-6 sm:overflow-visible sm:px-0 lg:grid-cols-3"
            >
                <Reveal
                    v-for="(collection, index) in collections"
                    :key="collection.slug"
                    as="li"
                    :delay-ms="(index % 3) * 100"
                    class="w-[78%] shrink-0 snap-start sm:w-auto"
                >
                    <Link
                        :href="collectionShow(collection.slug)"
                        class="group block h-full overflow-hidden rounded-media border border-fl-line bg-white transition-shadow hover:shadow-fl-float focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:ring-offset-4 focus-visible:outline-none"
                    >
                        <div
                            class="relative aspect-[4/3] overflow-hidden"
                            :style="stage(collection.accent_color)"
                        >
                            <img
                                v-if="collection.cover_url"
                                :src="collection.cover_url"
                                :alt="`Colección ${collection.name}`"
                                :loading="index === 0 ? 'eager' : 'lazy'"
                                decoding="async"
                                class="absolute inset-0 size-full object-cover transition-transform duration-[1200ms] ease-out group-hover:scale-[1.03] motion-reduce:transition-none"
                            />
                            <div
                                v-else
                                class="absolute inset-x-0 top-[6%] mx-auto w-[30%] transition-transform duration-700 ease-out group-hover:-translate-y-1 group-hover:rotate-[-3deg] motion-reduce:transition-none"
                            >
                                <TagVisualizer
                                    :name="collection.name"
                                    :subtitle="collectionSubtitle(collection)"
                                    :year="collection.year"
                                    :accent="collection.accent_color"
                                    :tilt="false"
                                />
                            </div>
                            <span
                                v-if="collection.year"
                                class="legacy-numeric absolute top-4 left-4 rounded-full bg-white/90 px-2.5 py-1 text-[11px] font-semibold tracking-[0.16em] text-fl-gold-ink"
                                >{{ collection.year }}</span
                            >
                        </div>
                        <div class="flex items-end justify-between gap-4 p-5">
                            <div class="min-w-0">
                                <p
                                    v-if="collectionSubtitle(collection)"
                                    class="truncate text-[11px] font-semibold tracking-[0.18em] text-fl-gold-ink uppercase"
                                >
                                    {{ collectionSubtitle(collection) }}
                                </p>
                                <h3
                                    class="mt-1 truncate font-serif text-2xl leading-tight text-foreground"
                                >
                                    {{ collection.name }}
                                </h3>
                                <p class="mt-1.5 text-sm text-muted-foreground">
                                    <template
                                        v-if="
                                            collection.from_price_minor !== null
                                        "
                                    >
                                        Desde
                                        <Money
                                            :minor="collection.from_price_minor"
                                            :currency="collection.currency"
                                            class="font-semibold text-foreground"
                                        />
                                    </template>
                                    <template
                                        v-else-if="!collection.products_count"
                                        >Muy pronto</template
                                    >
                                </p>
                            </div>
                            <span
                                class="grid size-10 shrink-0 place-items-center rounded-full border border-foreground/10 text-foreground transition-colors group-hover:border-fl-black group-hover:bg-fl-black group-hover:text-white"
                                aria-hidden="true"
                            >
                                <ArrowUpRight class="size-4" />
                            </span>
                        </div>
                    </Link>
                </Reveal>
            </ul>

            <!-- No collection yet, but tags are on sale -->
            <ul
                v-else-if="tagProducts.length"
                class="mt-12 grid gap-8 sm:grid-cols-2 lg:grid-cols-3"
            >
                <li v-for="product in tagProducts" :key="product.uuid">
                    <TagProductTile
                        :name="product.name"
                        :slug="product.slug"
                        :tagline="product.tagline"
                        :image-url="
                            product.image_source === 'upload'
                                ? product.image_url
                                : null
                        "
                        :image-alt="product.image_alt"
                        :price-minor="product.from_price_minor"
                        :compare-at-minor="product.compare_at_minor"
                        :currency="product.currency"
                        :available="product.in_stock"
                        :status-label="product.availability_label"
                        tone="light"
                    />
                </li>
            </ul>

            <!-- Honest empty state -->
            <div
                v-else
                class="mt-12 grid items-center gap-8 rounded-hero border border-fl-line bg-fl-paper p-8 sm:p-12 lg:grid-cols-12"
            >
                <div
                    class="mx-auto w-32 lg:col-span-3 lg:w-full lg:max-w-[10rem]"
                >
                    <TagVisualizer
                        name="Próximamente"
                        subtitle="Primera colección"
                        :tilt="false"
                    />
                </div>
                <div class="lg:col-span-8 lg:col-start-5">
                    <p
                        class="font-serif text-3xl leading-tight text-foreground sm:text-4xl"
                    >
                        Las primeras colecciones están por llegar.
                    </p>
                    <p class="mt-4 max-w-lg text-muted-foreground">
                        Estamos terminando los diseños. Mientras tanto, conoce
                        cómo funciona tu tag de principio a fin.
                    </p>
                    <Link
                        :href="howItWorks()"
                        class="mt-7 inline-flex min-h-11 items-center gap-2 text-sm font-semibold text-foreground underline decoration-fl-gold decoration-1 underline-offset-[6px] transition-colors hover:decoration-2"
                    >
                        Cómo funciona
                        <ArrowRight class="size-4" aria-hidden="true" />
                    </Link>
                </div>
            </div>
        </div>
    </section>
</template>
