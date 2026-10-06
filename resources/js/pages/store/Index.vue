<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Check, ShieldCheck, Truck, Undo2 } from '@lucide/vue';
import { computed } from 'vue';
import PlateShowcase from '@/components/public/PlateShowcase.vue';
import SeoHead from '@/components/public/SeoHead.vue';
import Money from '@/components/shared/Money.vue';
import ProductCard from '@/components/shared/ProductCard.vue';
import { Button } from '@/components/ui/button';
import {
    index as storeIndex,
    show as productShow,
} from '@/routes/store/products';
import type { PublicProductCard } from '@/types';

type FeaturedProduct = PublicProductCard & {
    description: string | null;
    gallery: { url: string; alt_text: string | null }[];
};

const props = defineProps<{
    products: PublicProductCard[];
    featured: FeaturedProduct | null;
    categories: {
        name: string;
        slug: string;
        description: string | null;
        products_count: number;
    }[];
    filters: { category: string };
}>();

const activeCategory = computed(
    () =>
        props.categories.find((c) => c.slug === props.filters.category) ?? null,
);

// The featured Legacy Plate already has its own block — don't repeat it
// in the grid right below.
const gridProducts = computed(() =>
    props.featured
        ? props.products.filter((p) => p.uuid !== props.featured!.uuid)
        : props.products,
);

const plateFeatures = [
    'Personaliza con tu nombre',
    'Graba tus logros',
    'Conecta con tu perfil',
    'Tecnología NFC',
];
</script>

<template>
    <SeoHead
        title="Tienda"
        description="Legacy Plate con NFC, textil técnico, enfriamiento y accesorios diseñados para atletas. Calidad, diseño y significado en cada detalle."
    />

    <!-- Header -->
    <section class="fl-container pt-12 pb-10 sm:pt-16">
        <div class="grid items-end gap-6 lg:grid-cols-12">
            <div class="lg:col-span-7">
                <p class="fl-eyebrow flex items-center gap-3">
                    <span class="h-px w-8 bg-fl-gold" aria-hidden="true" />
                    Tienda Finisher Legacy
                </p>
                <h1
                    class="fl-display mt-5 text-[2.6rem] sm:text-6xl lg:text-7xl"
                >
                    {{
                        activeCategory
                            ? activeCategory.name
                            : 'Lleva tu legado contigo.'
                    }}
                </h1>
            </div>
            <p
                class="text-lg leading-relaxed text-muted-foreground lg:col-span-5 lg:pb-2"
            >
                {{
                    activeCategory?.description ||
                    'Productos diseñados para atletas. Calidad, diseño y significado en cada detalle.'
                }}
            </p>
        </div>

        <nav
            class="-mx-4 mt-10 flex gap-2 overflow-x-auto border-b border-border px-4 pb-4 sm:mx-0 sm:flex-wrap sm:px-0"
            aria-label="Categorías"
        >
            <Link
                :href="storeIndex()"
                class="shrink-0 rounded-full px-4 py-2 text-[13px] font-medium tracking-wide transition-colors"
                :class="
                    !filters.category
                        ? 'bg-foreground text-background'
                        : 'border border-border text-muted-foreground hover:border-foreground/25 hover:text-foreground'
                "
                :aria-current="!filters.category ? 'page' : undefined"
            >
                Todo
            </Link>
            <Link
                v-for="category in categories"
                :key="category.slug"
                :href="storeIndex({ query: { category: category.slug } })"
                class="shrink-0 rounded-full px-4 py-2 text-[13px] font-medium tracking-wide uppercase transition-colors"
                :class="
                    filters.category === category.slug
                        ? 'bg-foreground text-background'
                        : 'border border-border text-muted-foreground hover:border-foreground/25 hover:text-foreground'
                "
                :aria-current="
                    filters.category === category.slug ? 'page' : undefined
                "
            >
                {{ category.name }}
            </Link>
        </nav>
    </section>

    <!-- Featured: Legacy Plate -->
    <section v-if="featured" class="fl-container pb-16">
        <div
            class="grid overflow-hidden rounded-2xl border border-border bg-card lg:grid-cols-2"
        >
            <div class="relative bg-fl-cream">
                <img
                    v-if="featured.image_url"
                    :src="featured.image_url"
                    :alt="featured.image_alt || featured.name"
                    class="aspect-square size-full object-cover lg:aspect-auto"
                    fetchpriority="high"
                />
                <div
                    v-else
                    class="flex aspect-square items-center justify-center p-10 lg:aspect-auto lg:h-full lg:min-h-[520px]"
                    style="
                        background: radial-gradient(
                            circle at 50% 45%,
                            rgb(255 255 255 / 0.95),
                            transparent 65%
                        );
                    "
                >
                    <div class="w-full max-w-sm -rotate-3">
                        <PlateShowcase
                            :show-hotspots="false"
                            :show-caption="false"
                            event-name="Tu evento"
                            athlete-name="Tu nombre"
                            time="03:42:18"
                        />
                    </div>
                </div>

                <div
                    v-if="featured.gallery.length > 1"
                    class="absolute right-4 bottom-4 hidden gap-2 sm:flex"
                >
                    <img
                        v-for="item in featured.gallery.slice(1, 3)"
                        :key="item.url"
                        :src="item.url"
                        :alt="item.alt_text || featured.name"
                        loading="lazy"
                        class="size-20 rounded-lg border-2 border-white object-cover shadow-md"
                    />
                </div>
            </div>

            <div class="flex flex-col justify-center p-8 sm:p-12">
                <p class="fl-eyebrow">Producto destacado</p>
                <h2 class="fl-display mt-4 text-4xl uppercase sm:text-5xl">
                    {{ featured.name }}
                </h2>
                <p
                    class="mt-3 text-sm font-medium tracking-wide text-muted-foreground"
                >
                    {{
                        featured.tagline ||
                        'Grabado personalizado · Tecnología NFC'
                    }}
                </p>
                <p class="mt-6 font-serif text-2xl text-foreground">
                    “Tu historia, siempre contigo.”
                </p>
                <p
                    v-if="featured.description"
                    class="mt-4 max-w-md text-[15px] leading-relaxed text-muted-foreground"
                >
                    {{ featured.description }}
                </p>

                <ul class="mt-6 grid gap-2.5 sm:grid-cols-2">
                    <li
                        v-for="feature in plateFeatures"
                        :key="feature"
                        class="flex items-center gap-2.5 text-sm"
                    >
                        <span
                            class="flex size-5 items-center justify-center rounded-full bg-fl-gold/15 text-fl-gold-ink"
                        >
                            <Check class="size-3" />
                        </span>
                        {{ feature }}
                    </li>
                </ul>

                <div
                    class="mt-8 flex flex-col gap-4 sm:flex-row sm:items-center"
                >
                    <Button as-child size="lg" class="h-12 rounded-full px-7">
                        <Link :href="productShow(featured.slug)">
                            Personalizar mi placa
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
            </div>
        </div>
    </section>

    <!-- Catalog -->
    <section class="fl-container pb-16">
        <div
            v-if="gridProducts.length"
            class="grid grid-cols-2 gap-x-4 gap-y-12 sm:gap-x-6 lg:grid-cols-3 xl:grid-cols-4"
        >
            <ProductCard
                v-for="product in gridProducts"
                :key="product.uuid"
                :name="product.name"
                :slug="product.slug"
                :category="product.category"
                :from-price-minor="product.from_price_minor"
                :currency="product.currency"
                :in-stock="product.in_stock"
                :image-url="product.image_url"
                :image-alt="product.image_alt"
                :hover-image-url="product.hover_image_url"
                :variant-count="product.variant_count"
                :tagline="product.tagline"
                :availability="product.availability"
                :availability-label="product.availability_label"
                :compare-at-minor="product.compare_at_minor"
                :promotion-label="product.promotion_label"
            />
        </div>
        <div
            v-else-if="!featured"
            class="rounded-xl border border-dashed border-foreground/15 bg-card px-6 py-16 text-center"
        >
            <p class="font-serif text-2xl">
                Estamos preparando esta colección.
            </p>
            <p class="mt-2 text-muted-foreground">
                Muy pronto encontrarás aquí nuevos productos.
            </p>
            <Button
                v-if="filters.category"
                as-child
                variant="outline"
                class="mt-6 rounded-full"
            >
                <Link :href="storeIndex()">Ver toda la tienda</Link>
            </Button>
        </div>
    </section>

    <!-- Service promises -->
    <section class="border-t border-border bg-card">
        <ul class="fl-container grid gap-8 py-12 sm:grid-cols-3">
            <li class="flex items-start gap-4">
                <Truck class="mt-0.5 size-5 shrink-0 text-fl-gold-ink" />
                <div>
                    <p class="font-semibold">Seguimiento de tu pedido</p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Consulta el estado de cada compra en Mis pedidos.
                    </p>
                </div>
            </li>
            <li class="flex items-start gap-4">
                <ShieldCheck class="mt-0.5 size-5 shrink-0 text-fl-gold-ink" />
                <div>
                    <p class="font-semibold">Diseñado para atletas</p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Productos pensados para entrenar, competir y
                        recuperarte.
                    </p>
                </div>
            </li>
            <li class="flex items-start gap-4">
                <Undo2 class="mt-0.5 size-5 shrink-0 text-fl-gold-ink" />
                <div>
                    <p class="font-semibold">Conectado a tu legado</p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Los productos con Legacy Code se vinculan a tu perfil de
                        atleta.
                    </p>
                </div>
            </li>
        </ul>
    </section>
</template>
