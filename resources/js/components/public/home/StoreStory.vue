<script setup lang="ts">
/**
 * Store band (owner spec §43): the physical product is not merch, it's
 * part of your story. Real products from the catalog (Legacy Plate
 * first); nothing is shown when the catalog is empty.
 */
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import ProductCard from '@/components/shared/ProductCard.vue';
import { Button } from '@/components/ui/button';
import { productCardProps } from '@/lib/store';
import { index as storeIndex } from '@/routes/store/products';
import type { PublicProductCard } from '@/types';

defineProps<{ products: PublicProductCard[] }>();

const lines = [
    'Legacy Plate',
    'Ropa técnica',
    'Accesorios',
    'Racepack',
    'Lo que viene',
];
</script>

<template>
    <section
        v-if="products.length"
        class="py-20 sm:py-28"
        aria-labelledby="tienda-title"
    >
        <div class="fl-container">
            <div class="grid gap-8 lg:grid-cols-12 lg:items-end">
                <div class="lg:col-span-7">
                    <p class="fl-eyebrow">Tienda</p>
                    <h2
                        id="tienda-title"
                        class="fl-display mt-4 text-[2.6rem] sm:text-6xl"
                    >
                        No es merch.
                        <span class="block text-fl-stone italic"
                            >Es parte de tu historia.</span
                        >
                    </h2>
                </div>
                <div class="lg:col-span-5 lg:pb-2">
                    <p class="text-lg leading-relaxed text-muted-foreground">
                        Lo que llevas puesto el día de la carrera y lo que te
                        llevas a casa. Con NFC, se conecta a tu perfil.
                    </p>
                    <ul class="mt-5 flex flex-wrap gap-2" aria-label="Líneas">
                        <li
                            v-for="line in lines"
                            :key="line"
                            class="rounded-full border border-border bg-card px-3 py-1.5 text-xs font-medium text-muted-foreground"
                        >
                            {{ line }}
                        </li>
                    </ul>
                </div>
            </div>

            <div
                class="mt-12 grid grid-cols-2 gap-x-4 gap-y-12 sm:gap-x-6 lg:grid-cols-4"
            >
                <ProductCard
                    v-for="product in products"
                    :key="product.uuid"
                    v-bind="productCardProps(product)"
                />
            </div>

            <div class="mt-12 flex justify-center">
                <Button
                    as-child
                    size="lg"
                    variant="outline"
                    class="h-12 rounded-full bg-card px-7"
                >
                    <Link :href="storeIndex()">
                        Ver tienda <ArrowRight class="size-4" />
                    </Link>
                </Button>
            </div>
        </div>
    </section>
</template>
