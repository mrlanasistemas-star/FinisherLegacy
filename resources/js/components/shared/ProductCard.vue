<script setup lang="ts">
/**
 * Store product card (owner spec §19) — photo-led: big image, second
 * angle on hover, name, type, price, colors, sizes, a visual stock hint
 * (never a count) and the NFC / Nuevo / Preventa / Últimas piezas badges.
 *
 * Hover is deliberately calm and stable (UI System §8): a gentle lift,
 * a soft float shadow and a slight image scale — plus a crossfade to the
 * second product angle only when one exists. No pointer-follow tilt and
 * no CLS: the media box keeps a fixed 4:5 ratio. Everything is CSS and
 * fully reduced-motion aware; nothing ever hides content.
 */
import { Link } from '@inertiajs/vue3';
import { Nfc } from '@lucide/vue';
import { computed } from 'vue';
import Money from '@/components/shared/Money.vue';
import ProductImagePlaceholder from '@/components/shared/ProductImagePlaceholder.vue';
import { PRODUCT_TYPE_LABELS, STOCK_LABELS, swatchColor } from '@/lib/store';
import type { ProductAvailability, ProductColor } from '@/types';

const props = withDefaults(
    defineProps<{
        name: string;
        slug: string;
        type?: string | null;
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
        colors?: ProductColor[];
        sizes?: { label: string; in_stock: boolean }[];
        stockLevel?: 'ok' | 'low' | 'out' | null;
        isNfc?: boolean;
        isNew?: boolean;
        isPresale?: boolean;
        /** Eager-load the image (first row above the fold). */
        priority?: boolean;
    }>(),
    {
        availability: 'available',
        colors: () => [],
        sizes: () => [],
        stockLevel: null,
    },
);

const typeLabel = computed(
    () =>
        props.category ||
        (props.type ? (PRODUCT_TYPE_LABELS[props.type] ?? null) : null),
);

const forSale = computed(() => props.availability === 'available');

const stock = computed<'ok' | 'low' | 'out' | null>(() => {
    if (!forSale.value) {
        return null;
    }

    return props.stockLevel ?? (props.inStock ? 'ok' : 'out');
});

const badges = computed(() => {
    const list: { label: string; tone: 'ink' | 'gold' | 'paper' | 'warn' }[] =
        [];

    if (!forSale.value) {
        list.push({
            label:
                props.availabilityLabel ||
                (props.availability === 'concept'
                    ? 'Concepto'
                    : 'Próximamente'),
            tone: 'paper',
        });
    }

    if (props.isPresale && forSale.value) {
        list.push({ label: 'Preventa', tone: 'gold' });
    }

    if (props.isNew) {
        list.push({ label: 'Nuevo', tone: 'ink' });
    }

    if (props.promotionLabel && forSale.value) {
        list.push({ label: props.promotionLabel, tone: 'warn' });
    }

    if (stock.value === 'low') {
        list.push({ label: 'Últimas piezas', tone: 'paper' });
    }

    return list.slice(0, 3);
});

const visibleColors = computed(() => props.colors.slice(0, 5));
const visibleSizes = computed(() => props.sizes.slice(0, 6));
</script>

<template>
    <Link
        :href="`/tienda/${slug}`"
        class="fl-product-card group flex h-full flex-col rounded-card focus-visible:outline-none"
        :aria-label="`${name}${typeLabel ? ` — ${typeLabel}` : ''}`"
    >
        <div
            class="fl-product-card__media relative aspect-[4/5] overflow-hidden rounded-media border border-border bg-[#f1efe9] group-focus-visible:ring-2 group-focus-visible:ring-ring group-focus-visible:ring-offset-2"
        >
            <template v-if="imageUrl">
                <img
                    :src="imageUrl"
                    :alt="imageAlt || name"
                    width="800"
                    height="1000"
                    :loading="priority ? 'eager' : 'lazy'"
                    decoding="async"
                    class="fl-product-card__img size-full object-cover"
                    :class="hoverImageUrl ? 'group-hover:opacity-0' : ''"
                />
                <img
                    v-if="hoverImageUrl"
                    :src="hoverImageUrl"
                    alt=""
                    aria-hidden="true"
                    loading="lazy"
                    decoding="async"
                    class="fl-product-card__img absolute inset-0 size-full object-cover opacity-0 group-hover:opacity-100"
                />
            </template>
            <ProductImagePlaceholder
                v-else
                :name="name"
                label="Finisher Legacy"
            />

            <div
                v-if="badges.length"
                class="pointer-events-none absolute top-3 left-3 flex flex-wrap gap-1.5"
            >
                <span
                    v-for="badge in badges"
                    :key="badge.label"
                    class="rounded-full px-2.5 py-1 text-[10px] leading-none font-semibold tracking-[0.14em] uppercase"
                    :class="{
                        'bg-foreground text-background': badge.tone === 'ink',
                        'bg-fl-gold text-fl-black': badge.tone === 'gold',
                        'border border-foreground/10 bg-white/90 text-foreground backdrop-blur':
                            badge.tone === 'paper',
                        'bg-[#9f2a1d] text-white': badge.tone === 'warn',
                    }"
                    >{{ badge.label }}</span
                >
            </div>

            <span
                v-if="isNfc"
                class="absolute top-3 right-3 inline-flex items-center gap-1 rounded-full border border-foreground/10 bg-white/90 py-1 pr-2.5 pl-2 text-[10px] leading-none font-semibold tracking-[0.12em] text-foreground uppercase backdrop-blur"
                title="Se conecta a tu perfil con NFC"
            >
                <Nfc class="size-3 text-fl-gold-ink" aria-hidden="true" />
                NFC
            </span>

            <span
                v-if="stock === 'out'"
                class="absolute inset-x-3 bottom-3 rounded-full bg-white/90 py-1.5 text-center text-[11px] font-medium text-muted-foreground backdrop-blur"
                >Agotado por ahora</span
            >
        </div>

        <div class="flex flex-1 flex-col px-0.5 pt-4">
            <div
                class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between sm:gap-3"
            >
                <div class="min-w-0">
                    <p
                        v-if="typeLabel"
                        class="text-[11px] font-medium tracking-[0.16em] text-muted-foreground uppercase"
                    >
                        {{ typeLabel }}
                    </p>
                    <h3
                        class="mt-1 text-[15px] leading-snug font-semibold text-foreground underline-offset-4 group-hover:underline"
                    >
                        {{ name }}
                    </h3>
                </div>
                <p class="shrink-0 text-sm sm:pt-[18px] sm:text-right">
                    <template v-if="!forSale">
                        <span class="text-muted-foreground">{{
                            availability === 'concept'
                                ? 'En desarrollo'
                                : 'Muy pronto'
                        }}</span>
                    </template>
                    <template v-else-if="fromPriceMinor !== null">
                        <span
                            v-if="variantCount && variantCount > 1"
                            class="block text-[10px] tracking-wide text-muted-foreground uppercase"
                            >Desde</span
                        >
                        <Money
                            :minor="fromPriceMinor"
                            :currency="currency"
                            class="legacy-numeric font-semibold"
                            :class="
                                compareAtMinor
                                    ? 'text-[#9f2a1d]'
                                    : 'text-foreground'
                            "
                        />
                        <Money
                            v-if="compareAtMinor"
                            :minor="compareAtMinor"
                            :currency="currency"
                            class="legacy-numeric block text-xs text-muted-foreground line-through"
                        />
                    </template>
                </p>
            </div>

            <p
                v-if="tagline"
                class="mt-1.5 line-clamp-2 text-sm text-muted-foreground"
            >
                {{ tagline }}
            </p>

            <div
                v-if="visibleColors.length || visibleSizes.length || stock"
                class="mt-auto flex flex-wrap items-center gap-x-4 gap-y-2 pt-3"
            >
                <ul
                    v-if="visibleColors.length"
                    class="flex items-center gap-1.5"
                    :aria-label="`Colores: ${colors.map((c) => c.name).join(', ')}`"
                >
                    <li
                        v-for="color in visibleColors"
                        :key="color.name"
                        class="size-3.5 rounded-full ring-1 ring-foreground/15 ring-offset-1 ring-offset-background"
                        :style="{ backgroundColor: swatchColor(color) }"
                        :title="color.name"
                    />
                    <li
                        v-if="colors.length > visibleColors.length"
                        class="text-[11px] text-muted-foreground"
                    >
                        +{{ colors.length - visibleColors.length }}
                    </li>
                </ul>
                <ul
                    v-if="visibleSizes.length"
                    class="flex items-center gap-1.5 text-[11px] font-medium"
                    aria-label="Tallas"
                >
                    <li
                        v-for="size in visibleSizes"
                        :key="size.label"
                        :class="
                            size.in_stock
                                ? 'text-foreground'
                                : 'text-muted-foreground/60 line-through'
                        "
                    >
                        {{ size.label }}
                        <span v-if="!size.in_stock" class="sr-only"
                            >(agotada)</span
                        >
                    </li>
                </ul>
                <p
                    v-if="stock"
                    class="ml-auto inline-flex items-center gap-1.5 text-[11px] text-muted-foreground"
                >
                    <span
                        class="size-1.5 rounded-full"
                        :class="{
                            'bg-[#3f7d64]': stock === 'ok',
                            'bg-[#c98a1b]': stock === 'low',
                            'bg-foreground/25': stock === 'out',
                        }"
                        aria-hidden="true"
                    />
                    {{ STOCK_LABELS[stock] }}
                </p>
            </div>
        </div>
    </Link>
</template>

<style scoped>
.fl-product-card__media {
    transition:
        transform 450ms cubic-bezier(0.16, 1, 0.3, 1),
        box-shadow 450ms ease,
        border-color 300ms ease;
}
/* Calm, premium hover: a gentle lift + float shadow (UI System §8). */
.group:hover .fl-product-card__media {
    transform: translateY(-4px);
    border-color: rgb(23 23 20 / 0.18);
    box-shadow: var(--shadow-fl-float);
}
.fl-product-card__img {
    transition:
        transform 900ms cubic-bezier(0.16, 1, 0.3, 1),
        opacity 500ms ease;
}
.group:hover .fl-product-card__img {
    transform: scale(1.035);
}

@media (prefers-reduced-motion: reduce) {
    .fl-product-card__media,
    .fl-product-card__img {
        transition: none;
        transform: none !important;
    }
}
</style>
