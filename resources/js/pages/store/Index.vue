<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import ProductCard from '@/components/shared/ProductCard.vue';
import { useCanonicalUrl } from '@/composables/useCanonicalUrl';
import type { PublicProductCard } from '@/types';

defineProps<{
    products: PublicProductCard[];
    categories: { name: string; slug: string }[];
    filters: { category: string };
}>();

const canonicalUrl = useCanonicalUrl();
const description =
    'Legacy Plate, ropa técnica y accesorios pensados para acompañarte en cada meta — el ecosistema Finisher Legacy, en un solo lugar.';
</script>

<template>
    <Head title="Tienda — Finisher Legacy">
        <meta name="description" :content="description" />
        <link v-if="canonicalUrl" rel="canonical" :href="canonicalUrl" />
        <meta property="og:type" content="website" />
        <meta property="og:title" content="Tienda — Finisher Legacy" />
        <meta property="og:description" :content="description" />
        <meta v-if="canonicalUrl" property="og:url" :content="canonicalUrl" />
        <meta name="twitter:title" content="Tienda — Finisher Legacy" />
        <meta name="twitter:description" :content="description" />
    </Head>

    <div class="bg-fl-black">
        <section
            class="border-b border-white/10 bg-gradient-to-b from-fl-graphite/40 to-fl-black py-16"
        >
            <div class="mx-auto w-full max-w-[1600px] px-4 sm:px-6 xl:px-8">
                <p
                    class="text-xs font-semibold tracking-[0.3em] text-fl-gold-soft uppercase"
                >
                    Ecosistema Finisher Legacy
                </p>
                <h1 class="mt-3 text-3xl font-black text-white sm:text-4xl">
                    La tienda de tu legado
                </h1>
                <p class="mt-3 max-w-xl text-white/60">
                    Legacy Plate es la puerta de entrada — el equipo que te
                    acompaña en cada meta es el resto del camino.
                </p>
            </div>
        </section>

        <div class="mx-auto w-full max-w-[1600px] px-4 py-10 sm:px-6 xl:px-8">
            <div class="mb-8 flex flex-wrap gap-2">
                <Link
                    href="/tienda"
                    class="rounded-full border px-4 py-1.5 text-xs uppercase"
                    :class="
                        !filters.category
                            ? 'border-fl-gold text-fl-gold-soft'
                            : 'border-white/15 text-white/50 hover:border-white/30'
                    "
                >
                    Todo
                </Link>
                <Link
                    v-for="category in categories"
                    :key="category.slug"
                    :href="`/tienda?category=${category.slug}`"
                    class="rounded-full border px-4 py-1.5 text-xs uppercase"
                    :class="
                        filters.category === category.slug
                            ? 'border-fl-gold text-fl-gold-soft'
                            : 'border-white/15 text-white/50 hover:border-white/30'
                    "
                >
                    {{ category.name }}
                </Link>
            </div>

            <div
                class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
            >
                <ProductCard
                    v-for="product in products"
                    :key="product.uuid"
                    :name="product.name"
                    :slug="product.slug"
                    :category="product.category"
                    :from-price-minor="product.from_price_minor"
                    :currency="product.currency"
                    :in-stock="product.in_stock"
                    :image-url="product.image_url"
                    :hover-image-url="product.hover_image_url"
                    :variant-count="product.variant_count"
                />
            </div>
            <p v-if="!products.length" class="py-16 text-center text-white/30">
                Sin productos disponibles todavía.
            </p>
        </div>
    </div>
</template>
