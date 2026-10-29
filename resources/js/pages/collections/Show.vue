<script setup lang="ts">
/**
 * /colecciones/{slug} — one published collection (CollectionController@show):
 * cinematic hero (admin hero image, or the illustrative tag wearing the
 * collection), its story, its active products with the real price
 * (ResolveProductPrice) and checkout availability, how the tag works,
 * and other collections. Product CTA → /tienda/{slug}.
 */
import { Link } from '@inertiajs/vue3';
import { ArrowRight, ChevronRight } from '@lucide/vue';
import { computed } from 'vue';
import CollectionCard from '@/components/collections/CollectionCard.vue';
import TagProductTile from '@/components/collections/TagProductTile.vue';
import Reveal from '@/components/motion/Reveal.vue';
import SeoHead from '@/components/public/SeoHead.vue';
import TagVisualizer from '@/components/tag/TagVisualizer.vue';
import { ACTIVATE_URL } from '@/config/public-links';
import { howItWorks } from '@/routes';
import { index as collectionsIndex } from '@/routes/collections';
import { show as productShow } from '@/routes/store/products';
import type { CollectionCardData, CollectionDetail } from '@/types/collections';
import { collectionBaseName, collectionSubtitle } from '@/types/collections';

const props = defineProps<{
    collection: CollectionDetail;
    otherCollections: CollectionCardData[];
}>();

const subtitle = computed(() => collectionSubtitle(props.collection));
const baseName = computed(() => collectionBaseName(props.collection));

const seoTitle = computed(
    () =>
        props.collection.seo_title ??
        `Colección ${baseName.value}${props.collection.year ? ` ${props.collection.year}` : ''}`,
);
const seoDescription = computed(
    () =>
        props.collection.seo_description ??
        props.collection.tagline ??
        props.collection.description?.slice(0, 180) ??
        `Finisher Legacy Tag de la colección ${props.collection.name}: el recuerdo físico de tu logro, con tu historia dentro.`,
);

const origin = typeof window === 'undefined' ? '' : window.location.origin;

const jsonLd = computed(() => ({
    '@context': 'https://schema.org',
    '@type': 'CollectionPage',
    name: seoTitle.value,
    description: seoDescription.value,
    hasPart: props.collection.products.map((p) => ({
        '@type': 'Product',
        name: p.name,
        url: origin ? `${origin}${productShow.url(p.slug)}` : undefined,
        image: p.image_url ?? undefined,
        brand: { '@type': 'Brand', name: 'Finisher Legacy' },
        offers:
            p.price_minor !== null
                ? {
                      '@type': 'Offer',
                      price: (p.price_minor / 100).toFixed(2),
                      priceCurrency: p.currency,
                      availability: p.available
                          ? 'https://schema.org/InStock'
                          : 'https://schema.org/OutOfStock',
                  }
                : undefined,
    })),
}));

const steps = [
    {
        n: '01',
        title: 'Lo compras',
        body: 'Eliges tu tag y te lo llevas listo.',
    },
    {
        n: '02',
        title: 'Lo activas',
        body: 'Acercas el teléfono o escribes el código impreso.',
    },
    {
        n: '03',
        title: 'Guardas tu historia',
        body: 'Foto, tiempo, lugar, dedicatoria, audio.',
    },
];
</script>

<template>
    <SeoHead
        :title="seoTitle"
        :description="seoDescription"
        :image="collection.hero_url"
        :json-ld="jsonLd"
    />

    <!-- Hero -->
    <section
        class="relative overflow-hidden bg-background text-foreground"
        aria-labelledby="collection-title"
    >
        <template v-if="collection.hero_url">
            <img
                :src="collection.hero_url"
                alt=""
                fetchpriority="high"
                decoding="async"
                class="absolute inset-0 size-full object-cover"
            />
            <div
                class="absolute inset-0 bg-gradient-to-r from-background via-background/85 to-background/25"
                aria-hidden="true"
            />
            <div
                class="absolute inset-x-0 bottom-0 h-32 bg-gradient-to-t from-background to-transparent"
                aria-hidden="true"
            />
        </template>
        <div
            v-else
            class="pointer-events-none absolute inset-0"
            :style="{
                background: `radial-gradient(45% 60% at 78% 45%, color-mix(in srgb, ${collection.accent_color ?? 'var(--fl-gold)'} 18%, transparent), transparent 70%)`,
            }"
            aria-hidden="true"
        />

        <div
            class="fl-container relative grid min-h-[70svh] items-center gap-12 pt-12 pb-16 sm:pt-20 lg:grid-cols-12"
        >
            <div class="lg:col-span-7">
                <nav
                    aria-label="Ruta de navegación"
                    class="flex items-center gap-1.5 text-sm text-muted-foreground"
                >
                    <Link
                        :href="collectionsIndex()"
                        class="hover:text-foreground"
                        >Colecciones</Link
                    >
                    <ChevronRight class="size-3.5" aria-hidden="true" />
                    <span
                        class="truncate text-foreground"
                        aria-current="page"
                        >{{ collection.name }}</span
                    >
                </nav>
                <p
                    v-if="subtitle"
                    class="mt-10 flex items-center gap-3 text-[11px] font-semibold tracking-[0.24em] text-fl-gold-ink uppercase"
                >
                    <span class="h-px w-10 bg-fl-gold" aria-hidden="true" />
                    {{ subtitle }}
                </p>
                <h1
                    id="collection-title"
                    class="mt-5 font-serif text-[clamp(2.75rem,8vw,6.5rem)] leading-[0.98] font-medium tracking-[-0.03em]"
                >
                    {{ baseName }}
                    <span
                        v-if="collection.year"
                        class="legacy-numeric block text-fl-gold-ink italic"
                        >{{ collection.year }}</span
                    >
                </h1>
                <p
                    v-if="collection.tagline"
                    class="mt-6 max-w-[32rem] text-lg leading-relaxed text-muted-foreground"
                >
                    {{ collection.tagline }}
                </p>
                <p
                    v-if="collection.edition || collection.season"
                    class="mt-3 text-sm text-muted-foreground"
                >
                    {{
                        [collection.edition, collection.season]
                            .filter(Boolean)
                            .join(' · ')
                    }}
                </p>
                <a
                    v-if="collection.products.length"
                    href="#piezas"
                    class="mt-9 inline-flex h-11 items-center gap-2 rounded-full bg-foreground px-6 text-sm font-semibold tracking-wide text-background transition-colors hover:bg-fl-graphite"
                >
                    Ver
                    {{
                        collection.products.length === 1 ? 'el tag' : 'los tags'
                    }}
                    <ArrowRight class="size-4" aria-hidden="true" />
                </a>
            </div>
            <div
                class="mx-auto w-[56%] max-w-[18rem] sm:w-[40%] lg:col-span-4 lg:col-start-9 lg:w-full"
            >
                <TagVisualizer
                    :name="collection.name"
                    :subtitle="subtitle"
                    :year="collection.year"
                    :accent="collection.accent_color"
                />
                <p
                    class="mt-4 text-center text-[11px] tracking-[0.18em] text-muted-foreground uppercase"
                >
                    Render ilustrativo
                </p>
            </div>
        </div>
    </section>

    <!-- Story -->
    <section
        v-if="collection.description"
        class="bg-fl-paper py-16 sm:py-24"
        aria-label="La colección"
    >
        <div class="fl-container">
            <Reveal class="mx-auto max-w-[68ch]">
                <p class="fl-eyebrow">La colección</p>
                <p
                    class="mt-5 font-serif text-[clamp(1.4rem,2.4vw,2rem)] leading-snug whitespace-pre-line text-foreground"
                >
                    {{ collection.description }}
                </p>
            </Reveal>
        </div>
    </section>

    <!-- Products -->
    <section
        id="piezas"
        class="scroll-mt-20 bg-fl-paper py-16 sm:py-24"
        :class="{ 'pt-0 sm:pt-0': collection.description }"
        aria-labelledby="pieces-title"
    >
        <div class="fl-container">
            <div
                class="flex items-end justify-between gap-6 border-t border-foreground/10 pt-12"
            >
                <h2
                    id="pieces-title"
                    class="fl-display text-[clamp(1.9rem,3.6vw,3rem)]"
                >
                    {{
                        collection.products.length === 1
                            ? 'El tag de la colección'
                            : 'Los tags de la colección'
                    }}
                </h2>
            </div>

            <ul
                v-if="collection.products.length"
                class="mt-10 grid gap-x-8 gap-y-14 sm:grid-cols-2 lg:grid-cols-3"
            >
                <Reveal
                    v-for="(product, index) in collection.products"
                    :key="product.uuid"
                    as="li"
                    :delay-ms="(index % 3) * 80"
                >
                    <TagProductTile
                        :name="product.name"
                        :slug="product.slug"
                        :tagline="product.tagline"
                        :image-url="product.image_url"
                        :image-alt="product.image_alt"
                        :price-minor="product.price_minor"
                        :compare-at-minor="product.compare_at_minor"
                        :currency="product.currency"
                        :available="product.available"
                        :status-label="
                            product.is_purchasable
                                ? 'Agotado'
                                : product.availability_label
                        "
                        :art-name="collection.name"
                        :art-subtitle="subtitle"
                        :art-year="collection.year"
                        :accent="collection.accent_color"
                    />
                    <Link
                        :href="productShow(product.slug)"
                        class="mt-4 inline-flex min-h-11 items-center gap-2 text-sm font-semibold text-foreground underline-offset-4 hover:underline"
                    >
                        {{
                            product.available ? 'Ver y comprar' : 'Ver detalles'
                        }}
                        <ArrowRight class="size-4" aria-hidden="true" />
                    </Link>
                </Reveal>
            </ul>
            <p v-else class="mt-10 max-w-lg text-muted-foreground">
                Los tags de esta colección estarán disponibles muy pronto.
            </p>
        </div>
    </section>

    <!-- How it works, briefly -->
    <section
        class="border-y border-fl-line bg-white py-16 text-foreground sm:py-24"
        aria-labelledby="collection-how-title"
    >
        <div class="fl-container grid gap-12 lg:grid-cols-12">
            <div class="lg:col-span-4">
                <p
                    class="text-[11px] font-semibold tracking-[0.24em] text-fl-gold-ink uppercase"
                >
                    Después de comprarlo
                </p>
                <h2
                    id="collection-how-title"
                    class="mt-4 font-serif text-[clamp(1.9rem,3.6vw,3rem)] leading-tight"
                >
                    Tu carrera termina.
                    <span class="text-fl-gold-ink italic">Tu historia no.</span>
                </h2>
                <div class="mt-8 flex flex-wrap gap-x-6 gap-y-2 text-sm">
                    <Link
                        :href="howItWorks()"
                        class="inline-flex min-h-11 items-center gap-2 font-semibold text-foreground underline-offset-4 hover:underline"
                    >
                        Cómo funciona
                        <ArrowRight class="size-4" aria-hidden="true" />
                    </Link>
                    <a
                        :href="ACTIVATE_URL"
                        class="inline-flex min-h-11 items-center text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                        >¿Ya lo tienes? Actívalo</a
                    >
                </div>
            </div>
            <ol class="grid gap-10 sm:grid-cols-3 lg:col-span-7 lg:col-start-6">
                <li
                    v-for="step in steps"
                    :key="step.n"
                    class="border-t border-foreground/10 pt-6"
                >
                    <span class="legacy-numeric text-xs text-fl-gold-ink">{{
                        step.n
                    }}</span>
                    <p class="mt-3 font-serif text-2xl">{{ step.title }}</p>
                    <p class="mt-2 text-[15px] text-muted-foreground">
                        {{ step.body }}
                    </p>
                </li>
            </ol>
        </div>
    </section>

    <!-- Other collections -->
    <section
        v-if="otherCollections.length"
        class="bg-fl-paper py-16 sm:py-24"
        aria-labelledby="other-collections-title"
    >
        <div class="fl-container">
            <div class="flex items-end justify-between gap-6">
                <h2
                    id="other-collections-title"
                    class="fl-display text-[clamp(1.9rem,3.6vw,3rem)]"
                >
                    Otras colecciones
                </h2>
                <Link
                    :href="collectionsIndex()"
                    class="inline-flex min-h-11 shrink-0 items-center gap-2 text-sm font-semibold underline-offset-4 hover:underline"
                >
                    Ver todas <ArrowRight class="size-4" aria-hidden="true" />
                </Link>
            </div>
            <ul
                class="mt-10 grid gap-x-8 gap-y-14 sm:grid-cols-2 lg:grid-cols-3"
            >
                <li v-for="other in otherCollections" :key="other.slug">
                    <CollectionCard :collection="other" />
                </li>
            </ul>
        </div>
    </section>
</template>
