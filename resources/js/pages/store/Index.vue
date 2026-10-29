<script setup lang="ts">
/**
 * Store home (owner spec §17-§18). Since 2026-10-27 the hero product is
 * the Finisher Legacy Tag (a rotatable LegacyTag3D in the hero until
 * real photography exists); historical Legacy Plate products stay
 * purchasable through their own page but are no longer promoted here.
 * Editorial hero, categories,
 * featured, the Legacy collection, new arrivals, NFC-connected products,
 * "más usados en carreras", editorial blocks and a closing CTA. Every
 * product block comes from the catalog; a block with nothing real to
 * show is simply not rendered. No invented reviews or numbers.
 */
import { Link } from '@inertiajs/vue3';
import { ArrowRight, ArrowUpRight, Nfc, Smartphone } from '@lucide/vue';
import { computed } from 'vue';
import Reveal from '@/components/motion/Reveal.vue';
import SeoHead from '@/components/public/SeoHead.vue';
import Money from '@/components/shared/Money.vue';
import ProductCard from '@/components/shared/ProductCard.vue';
import TagVisualizer from '@/components/tag/TagVisualizer.vue';
import LegacyTag3D from '@/components/three/LegacyTag3D.vue';
import { Button } from '@/components/ui/button';
import { MEDIA } from '@/config/media';
import { productCardProps } from '@/lib/store';
import { howItWorks } from '@/routes';
import { index as collectionsIndex } from '@/routes/collections';
import { index as eventsIndex } from '@/routes/events';
import { catalog } from '@/routes/store';
import { show as categoryShow } from '@/routes/store/categories';
import { show as productShow } from '@/routes/store/products';
import type { PublicProductCard } from '@/types';

type FeaturedProduct = PublicProductCard & {
    description: string | null;
    collection?: {
        name: string;
        slug: string;
        year: number | null;
        destination: string | null;
    } | null;
    gallery: { url: string; alt_text: string | null }[];
};

const props = defineProps<{
    products: PublicProductCard[];
    featured: FeaturedProduct | null;
    sections: {
        featured: PublicProductCard[];
        newest: PublicProductCard[];
        newestAreRecent: boolean;
        legacy: PublicProductCard[];
        nfc: PublicProductCard[];
        raceFavorites: PublicProductCard[];
        raceFavoritesBySales: boolean;
    };
    categories: {
        name: string;
        slug: string;
        description: string | null;
        products_count: number;
    }[];
}>();

const photo = MEDIA.photo;

const visibleCategories = computed(() =>
    props.categories.filter((c) => c.products_count > 0),
);
// Columns follow the count, so a short list never leaves an empty grey cell.
const categoryCols = computed(() => {
    const n = Math.min(visibleCategories.value.length, 8);

    if (n === 1) {
        return 'grid-cols-1';
    }

    if (n === 3) {
        return 'max-sm:grid-cols-1 sm:grid-cols-3';
    }

    // Odd counts: the last tile spans the row on two columns.
    const odd =
        n % 2 === 1
            ? '[&>li:last-child]:col-span-2 lg:[&>li:last-child]:col-span-1'
            : '';

    return `${n >= 4 ? 'lg:grid-cols-4' : ''} ${odd}`;
});

// Each band shows products the previous bands didn't, so a small
// catalog never reads as the same four cards repeated.
const featuredRow = computed(() =>
    props.sections.featured
        .filter(
            (p) => p.type !== 'legacy_plate' && p.uuid !== props.featured?.uuid,
        )
        .slice(0, 4),
);
const shown = computed(() => new Set(featuredRow.value.map((p) => p.uuid)));
const freshOrAll = (list: PublicProductCard[], min = 2) => {
    const fresh = list.filter((p) => !shown.value.has(p.uuid));

    return fresh.length >= min ? fresh : list;
};
const newestRow = computed(() =>
    props.sections.newestAreRecent ? props.sections.newest : [],
);
const nfcRow = computed(() => props.sections.nfc.slice(0, 3));
const favoritesRow = computed(() =>
    freshOrAll(props.sections.raceFavorites).slice(0, 4),
);
const legacyExtras = computed(() =>
    props.sections.legacy
        .filter((p) => p.uuid !== props.featured?.uuid)
        .slice(0, 2),
);
</script>

<template>
    <SeoHead
        title="Equipo para correr, piezas para recordar"
        description="El Finisher Legacy Tag, ropa técnica y accesorios para atletas. El recuerdo físico de tu logro, con tu historia dentro."
    />

    <!-- Hero -->
    <section class="relative overflow-hidden">
        <div
            class="fl-container grid items-center gap-10 pt-10 pb-14 sm:pt-14 lg:grid-cols-12 lg:gap-8 lg:pt-16 lg:pb-20"
        >
            <div class="lg:col-span-6">
                <p class="fl-eyebrow flex items-center gap-3">
                    <span class="h-px w-8 bg-fl-gold" aria-hidden="true" />
                    Tienda Finisher Legacy
                </p>
                <h1
                    class="fl-display mt-6 text-[2.75rem] sm:text-6xl xl:text-7xl"
                >
                    Equipo para correr.
                    <span class="block text-fl-stone italic"
                        >Piezas para recordar.</span
                    >
                </h1>
                <p
                    class="mt-6 max-w-lg text-lg leading-relaxed text-muted-foreground"
                >
                    Lo que usas en tus carreras y el recuerdo que te llevas de
                    ellas: el Finisher Legacy Tag, con tu historia dentro.
                </p>
                <div class="mt-9 flex flex-wrap gap-3">
                    <Button as-child size="lg" class="h-12 rounded-full px-6">
                        <Link :href="catalog()">
                            Ver catálogo
                            <ArrowRight class="size-4" />
                        </Link>
                    </Button>
                    <Button
                        as-child
                        size="lg"
                        variant="outline"
                        class="h-12 rounded-full px-6"
                    >
                        <Link :href="collectionsIndex()"
                            >Explorar colecciones</Link
                        >
                    </Button>
                </div>
            </div>

            <div class="relative lg:col-span-6">
                <div
                    class="relative mx-auto flex max-w-xl flex-col overflow-hidden rounded-hero border border-border bg-fl-cream lg:max-w-none"
                >
                    <div
                        class="absolute inset-0 bg-[radial-gradient(55%_60%_at_50%_45%,color-mix(in_srgb,var(--fl-gold)_22%,transparent),transparent_70%)]"
                        aria-hidden="true"
                    />
                    <div class="relative mx-auto w-[56%] pt-4 sm:w-[44%]">
                        <LegacyTag3D
                            :name="featured?.collection?.name ?? 'Tu colección'"
                            :subtitle="
                                featured?.collection?.destination ??
                                'Destino · Disciplina'
                            "
                            :year="featured?.collection?.year ?? null"
                            label="Finisher Legacy Tag en 3D"
                        />
                    </div>
                    <div class="relative px-6 pt-2 pb-6 sm:px-8 sm:pb-8">
                        <p
                            class="text-[11px] font-semibold tracking-[0.2em] text-fl-gold-ink uppercase"
                        >
                            Finisher Legacy Tag · gíralo en 3D
                        </p>
                        <p
                            class="mt-1 font-serif text-xl text-foreground sm:text-2xl"
                        >
                            El recuerdo físico de tu logro. Tu historia vive
                            dentro.
                        </p>
                    </div>
                </div>
                <p
                    class="absolute top-4 left-4 z-10 inline-flex items-center gap-2 rounded-full border border-fl-gold/30 bg-white/90 px-3 py-1.5 text-[11px] font-semibold tracking-[0.14em] text-fl-gold-ink uppercase shadow-fl-micro backdrop-blur sm:top-5 sm:left-5"
                >
                    <Nfc class="size-3.5" aria-hidden="true" /> Solo acerca tu
                    teléfono
                </p>
            </div>
        </div>
    </section>

    <!-- Categories -->
    <section
        v-if="visibleCategories.length"
        class="border-y border-border bg-card"
        aria-labelledby="categorias"
    >
        <div class="fl-container py-10 sm:py-12">
            <h2 id="categorias" class="sr-only">Categorías</h2>
            <ul
                class="grid grid-cols-2 gap-px overflow-hidden rounded-card border border-border bg-border"
                :class="categoryCols"
            >
                <li
                    v-for="(category, index) in visibleCategories.slice(0, 8)"
                    :key="category.slug"
                    class="bg-card"
                >
                    <Link
                        :href="categoryShow(category.slug)"
                        class="group flex h-full min-h-28 flex-col justify-between gap-5 p-5 transition-colors hover:bg-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none focus-visible:ring-inset sm:min-h-32 sm:p-6"
                    >
                        <span
                            class="legacy-numeric text-xs text-muted-foreground"
                            >{{ String(index + 1).padStart(2, '0') }}</span
                        >
                        <span class="flex items-end justify-between gap-3">
                            <span>
                                <span
                                    class="block font-serif text-xl text-foreground sm:text-2xl"
                                    >{{ category.name }}</span
                                >
                                <span class="text-sm text-muted-foreground"
                                    >{{ category.products_count }}
                                    {{
                                        category.products_count === 1
                                            ? 'producto'
                                            : 'productos'
                                    }}</span
                                >
                            </span>
                            <ArrowUpRight
                                class="size-5 text-muted-foreground transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5 group-hover:text-foreground"
                                aria-hidden="true"
                            />
                        </span>
                    </Link>
                </li>
            </ul>
        </div>
    </section>

    <!-- Featured -->
    <section
        v-if="featuredRow.length"
        class="fl-container py-16 sm:py-24"
        aria-labelledby="destacados"
    >
        <div class="flex items-end justify-between gap-4">
            <div>
                <p class="fl-eyebrow">Destacados</p>
                <h2
                    id="destacados"
                    class="fl-display mt-3 text-4xl sm:text-5xl"
                >
                    Lo esencial del día de carrera.
                </h2>
            </div>
            <Link
                :href="catalog()"
                class="hidden shrink-0 items-center gap-2 text-sm font-semibold underline-offset-4 hover:underline sm:inline-flex"
            >
                Ver todo <ArrowRight class="size-4" />
            </Link>
        </div>
        <div
            class="mt-10 grid grid-cols-2 gap-x-4 gap-y-12 sm:gap-x-6 lg:grid-cols-4"
        >
            <ProductCard
                v-for="(product, index) in featuredRow"
                :key="product.uuid"
                v-bind="productCardProps(product)"
                :priority="index < 2"
            />
        </div>
    </section>

    <!-- Legacy collection -->
    <section
        v-if="featured && featured.type === 'legacy_tag'"
        class="fl-container pb-16 sm:pb-24"
    >
        <div
            class="grid overflow-hidden rounded-hero border border-border bg-card lg:grid-cols-12"
        >
            <div
                class="relative min-h-[24rem] overflow-hidden bg-fl-cream sm:min-h-[30rem] lg:col-span-6"
            >
                <img
                    v-if="
                        featured.image_source === 'upload' && featured.image_url
                    "
                    :src="featured.image_url"
                    :alt="featured.image_alt ?? featured.name"
                    loading="lazy"
                    decoding="async"
                    class="absolute inset-0 size-full object-cover"
                />
                <div v-else class="absolute inset-x-0 top-[8%] mx-auto w-[34%]">
                    <TagVisualizer
                        :name="featured.collection?.name ?? featured.name"
                        :subtitle="featured.collection?.destination ?? null"
                        :year="featured.collection?.year ?? null"
                        :tilt="false"
                    />
                </div>
            </div>
            <div class="flex flex-col justify-center p-8 sm:p-12 lg:col-span-6">
                <p class="fl-eyebrow">Finisher Legacy Tag</p>
                <h2 class="fl-display mt-4 text-4xl sm:text-5xl">
                    Tu carrera termina. Tu historia no.
                </h2>
                <p class="mt-5 text-lg leading-relaxed text-muted-foreground">
                    {{
                        featured.tagline ||
                        'Un tag de colección con chip NFC: acercas tu teléfono, lo activas y guardas ahí la historia de tu carrera.'
                    }}
                </p>
                <ul class="mt-6 flex flex-wrap gap-2 text-sm">
                    <li
                        v-for="item in [
                            'Diseño de colección',
                            'Chip NFC',
                            'Correa incluida',
                            'Sin apps',
                        ]"
                        :key="item"
                        class="rounded-full border border-border px-3 py-1.5 text-muted-foreground"
                    >
                        {{ item }}
                    </li>
                </ul>
                <div class="mt-8 flex flex-wrap items-center gap-5">
                    <Button as-child size="lg" class="h-12 rounded-full px-6">
                        <Link :href="productShow(featured.slug)">
                            Ver {{ featured.name }}
                            <ArrowRight class="size-4" />
                        </Link>
                    </Button>
                    <p
                        v-if="featured.from_price_minor !== null"
                        class="text-sm text-muted-foreground"
                    >
                        Desde
                        <Money
                            :minor="featured.from_price_minor"
                            :currency="featured.currency"
                            class="font-semibold text-foreground"
                        />
                    </p>
                </div>

                <div
                    v-if="legacyExtras.length"
                    class="mt-10 grid grid-cols-2 gap-4 border-t border-border pt-8"
                >
                    <ProductCard
                        v-for="product in legacyExtras"
                        :key="product.uuid"
                        v-bind="productCardProps(product)"
                    />
                </div>
            </div>
        </div>
    </section>

    <!-- New arrivals -->
    <section
        v-if="newestRow.length >= 2"
        class="border-t border-border bg-card py-16 sm:py-24"
        aria-labelledby="novedades"
    >
        <div class="fl-container">
            <p class="fl-eyebrow">Novedades</p>
            <h2 id="novedades" class="fl-display mt-3 text-4xl sm:text-5xl">
                Recién llegados.
            </h2>
            <div
                class="mt-10 grid grid-cols-2 gap-x-4 gap-y-12 sm:gap-x-6 lg:grid-cols-4"
            >
                <ProductCard
                    v-for="product in newestRow"
                    :key="product.uuid"
                    v-bind="productCardProps(product)"
                />
            </div>
        </div>
    </section>

    <!-- NFC-connected -->
    <section
        v-if="nfcRow.length"
        class="py-16 sm:py-24"
        aria-labelledby="conectados"
    >
        <div class="fl-container">
            <div class="grid gap-10 lg:grid-cols-12 lg:gap-12">
                <div class="lg:col-span-4">
                    <div class="lg:sticky lg:top-28">
                        <span
                            class="flex size-12 items-center justify-center rounded-full bg-foreground text-fl-gold"
                            aria-hidden="true"
                        >
                            <Smartphone class="size-5" />
                        </span>
                        <p class="fl-eyebrow mt-6">Productos conectados</p>
                        <h2
                            id="conectados"
                            class="fl-display mt-3 text-4xl sm:text-[2.75rem]"
                        >
                            Acércale tu teléfono.
                        </h2>
                        <p
                            class="mt-4 text-base leading-relaxed text-muted-foreground"
                        >
                            Con NFC, tu producto abre su página al instante y,
                            al conectarlo, queda guardado en Mi equipo, junto a
                            tus carreras.
                        </p>
                        <Link
                            :href="howItWorks()"
                            class="mt-6 inline-flex items-center gap-2 text-sm font-semibold underline-offset-4 hover:underline"
                        >
                            Cómo funciona <ArrowRight class="size-4" />
                        </Link>
                    </div>
                </div>
                <div
                    class="grid grid-cols-2 gap-x-4 gap-y-12 sm:grid-cols-3 sm:gap-x-6 lg:col-span-8"
                >
                    <ProductCard
                        v-for="product in nfcRow"
                        :key="product.uuid"
                        v-bind="productCardProps(product)"
                    />
                </div>
            </div>
        </div>
    </section>

    <!-- Editorial blocks — light cards: photo on top, text on white -->
    <section class="fl-container pb-16 sm:pb-24" aria-label="Historias">
        <div class="grid gap-5 md:grid-cols-2">
            <Reveal
                as="article"
                class="group relative flex flex-col overflow-hidden rounded-hero border border-border bg-card p-3 shadow-fl-micro transition-[box-shadow,transform] duration-300 hover:-translate-y-0.5 hover:shadow-fl-float"
            >
                <div
                    class="relative aspect-[16/10] overflow-hidden rounded-media bg-fl-cream"
                >
                    <img
                        :src="photo.runner.src"
                        :srcset="photo.runner.srcset"
                        sizes="(min-width: 768px) 50vw, 100vw"
                        :alt="photo.runner.alt"
                        loading="lazy"
                        decoding="async"
                        class="absolute inset-0 size-full object-cover object-[30%_30%] transition-transform duration-[1200ms] ease-out group-hover:scale-[1.03]"
                    />
                </div>
                <div
                    class="flex flex-1 flex-col px-4 pt-6 pb-5 sm:px-6 sm:pb-7"
                >
                    <p class="fl-eyebrow">Antes de la meta</p>
                    <h3
                        class="mt-2 max-w-md font-serif text-2xl leading-tight text-foreground sm:text-3xl"
                    >
                        Hecho para el kilómetro en que todo cuesta.
                    </h3>
                    <Link
                        :href="catalog({ query: { tipo: 'apparel' } })"
                        class="mt-5 inline-flex items-center gap-2 self-start text-sm font-semibold text-foreground underline decoration-fl-gold decoration-1 underline-offset-[6px] transition-[text-decoration-thickness] after:absolute after:inset-0 hover:decoration-2"
                    >
                        Ver ropa técnica
                        <ArrowRight
                            class="size-4 transition-transform group-hover:translate-x-0.5"
                        />
                    </Link>
                </div>
            </Reveal>
            <Reveal
                as="article"
                :delay-ms="80"
                class="group relative flex flex-col overflow-hidden rounded-hero border border-border bg-card p-3 shadow-fl-micro transition-[box-shadow,transform] duration-300 hover:-translate-y-0.5 hover:shadow-fl-float"
            >
                <div
                    class="relative aspect-[16/10] overflow-hidden rounded-media bg-fl-cream"
                >
                    <img
                        :src="photo.triumph.src"
                        :srcset="photo.triumph.srcset"
                        sizes="(min-width: 768px) 50vw, 100vw"
                        :alt="photo.triumph.alt"
                        loading="lazy"
                        decoding="async"
                        class="absolute inset-0 size-full object-cover transition-transform duration-[1200ms] ease-out group-hover:scale-[1.03]"
                    />
                </div>
                <div
                    class="flex flex-1 flex-col px-4 pt-6 pb-5 sm:px-6 sm:pb-7"
                >
                    <p class="fl-eyebrow">Después de la meta</p>
                    <h3
                        class="mt-2 max-w-md font-serif text-2xl leading-tight text-foreground sm:text-3xl"
                    >
                        Para el momento que no quieres olvidar.
                    </h3>
                    <Link
                        :href="
                            featured ? productShow(featured.slug) : catalog()
                        "
                        class="mt-5 inline-flex items-center gap-2 self-start text-sm font-semibold text-foreground underline decoration-fl-gold decoration-1 underline-offset-[6px] transition-[text-decoration-thickness] after:absolute after:inset-0 hover:decoration-2"
                    >
                        Guardar tu historia
                        <ArrowRight
                            class="size-4 transition-transform group-hover:translate-x-0.5"
                        />
                    </Link>
                </div>
            </Reveal>
        </div>
    </section>

    <!-- Most used in races -->
    <section
        v-if="favoritesRow.length"
        class="border-t border-border bg-card py-16 sm:py-24"
        aria-labelledby="favoritos"
    >
        <div class="fl-container">
            <div class="flex items-end justify-between gap-4">
                <div>
                    <p class="fl-eyebrow">
                        {{
                            sections.raceFavoritesBySales
                                ? 'Los más usados en carreras'
                                : 'Para tu próxima carrera'
                        }}
                    </p>
                    <h2
                        id="favoritos"
                        class="fl-display mt-3 text-4xl sm:text-5xl"
                    >
                        {{
                            sections.raceFavoritesBySales
                                ? 'Lo que más llevan los atletas.'
                                : 'Listo para la línea de salida.'
                        }}
                    </h2>
                </div>
                <Link
                    :href="catalog()"
                    class="hidden shrink-0 items-center gap-2 text-sm font-semibold underline-offset-4 hover:underline sm:inline-flex"
                >
                    Ver catálogo <ArrowRight class="size-4" />
                </Link>
            </div>
            <div
                class="mt-10 grid grid-cols-2 gap-x-4 gap-y-12 sm:gap-x-6 lg:grid-cols-4"
            >
                <ProductCard
                    v-for="product in favoritesRow"
                    :key="product.uuid"
                    v-bind="productCardProps(product)"
                />
            </div>
        </div>
    </section>

    <!-- Closing CTA -->
    <section class="fl-container py-16 sm:py-24">
        <div
            class="relative overflow-hidden rounded-hero border border-border bg-[radial-gradient(120%_120%_at_100%_0%,#f4eedf,transparent_55%),#fff] px-6 py-14 text-center sm:px-12 sm:py-20"
        >
            <p class="fl-eyebrow">Tu próxima historia</p>
            <h2 class="fl-display mx-auto mt-4 max-w-2xl text-4xl sm:text-5xl">
                Empieza por tu próxima carrera.
            </h2>
            <p class="mx-auto mt-4 max-w-lg text-muted-foreground">
                Elige tu evento, prepárate con tu equipo y guarda lo que vivas
                en tu legado.
            </p>
            <div class="mt-8 flex flex-wrap justify-center gap-3">
                <Button as-child size="lg" class="h-12 rounded-full px-6">
                    <Link :href="eventsIndex()">Explorar eventos</Link>
                </Button>
                <Button
                    as-child
                    size="lg"
                    variant="outline"
                    class="h-12 rounded-full px-6"
                >
                    <Link :href="catalog()">Ver todo el catálogo</Link>
                </Button>
            </div>
        </div>
    </section>
</template>
