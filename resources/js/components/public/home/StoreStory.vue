<script setup lang="ts">
/**
 * Store band (owner spec §43): the physical product is not merch, it's
 * part of your story. The Legacy Plate is elevated as the hero product
 * (the controller forces it first and passes `legacyPlateSlug`, so the
 * CTA links straight to it) with its photoreal render; the rest of the
 * catalogue rides a horizontal rail instead of a dense grid (UI System
 * §7/§13). Real products only; nothing shows when the catalogue is empty.
 */
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Nfc } from '@lucide/vue';
import { computed, useTemplateRef } from 'vue';
import Reveal from '@/components/motion/Reveal.vue';
import StaggerGroup from '@/components/motion/StaggerGroup.vue';
import ProductCard from '@/components/shared/ProductCard.vue';
import { Button } from '@/components/ui/button';
import { useParallax } from '@/composables/useParallax';
import { MEDIA } from '@/config/media';
import { productCardProps } from '@/lib/store';
import {
    index as storeIndex,
    show as productShow,
} from '@/routes/store/products';
import type { PublicProductCard } from '@/types';

const props = defineProps<{
    products: PublicProductCard[];
    legacyPlateSlug: string | null;
}>();

const platePhoto = MEDIA.plate.hero;

// Scroll parallax for the Legacy Plate hero image (writes `--sp` on its panel).
const plateMediaEl = useTemplateRef<HTMLElement>('plateMedia');
useParallax(plateMediaEl);

// Keep the hero plate out of the rail so it isn't shown twice.
const railProducts = computed(() =>
    props.legacyPlateSlug
        ? props.products.filter((p) => p.slug !== props.legacyPlateSlug)
        : props.products,
);

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
        class="fl-section-lg"
        aria-labelledby="tienda-title"
    >
        <div class="fl-container">
            <div class="grid gap-8 lg:grid-cols-12 lg:items-end">
                <div class="lg:col-span-7">
                    <p class="fl-eyebrow">Tienda</p>
                    <h2
                        id="tienda-title"
                        class="fl-display mt-4 text-[clamp(2.4rem,5vw,3.75rem)]"
                    >
                        No es merch.
                        <span class="block text-fl-stone italic"
                            >Es parte de tu historia.</span
                        >
                    </h2>
                </div>
                <div class="lg:col-span-5 lg:pb-2">
                    <p class="fl-body-lg text-muted-foreground">
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

            <!-- Hero product: the Legacy Plate -->
            <Reveal v-if="legacyPlateSlug" class="mt-14">
                <div
                    class="fl-plate-card group grid overflow-hidden rounded-hero border border-border bg-card shadow-fl-soft transition-[border-color,box-shadow] duration-500 hover:border-fl-gold/45 hover:shadow-fl-float lg:grid-cols-2"
                >
                    <div
                        ref="plateMedia"
                        class="relative aspect-[4/3] overflow-hidden bg-fl-black lg:aspect-auto lg:min-h-[26rem]"
                    >
                        <div class="fl-plate-pan size-full">
                            <img
                                :src="platePhoto.src"
                                :srcset="platePhoto.srcset"
                                sizes="(min-width: 1024px) 50vw, 100vw"
                                :width="platePhoto.width"
                                :height="platePhoto.height"
                                :alt="platePhoto.alt"
                                loading="lazy"
                                decoding="async"
                                class="fl-plate-img size-full object-cover"
                            />
                        </div>
                    </div>
                    <div class="flex flex-col justify-center gap-5 p-8 sm:p-12">
                        <p class="fl-eyebrow flex items-center gap-2">
                            <Nfc class="size-4" aria-hidden="true" /> El
                            producto insignia
                        </p>
                        <h3 class="fl-display text-[clamp(2rem,3.4vw,3rem)]">
                            Legacy Plate
                        </h3>
                        <p class="fl-body-lg max-w-md text-muted-foreground">
                            Zamak niquelado, acabado en resina y NFC integrado.
                            Se sujeta al listón de tu medalla y, con un toque,
                            vuelve a abrir tu carrera.
                        </p>
                        <div
                            class="mt-2 flex flex-wrap items-center gap-x-5 gap-y-3"
                        >
                            <Button
                                as-child
                                size="lg"
                                class="h-12 rounded-full px-6"
                            >
                                <Link :href="productShow(legacyPlateSlug)">
                                    Ver la Legacy Plate
                                    <ArrowRight class="size-4" />
                                </Link>
                            </Button>
                        </div>
                    </div>
                </div>
            </Reveal>

            <!-- The rest of the catalogue as a rail -->
            <StaggerGroup
                v-if="railProducts.length"
                class="fl-rail mt-12 flex gap-5 overflow-x-auto pb-4"
                :stagger-ms="70"
            >
                <div
                    v-for="product in railProducts"
                    :key="product.uuid"
                    class="w-[72vw] shrink-0 snap-start sm:w-64"
                >
                    <ProductCard v-bind="productCardProps(product)" />
                </div>
            </StaggerGroup>

            <div class="mt-10">
                <Link
                    :href="storeIndex()"
                    class="inline-flex min-h-11 items-center gap-2 text-sm font-semibold underline-offset-4 hover:underline"
                >
                    Ver toda la tienda <ArrowRight class="size-4" />
                </Link>
            </div>
        </div>
    </section>
</template>

<style scoped>
.fl-rail {
    scroll-snap-type: x mandatory;
    scrollbar-width: none;
    -ms-overflow-style: none;
    margin-inline: -1rem;
    padding-inline: 1rem;
}
.fl-rail::-webkit-scrollbar {
    display: none;
}

/* Legacy Plate hero image: the pan layer tracks scroll parallax instantly
   (no transition), the image layer carries the hover zoom (transitioned). The
   image is oversized so the drift never exposes the panel edge. */
.fl-plate-pan,
.fl-plate-img {
    transform: none;
}

@media (prefers-reduced-motion: no-preference) {
    .fl-plate-pan {
        transform: translateY(calc(var(--sp, 0) * 22px));
        will-change: transform;
    }
    .fl-plate-img {
        transform: scale(1.08);
        transition: transform 600ms var(--legacy-ease);
    }
    .fl-plate-card:hover .fl-plate-img {
        transform: scale(1.12);
    }
}
</style>
