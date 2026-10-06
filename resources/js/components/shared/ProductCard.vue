<script setup lang="ts">
/**
 * Store product card — photo-led, never stock counts. Image priority is
 * resolved server-side (Product::primaryImageUrl): uploaded gallery image
 * → conceptual render for the slug → this card's designed placeholder. "Próximamente" /
 * "Concepto" products are labelled and never show a buy price.
 */
import { Link } from '@inertiajs/vue3';
import Money from '@/components/shared/Money.vue';
import ProductImagePlaceholder from '@/components/shared/ProductImagePlaceholder.vue';
import type { ProductAvailability } from '@/types';

withDefaults(
    defineProps<{
        name: string;
        slug: string;
        category?: string | null;
        fromPriceMinor: number | null;
        currency: string;
        inStock: boolean;
        imageUrl?: string | null;
        imageAlt?: string | null;
        hoverImageUrl?: string | null;
        variantCount?: number;
        tagline?: string | null;
        availability?: ProductAvailability;
        availabilityLabel?: string;
        compareAtMinor?: number | null;
        promotionLabel?: string | null;
    }>(),
    {
        availability: 'available',
    },
);
</script>

<template>
    <Link
        :href="`/tienda/${slug}`"
        class="group flex h-full flex-col focus-visible:outline-none"
    >
        <div
            class="relative aspect-[4/5] overflow-hidden rounded-xl border border-border bg-card transition-colors group-hover:border-foreground/20 group-focus-visible:ring-2 group-focus-visible:ring-ring"
        >
            <template v-if="imageUrl">
                <img
                    :src="imageUrl"
                    :alt="imageAlt || name"
                    width="800"
                    height="1000"
                    loading="lazy"
                    decoding="async"
                    class="size-full object-cover transition duration-700 ease-out group-hover:scale-[1.03]"
                    :class="hoverImageUrl ? 'group-hover:opacity-0' : ''"
                />
                <img
                    v-if="hoverImageUrl"
                    :src="hoverImageUrl"
                    alt=""
                    aria-hidden="true"
                    loading="lazy"
                    decoding="async"
                    class="absolute inset-0 size-full object-cover opacity-0 transition duration-700 group-hover:opacity-100"
                />
            </template>
            <ProductImagePlaceholder
                v-else
                :name="name"
                label="Finisher Legacy"
            />

            <span
                v-if="availability !== 'available'"
                class="absolute top-3 left-3 rounded-full px-2.5 py-1 text-[10px] font-semibold tracking-[0.14em] uppercase"
                :class="
                    availability === 'concept'
                        ? 'border border-foreground/15 bg-white/90 text-foreground'
                        : 'bg-foreground text-background'
                "
            >
                {{
                    availabilityLabel ||
                    (availability === 'concept' ? 'Concepto' : 'Próximamente')
                }}
            </span>
            <span
                v-else-if="promotionLabel"
                class="absolute top-3 left-3 rounded-full bg-red-600 px-2.5 py-1 text-[10px] font-bold tracking-[0.14em] text-white uppercase"
            >
                {{ promotionLabel }}
            </span>
            <span
                v-else-if="!inStock"
                class="absolute top-3 left-3 rounded-full bg-white/90 px-2.5 py-1 text-[10px] font-semibold tracking-[0.14em] text-muted-foreground uppercase"
            >
                Agotado
            </span>
        </div>

        <div class="flex flex-1 flex-col pt-4">
            <p v-if="category" class="fl-eyebrow">{{ category }}</p>
            <h3
                class="mt-1 text-[15px] leading-snug font-semibold text-foreground underline-offset-4 group-hover:underline"
            >
                {{ name }}
            </h3>
            <p
                v-if="tagline"
                class="mt-1 line-clamp-2 text-sm text-muted-foreground"
            >
                {{ tagline }}
            </p>
            <p class="mt-2 text-sm text-foreground">
                <template v-if="availability !== 'available'">
                    <span class="text-muted-foreground">{{
                        availability === 'concept'
                            ? 'En desarrollo'
                            : 'Muy pronto disponible'
                    }}</span>
                </template>
                <template v-else-if="fromPriceMinor !== null">
                    <span v-if="variantCount && variantCount > 1">Desde </span>
                    <Money
                        :minor="fromPriceMinor"
                        :currency="currency"
                        class="font-medium"
                        :class="compareAtMinor ? 'text-red-700' : ''"
                    />
                    <Money
                        v-if="compareAtMinor"
                        :minor="compareAtMinor"
                        :currency="currency"
                        class="ml-1.5 text-xs text-muted-foreground line-through"
                    />
                </template>
                <span v-else class="text-muted-foreground"
                    >Precio por confirmar</span
                >
            </p>
        </div>
    </Link>
</template>
