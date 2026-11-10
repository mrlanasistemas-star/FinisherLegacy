<script setup lang="ts">
/**
 * Store home. One clear path for a first-time visitor: what is sold (the
 * collectible Finisher Legacy Tag + race gear), the featured tag, the tag
 * collections, the gear, why buying here is safe, and where to learn more.
 * Every product block comes from the catalog; a block with nothing real to
 * show is simply not rendered, and no product appears in two grids. No
 * invented reviews, stock or numbers.
 */
import { Link } from '@inertiajs/vue3';
import { ArrowRight, ArrowUpRight, Nfc, ShieldCheck, Truck } from '@lucide/vue';
import { computed } from 'vue';
import CtaBand from '@/components/marketing/CtaBand.vue';
import PageHero from '@/components/marketing/PageHero.vue';
import SectionIntro from '@/components/marketing/SectionIntro.vue';
import SeoHead from '@/components/public/SeoHead.vue';
import Money from '@/components/shared/Money.vue';
import ProductCard from '@/components/shared/ProductCard.vue';
import TagFlipCard from '@/components/tag/TagFlipCard.vue';
import LegacyTag3D from '@/components/three/LegacyTag3D.vue';
import { vReveal } from '@/composables/useReveal';
import { MEDIA } from '@/config/media';
import {
    ACTIVATE_URL,
    COLLECTIONS_URL,
    HOW_IT_WORKS_URL,
    PORTRAIT_URL,
} from '@/config/public-links';
import { productCardProps } from '@/lib/store';
import { catalog } from '@/routes/store';
import { show as categoryShow } from '@/routes/store/categories';
import { show as productShow } from '@/routes/store/products';
import type { PublicProductCard } from '@/types';
import { tagArt } from '@/types/collections';

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

/** The featured tag's design (collection artwork when it has one). */
const featuredArt = computed(() =>
    tagArt(
        {
            name:
                props.featured?.collection?.name ??
                props.featured?.name ??
                'Tu colección',
            year: props.featured?.collection?.year ?? null,
            destination: props.featured?.collection?.destination ?? null,
        },
        props.featured?.tag_design ?? null,
    ),
);

const featuredTag = computed(() =>
    props.featured?.type === 'legacy_tag' ? props.featured : null,
);

const visibleCategories = computed(() =>
    props.categories.filter((c) => c.products_count > 0).slice(0, 8),
);

/** De-duplicated pool of every card the server sent, in its order. */
const pool = computed(() => {
    const seen = new Set<string>();
    const all = [
        ...props.sections.featured,
        ...props.sections.raceFavorites,
        ...props.sections.newest,
        ...props.sections.legacy,
        ...props.sections.nfc,
        ...props.products,
    ];

    return all.filter((p) => {
        if (seen.has(p.uuid) || p.type === 'legacy_plate') {
            return false;
        }

        seen.add(p.uuid);

        return true;
    });
});

// Tags and gear never share a grid, and the featured tag is not repeated.
const tagRow = computed(() =>
    pool.value
        .filter(
            (p) =>
                p.type === 'legacy_tag' && p.uuid !== featuredTag.value?.uuid,
        )
        .slice(0, 4),
);
const gearRow = computed(() =>
    pool.value.filter((p) => p.type !== 'legacy_tag').slice(0, 4),
);

const trust = [
    {
        icon: Truck,
        title: 'Envío a domicilio',
        text: 'Sigue tu pedido desde tu cuenta hasta que llega a tus manos.',
    },
    {
        icon: ShieldCheck,
        title: 'Pago seguro',
        text: 'Pagas en línea, en una conexión cifrada.',
    },
    {
        icon: Nfc,
        title: 'Activación en un toque',
        text: 'Acercas tu teléfono al tag y se abre tu recuerdo. Sin apps obligatorias.',
    },
];

const tagSteps = [
    'Eliges el diseño de tu carrera o destino.',
    'Lo recibes listo, con su correa y su chip NFC.',
    'Lo tocas con tu teléfono y guardas tu historia.',
];
</script>

<template>
    <SeoHead
        title="Tienda: tags de colección y equipo para tu carrera"
        description="Colecciones del Finisher Legacy Tag por carrera y destino, además de Racepack, Trisuit y Chill Band. Envío a domicilio y pago seguro."
    />

    <PageHero
        eyebrow="Tienda Finisher Legacy"
        title="Tags de colección."
        accent="Y equipo para tu carrera."
        lede="El Finisher Legacy Tag de tu carrera o destino —lo tocas con tu teléfono y se abre tu recuerdo— y el equipo para llegar a la meta: Racepack, Trisuit y Chill Band."
    >
        <template #actions>
            <Link :href="COLLECTIONS_URL" class="fl-btn-primary">
                Ver colecciones de tags
                <ArrowRight class="size-4" aria-hidden="true" />
            </Link>
            <Link :href="catalog()" class="fl-btn-secondary">
                Todo el catálogo
            </Link>
        </template>

        <ul
            class="grid max-w-xl grid-cols-1 gap-x-6 gap-y-2 text-sm text-muted-foreground sm:grid-cols-3"
        >
            <li
                v-for="item in trust"
                :key="item.title"
                class="flex items-center gap-2"
            >
                <component
                    :is="item.icon"
                    class="size-4 shrink-0 text-fl-gold-ink"
                    aria-hidden="true"
                />
                {{ item.title }}
            </li>
        </ul>

        <template #visual>
            <div
                class="relative w-full max-w-md overflow-hidden rounded-hero border border-border bg-fl-cream shadow-fl-float"
            >
                <div
                    class="absolute inset-0 bg-[radial-gradient(55%_60%_at_50%_42%,color-mix(in_srgb,var(--fl-gold)_26%,transparent),transparent_70%)]"
                    aria-hidden="true"
                />
                <p
                    class="absolute top-4 left-4 z-10 inline-flex items-center gap-2 rounded-full border border-fl-gold/30 bg-white/90 px-3 py-1.5 text-[11px] font-semibold tracking-[0.14em] text-fl-gold-ink uppercase shadow-fl-micro backdrop-blur"
                >
                    <Nfc class="size-3.5" aria-hidden="true" /> Se abre con un
                    toque
                </p>
                <div class="relative mx-auto w-[58%] pt-8 sm:w-[52%]">
                    <LegacyTag3D
                        v-bind="featuredArt"
                        label="Finisher Legacy Tag en 3D"
                    />
                </div>
                <div class="relative px-6 pt-1 pb-6 text-center">
                    <p class="fl-eyebrow">Finisher Legacy Tag</p>
                </div>
            </div>
        </template>
    </PageHero>

    <!-- Categories -->
    <section
        v-if="visibleCategories.length"
        class="border-y border-border bg-card"
        aria-labelledby="categorias"
    >
        <div class="fl-container py-6 sm:py-8">
            <h2 id="categorias" class="sr-only">Categorías</h2>
            <ul
                class="-mx-4 flex snap-x [scrollbar-width:none] gap-3 overflow-x-auto px-4 pb-1 sm:mx-0 sm:grid sm:grid-cols-2 sm:overflow-visible sm:px-0 lg:grid-cols-4 [&::-webkit-scrollbar]:hidden"
            >
                <li
                    v-for="(category, index) in visibleCategories"
                    :key="category.slug"
                    v-reveal="{ index }"
                    class="w-[62%] shrink-0 snap-start sm:w-auto"
                >
                    <Link
                        :href="categoryShow(category.slug)"
                        class="group flex h-full items-center justify-between gap-4 rounded-card border border-border bg-background px-5 py-4 transition-colors duration-200 hover:border-fl-gold focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        <span class="min-w-0">
                            <span
                                class="block truncate font-serif text-base font-semibold text-foreground sm:text-lg"
                                >{{ category.name }}</span
                            >
                            <span class="text-[13px] text-muted-foreground"
                                >{{ category.products_count }}
                                {{
                                    category.products_count === 1
                                        ? 'producto'
                                        : 'productos'
                                }}</span
                            >
                        </span>
                        <span
                            class="flex size-9 shrink-0 items-center justify-center rounded-full bg-fl-cream text-fl-gold-ink transition-colors duration-300 group-hover:bg-fl-black group-hover:text-fl-gold-soft"
                            aria-hidden="true"
                        >
                            <ArrowUpRight class="size-4" />
                        </span>
                    </Link>
                </li>
            </ul>
        </div>
    </section>

    <!-- Featured tag -->
    <section
        v-if="featuredTag"
        class="fl-section"
        aria-labelledby="tag-destacado"
    >
        <div class="fl-container">
            <div
                v-reveal="{ y: 28 }"
                class="grid overflow-hidden rounded-hero border border-border bg-card shadow-fl-soft lg:grid-cols-2"
            >
                <div
                    class="relative flex min-h-[22rem] items-center justify-center overflow-hidden bg-fl-cream sm:min-h-[28rem]"
                >
                    <img
                        v-if="
                            featuredTag.image_source === 'upload' &&
                            featuredTag.image_url
                        "
                        :src="featuredTag.image_url"
                        :alt="featuredTag.image_alt ?? featuredTag.name"
                        loading="lazy"
                        decoding="async"
                        class="absolute inset-0 size-full object-cover"
                    />
                    <template v-else>
                        <div
                            class="absolute inset-0 bg-[radial-gradient(50%_55%_at_50%_45%,color-mix(in_srgb,var(--fl-gold)_24%,transparent),transparent_70%)]"
                            aria-hidden="true"
                        />
                        <div class="relative w-[38%] max-w-[13rem] py-8">
                            <TagFlipCard :art="featuredArt" />
                        </div>
                    </template>
                </div>
                <div class="flex flex-col justify-center p-7 sm:p-12">
                    <p class="fl-eyebrow">
                        {{
                            featuredTag.collection?.name
                                ? `Colección ${featuredTag.collection.name}`
                                : 'Finisher Legacy Tag'
                        }}
                    </p>
                    <h2
                        id="tag-destacado"
                        class="fl-display mt-4 text-[1.9rem] sm:text-[2.6rem]"
                    >
                        Tu carrera termina.
                        <span class="block font-medium text-fl-gold-ink italic"
                            >Tu historia no.</span
                        >
                    </h2>
                    <p
                        class="mt-4 text-base leading-relaxed text-muted-foreground"
                    >
                        Un tag de colección con chip NFC. Dentro guardas tus
                        fotos, tu historia, tu resultado y un mensaje de voz;
                        quien lo toque con su teléfono lo abre, sin instalar
                        nada.
                    </p>
                    <ol class="mt-6 space-y-3">
                        <li
                            v-for="(step, index) in tagSteps"
                            :key="step"
                            class="flex items-start gap-3 text-sm text-foreground"
                        >
                            <span
                                class="legacy-numeric flex size-6 shrink-0 items-center justify-center rounded-full bg-fl-cream text-[11px] font-semibold text-fl-gold-ink"
                                aria-hidden="true"
                                >{{ index + 1 }}</span
                            >
                            <span class="pt-0.5">{{ step }}</span>
                        </li>
                    </ol>
                    <div
                        class="mt-8 flex flex-wrap items-center gap-x-5 gap-y-3"
                    >
                        <Link
                            :href="productShow(featuredTag.slug)"
                            class="fl-btn-primary"
                        >
                            Ver {{ featuredTag.name }}
                            <ArrowRight class="size-4" aria-hidden="true" />
                        </Link>
                        <p
                            v-if="featuredTag.from_price_minor !== null"
                            class="text-sm text-muted-foreground"
                        >
                            Desde
                            <Money
                                :minor="featuredTag.from_price_minor"
                                :currency="featuredTag.currency"
                                class="font-semibold text-foreground"
                            />
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Tag collections -->
    <section
        v-if="tagRow.length"
        class="fl-section border-t border-border bg-card"
        aria-label="Tags de colección"
    >
        <div class="fl-container">
            <div
                class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between"
            >
                <SectionIntro
                    eyebrow="Tags de colección"
                    title="Un diseño por carrera"
                    accent="y por destino."
                />
                <Link
                    :href="COLLECTIONS_URL"
                    class="inline-flex shrink-0 items-center gap-2 text-sm font-semibold text-foreground"
                >
                    <span class="fl-link fl-link--gold"
                        >Ver todas las colecciones</span
                    >
                    <ArrowRight class="size-4" aria-hidden="true" />
                </Link>
            </div>
            <div
                class="mt-10 grid grid-cols-2 gap-x-4 gap-y-10 sm:gap-x-6 lg:grid-cols-4"
            >
                <ProductCard
                    v-for="(product, index) in tagRow"
                    :key="product.uuid"
                    v-reveal="{ index }"
                    v-bind="productCardProps(product)"
                    :priority="index < 2"
                />
                <Link
                    v-if="tagRow.length < 4"
                    v-reveal="{ index: tagRow.length }"
                    :href="COLLECTIONS_URL"
                    class="group flex aspect-[4/5] flex-col items-center justify-center gap-4 self-start rounded-card border border-dashed border-fl-gold/60 bg-background p-6 text-center transition-colors duration-300 hover:border-fl-gold-dim hover:bg-fl-cream/50"
                >
                    <span
                        class="flex size-12 items-center justify-center rounded-full bg-fl-cream text-fl-gold-ink transition-colors duration-300 group-hover:bg-fl-black group-hover:text-fl-gold-soft"
                        aria-hidden="true"
                    >
                        <ArrowRight class="size-5" />
                    </span>
                    <span
                        class="font-serif text-base leading-snug font-semibold text-foreground sm:text-lg"
                        >Explora todas las colecciones</span
                    >
                    <span class="text-[13px] text-muted-foreground"
                        >Encuentra el diseño de tu carrera.</span
                    >
                </Link>
            </div>
        </div>
    </section>

    <!-- Gear -->
    <section v-if="gearRow.length" class="fl-section" aria-label="Equipo">
        <div class="fl-container">
            <div
                class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between"
            >
                <SectionIntro
                    eyebrow="Equipo y textil"
                    title="Listo para la"
                    accent="línea de salida."
                    text="Lo que llevas puesto y lo que cargas el día de la carrera."
                />
                <Link
                    :href="catalog()"
                    class="inline-flex shrink-0 items-center gap-2 text-sm font-semibold text-foreground"
                >
                    <span class="fl-link fl-link--gold">Ver el catálogo</span>
                    <ArrowRight class="size-4" aria-hidden="true" />
                </Link>
            </div>
            <div
                class="mt-10 grid grid-cols-2 gap-x-4 gap-y-10 sm:gap-x-6 lg:grid-cols-4"
            >
                <ProductCard
                    v-for="(product, index) in gearRow"
                    :key="product.uuid"
                    v-reveal="{ index }"
                    v-bind="productCardProps(product)"
                />
            </div>
        </div>
    </section>

    <!-- Trust notes -->
    <section
        class="border-y border-border bg-card"
        aria-label="Comprar con confianza"
    >
        <ul
            class="fl-container grid gap-8 py-10 sm:grid-cols-3 sm:gap-6 sm:py-12"
        >
            <li
                v-for="(item, index) in trust"
                :key="item.title"
                v-reveal="{ index }"
                class="flex items-start gap-4"
            >
                <span
                    class="flex size-11 shrink-0 items-center justify-center rounded-full bg-fl-cream text-fl-gold-ink"
                    aria-hidden="true"
                >
                    <component
                        :is="item.icon"
                        class="size-5"
                        :stroke-width="1.6"
                    />
                </span>
                <span>
                    <span
                        class="block font-serif text-base font-semibold text-foreground"
                        >{{ item.title }}</span
                    >
                    <span
                        class="mt-1 block text-sm leading-relaxed text-muted-foreground"
                        >{{ item.text }}</span
                    >
                </span>
            </li>
        </ul>
    </section>

    <!-- Learn more -->
    <section class="fl-section" aria-label="Conoce más">
        <div class="fl-container grid gap-5 md:grid-cols-2">
            <article
                v-for="(card, index) in [
                    {
                        eyebrow: 'Cómo funciona',
                        title: 'Del tag a tu recuerdo, en un toque.',
                        cta: 'Ver el paso a paso',
                        href: HOW_IT_WORKS_URL,
                        image: photo.triumph,
                        position: 'object-center',
                    },
                    {
                        eyebrow: 'Portarretrato inteligente',
                        title: 'Tu foto de meta, con tu álbum dentro.',
                        cta: 'Conocer el portarretrato',
                        href: PORTRAIT_URL,
                        image: photo.runner,
                        position: 'object-[30%_30%]',
                    },
                ]"
                :key="card.eyebrow"
                v-reveal="{ index }"
                class="group fl-card fl-card-hover relative flex flex-col overflow-hidden !rounded-hero p-3"
            >
                <div
                    class="fl-zoom relative aspect-[16/9] rounded-media bg-fl-cream"
                >
                    <img
                        :src="card.image.src"
                        :srcset="card.image.srcset"
                        sizes="(min-width: 768px) 50vw, 100vw"
                        :alt="card.image.alt"
                        loading="lazy"
                        decoding="async"
                        class="absolute inset-0 size-full object-cover"
                        :class="card.position"
                    />
                </div>
                <div
                    class="flex flex-1 flex-col px-4 pt-6 pb-5 sm:px-6 sm:pb-6"
                >
                    <p class="fl-eyebrow">{{ card.eyebrow }}</p>
                    <h3
                        class="mt-2 max-w-md font-serif text-2xl leading-tight font-semibold text-foreground"
                    >
                        {{ card.title }}
                    </h3>
                    <Link
                        :href="card.href"
                        class="mt-5 inline-flex items-center gap-2 self-start text-sm font-semibold text-foreground after:absolute after:inset-0"
                    >
                        <span class="fl-link fl-link--gold">{{
                            card.cta
                        }}</span>
                        <ArrowRight
                            class="size-4 transition-transform duration-300 group-hover:translate-x-1 motion-reduce:transform-none"
                            aria-hidden="true"
                        />
                    </Link>
                </div>
            </article>
        </div>
    </section>

    <CtaBand
        title="Elige el tag de tu carrera."
        accent="Guarda lo que viviste."
        text="¿Ya tienes el tuyo? Actívalo acercando tu teléfono o escribiendo su código."
    >
        <template #actions>
            <Link :href="COLLECTIONS_URL" class="mk-cta-primary">
                Ver colecciones
                <ArrowRight class="size-4" aria-hidden="true" />
            </Link>
            <Link :href="ACTIVATE_URL" class="mk-cta-secondary">
                Activar mi tag
            </Link>
        </template>
    </CtaBand>
</template>
