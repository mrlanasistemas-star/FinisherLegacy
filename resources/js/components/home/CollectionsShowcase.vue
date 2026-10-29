<script setup lang="ts">
/**
 * "Elige tu colección" — only REAL data: published collections from the
 * DB; if none yet but tag products are published, those; if neither, an
 * honest "las primeras colecciones están por llegar".
 *
 * On desktop the travelling 3D tag waits in the right column (sticky) and
 * wears the collection whose row is at the middle of the screen (or the
 * one under the pointer / focus): it spins once and comes back with that
 * collection's name, year and accent. Real "desde" prices only.
 */
import { Link } from '@inertiajs/vue3';
import { ArrowRight, ArrowUpRight } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import TagProductTile from '@/components/collections/TagProductTile.vue';
import Reveal from '@/components/motion/Reveal.vue';
import Money from '@/components/shared/Money.vue';
import TagVisualizer from '@/components/tag/TagVisualizer.vue';
import TagAnchor from '@/components/three/TagAnchor.vue';
import type { TagPose } from '@/components/three/tagStageContext';
import { howItWorks } from '@/routes';
import {
    index as collectionsIndex,
    show as collectionShow,
} from '@/routes/collections';
import type { PublicProductCard } from '@/types';
import type { CollectionCardData } from '@/types/collections';
import { collectionSubtitle } from '@/types/collections';

const props = defineProps<{
    collections: CollectionCardData[];
    tagProducts: PublicProductCard[];
}>();

const active = ref(0);
const activeCollection = computed(
    () => props.collections[active.value] ?? props.collections[0] ?? null,
);

const pose = computed<TagPose>(() => {
    const c = activeCollection.value;

    return {
        face: 'front',
        turn: -0.3,
        tilt: 0.05,
        roll: 0.03,
        art: c
            ? {
                  name: c.name,
                  subtitle: collectionSubtitle(c),
                  year: c.year,
                  accent: c.accent_color,
              }
            : null,
    };
});

const stage = (accent: string | null) => ({
    background: `radial-gradient(80% 70% at 50% 60%, color-mix(in srgb, ${accent ?? 'var(--fl-gold)'} 26%, transparent), transparent 75%), var(--fl-paper)`,
});

const rows = ref<HTMLElement[]>([]);
let observer: IntersectionObserver | null = null;

onMounted(() => {
    if (!('IntersectionObserver' in window)) {
        return;
    }

    observer = new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                if (entry.isIntersecting) {
                    active.value = Number(
                        (entry.target as HTMLElement).dataset.index ?? 0,
                    );
                }
            }
        },
        { rootMargin: '-46% 0px -46% 0px' },
    );
    rows.value.forEach((el) => observer?.observe(el));
});

onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <section
        class="relative bg-fl-paper py-20 sm:py-24 lg:py-32"
        aria-labelledby="collections-title"
    >
        <div class="fl-container grid gap-12 lg:grid-cols-12 lg:gap-10">
            <div class="lg:col-span-7">
                <Reveal class="max-w-2xl text-center lg:text-left">
                    <p class="fl-eyebrow">Colecciones</p>
                    <h2
                        id="collections-title"
                        class="fl-display mt-4 text-[clamp(2.1rem,4.4vw,3.6rem)]"
                    >
                        Elige la colección de tu carrera.
                    </h2>
                    <p
                        class="mx-auto mt-5 max-w-[32rem] text-lg leading-relaxed text-muted-foreground lg:mx-0"
                    >
                        Cada destino, disciplina y edición tiene su propio
                        diseño.
                    </p>
                </Reveal>

                <!-- Collections -->
                <ul v-if="collections.length" class="mt-10 space-y-4 lg:mt-14">
                    <li
                        v-for="(collection, index) in collections"
                        :key="collection.slug"
                        ref="rows"
                        :data-index="index"
                    >
                        <Link
                            :href="collectionShow(collection.slug)"
                            class="home-lift group grid grid-cols-[5.5rem_1fr_auto] items-center gap-4 rounded-card border bg-white p-3 pr-5 focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:ring-offset-4 focus-visible:outline-none sm:grid-cols-[7rem_1fr_auto] sm:gap-6"
                            :class="
                                active === index
                                    ? 'border-fl-gold/60 shadow-fl-float'
                                    : 'border-fl-line shadow-fl-micro hover:shadow-fl-soft'
                            "
                            @pointerenter="active = index"
                            @focus="active = index"
                        >
                            <span
                                class="relative block aspect-square overflow-hidden rounded-[1.1rem]"
                                :style="stage(collection.accent_color)"
                            >
                                <img
                                    v-if="collection.cover_url"
                                    :src="collection.cover_url"
                                    :alt="`Colección ${collection.name}`"
                                    :loading="index === 0 ? 'eager' : 'lazy'"
                                    decoding="async"
                                    class="absolute inset-0 size-full object-cover transition-transform duration-700 ease-out group-hover:scale-[1.05] motion-reduce:transition-none"
                                />
                                <span
                                    v-else
                                    class="absolute inset-x-0 top-[4%] mx-auto block w-[46%] transition-transform duration-700 ease-out group-hover:-translate-y-0.5 group-hover:rotate-[-4deg] motion-reduce:transition-none"
                                >
                                    <TagVisualizer
                                        :name="collection.name"
                                        :subtitle="
                                            collectionSubtitle(collection)
                                        "
                                        :year="collection.year"
                                        :accent="collection.accent_color"
                                        :tilt="false"
                                    />
                                </span>
                            </span>
                            <span class="min-w-0">
                                <span
                                    v-if="
                                        collectionSubtitle(collection) ||
                                        collection.year
                                    "
                                    class="block truncate text-[11px] font-semibold tracking-[0.18em] text-fl-gold-ink uppercase"
                                >
                                    {{
                                        [
                                            collectionSubtitle(collection),
                                            collection.year,
                                        ]
                                            .filter(Boolean)
                                            .join(' · ')
                                    }}
                                </span>
                                <span
                                    class="mt-1 block truncate font-serif text-[1.6rem] leading-tight text-foreground sm:text-[1.9rem]"
                                >
                                    {{ collection.name }}
                                </span>
                                <span
                                    class="mt-1 block text-sm text-muted-foreground"
                                >
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
                                </span>
                            </span>
                            <span
                                class="grid size-11 shrink-0 place-items-center rounded-full border border-foreground/10 text-foreground transition-all duration-300 group-hover:rotate-45 group-hover:border-fl-black group-hover:bg-fl-black group-hover:text-white motion-reduce:transition-none"
                                aria-hidden="true"
                            >
                                <ArrowUpRight class="size-4" />
                            </span>
                        </Link>
                    </li>
                </ul>

                <!-- No collection yet, but tags are on sale -->
                <ul
                    v-else-if="tagProducts.length"
                    class="mt-12 grid gap-8 sm:grid-cols-2"
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
                    class="mt-12 rounded-hero border border-fl-line bg-white p-8 sm:p-10"
                >
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

                <div
                    v-if="collections.length"
                    class="mt-8 text-center lg:text-left"
                >
                    <Link
                        :href="collectionsIndex()"
                        class="group inline-flex min-h-11 items-center gap-2 text-sm font-semibold text-foreground underline decoration-fl-gold decoration-1 underline-offset-[6px] hover:decoration-2"
                    >
                        Ver todas las colecciones
                        <ArrowRight
                            class="size-4 transition-transform group-hover:translate-x-1"
                            aria-hidden="true"
                        />
                    </Link>
                </div>
            </div>

            <!-- The travelling tag's stop (desktop only) -->
            <div class="hidden lg:col-span-5 lg:block">
                <div
                    class="sticky top-[calc(50vh+2rem-min(15rem,32vh))] flex flex-col items-center"
                >
                    <TagAnchor
                        class="relative h-[min(30rem,64vh)] w-full max-w-[24rem]"
                        :pose="pose"
                        :label="
                            activeCollection
                                ? `Finisher Legacy Tag de la colección ${activeCollection.name}. Render ilustrativo.`
                                : null
                        "
                    />
                </div>
            </div>
        </div>
    </section>
</template>
