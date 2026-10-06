<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import SectionHeading from '@/components/public/SectionHeading.vue';
import ProductCard from '@/components/shared/ProductCard.vue';
import { index as storeIndex } from '@/routes/store/products';
import type { PublicProductCard } from '@/types';

defineProps<{ products: PublicProductCard[] }>();
</script>

<template>
    <section v-if="products.length" class="fl-container py-16 sm:py-24">
        <SectionHeading
            eyebrow="Tienda"
            title="Lleva tu legado contigo."
            description="Productos diseñados para atletas. Calidad, diseño y significado en cada detalle."
        >
            <template #action>
                <Link
                    :href="storeIndex()"
                    class="inline-flex items-center gap-2 text-sm font-semibold text-foreground underline-offset-4 hover:underline"
                >
                    Ir a la tienda
                    <ArrowRight class="size-4" />
                </Link>
            </template>
        </SectionHeading>

        <div
            class="mt-10 grid grid-cols-2 gap-x-4 gap-y-10 sm:gap-x-6 lg:grid-cols-4"
        >
            <ProductCard
                v-for="product in products.slice(0, 4)"
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
            />
        </div>
    </section>
</template>
