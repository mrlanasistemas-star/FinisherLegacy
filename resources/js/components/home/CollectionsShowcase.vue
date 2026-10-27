<script setup lang="ts">
/**
 * Bloque E — "Una colección por cada historia." Only REAL data:
 * published collections from the DB; if there are none yet but tag
 * products are published, those; if neither, an honest "las primeras
 * colecciones están por llegar" — never a placeholder product.
 */
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import CollectionCard from '@/components/collections/CollectionCard.vue';
import TagProductTile from '@/components/collections/TagProductTile.vue';
import TagVisualizer from '@/components/tag/TagVisualizer.vue';
import { howItWorks } from '@/routes';
import { index as collectionsIndex } from '@/routes/collections';
import type { PublicProductCard } from '@/types';
import type { CollectionCardData } from '@/types/collections';

defineProps<{
    collections: CollectionCardData[];
    tagProducts: PublicProductCard[];
}>();
</script>

<template>
    <section
        class="relative overflow-hidden bg-fl-black py-20 text-white sm:py-28 lg:py-36"
        aria-labelledby="collections-title"
    >
        <div class="fl-container">
            <div
                class="flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between"
            >
                <div class="max-w-2xl">
                    <p
                        class="flex items-center gap-3 text-[11px] font-semibold tracking-[0.24em] text-fl-gold uppercase"
                    >
                        <span class="h-px w-10 bg-fl-gold" aria-hidden="true" />
                        Colecciones
                    </p>
                    <h2
                        id="collections-title"
                        class="mt-6 font-serif text-[clamp(2.25rem,5vw,4.25rem)] leading-[1.04] font-medium tracking-[-0.02em]"
                    >
                        Una colección por cada historia.
                    </h2>
                    <p
                        class="mt-5 max-w-[32rem] text-lg leading-relaxed text-white/65"
                    >
                        Cada destino, cada disciplina, cada edición tiene su
                        propio diseño. Elige el que lleva tu carrera.
                    </p>
                </div>
                <Link
                    v-if="collections.length"
                    :href="collectionsIndex()"
                    class="inline-flex min-h-11 shrink-0 items-center gap-2 text-sm font-semibold text-white underline-offset-4 hover:underline"
                >
                    Ver todas las colecciones
                    <ArrowRight class="size-4" aria-hidden="true" />
                </Link>
            </div>

            <!-- Collections: a rail that peeks the next one on phones -->
            <ul
                v-if="collections.length"
                class="-mx-4 mt-12 flex snap-x snap-mandatory gap-5 overflow-x-auto px-4 pb-4 sm:mx-0 sm:grid sm:snap-none sm:grid-cols-2 sm:gap-8 sm:overflow-visible sm:px-0 lg:grid-cols-3"
            >
                <li
                    v-for="(collection, index) in collections"
                    :key="collection.slug"
                    class="w-[78%] shrink-0 snap-start"
                >
                    <CollectionCard
                        :collection="collection"
                        tone="dark"
                        :priority="index === 0"
                    />
                </li>
            </ul>

            <!-- No collection yet, but tags are on sale -->
            <ul
                v-else-if="tagProducts.length"
                class="mt-12 grid gap-10 sm:grid-cols-2 lg:grid-cols-3"
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
                        tone="dark"
                    />
                </li>
            </ul>

            <!-- Honest empty state -->
            <div
                v-else
                class="mt-12 grid items-center gap-10 rounded-hero border border-white/10 p-8 sm:p-12 lg:grid-cols-12"
            >
                <div
                    class="mx-auto w-40 lg:col-span-3 lg:w-full lg:max-w-[12rem]"
                >
                    <TagVisualizer
                        name="Próximamente"
                        subtitle="Primera colección"
                        :tilt="false"
                    />
                </div>
                <div class="lg:col-span-8 lg:col-start-5">
                    <p class="font-serif text-3xl leading-tight sm:text-4xl">
                        Las primeras colecciones están por llegar.
                    </p>
                    <p class="mt-4 max-w-lg text-white/65">
                        Estamos terminando los diseños. Mientras tanto, conoce
                        cómo funciona tu tag de principio a fin.
                    </p>
                    <Link
                        :href="howItWorks()"
                        class="mt-7 inline-flex min-h-11 items-center gap-2 text-sm font-medium text-white underline decoration-fl-gold decoration-1 underline-offset-[6px] transition-colors hover:decoration-2"
                    >
                        Cómo funciona
                        <ArrowRight class="size-4" aria-hidden="true" />
                    </Link>
                </div>
            </div>
        </div>
    </section>
</template>
