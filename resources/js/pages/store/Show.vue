<script setup lang="ts">
/**
 * Store V2 product detail (product UX consolidation brief §58-§61,
 * §111-§114) — gallery left, purchase info right on desktop; below that,
 * the structured content sections (Cómo funciona / Características /
 * Guía de uso / FAQ) and "Completa tu equipo" related products.
 */
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Package, ShieldCheck, Truck } from '@lucide/vue';
import { computed, ref } from 'vue';
import ProductCard from '@/components/shared/ProductCard.vue';
import VariantSelector from '@/components/shared/VariantSelector.vue';
import type { Variant } from '@/components/shared/VariantSelector.vue';
import { Button } from '@/components/ui/button';
import { useCanonicalUrl } from '@/composables/useCanonicalUrl';

type GalleryItem = {
    id: number;
    type: 'image' | 'video';
    url: string;
    poster_url: string | null;
    alt_text: string | null;
    is_primary: boolean;
};

type ContentSection = {
    type: 'text' | 'features' | 'steps' | 'video' | 'faq';
    title: string;
    content: Record<string, unknown>;
};

type Product = {
    uuid: string;
    name: string;
    slug: string;
    description: string | null;
    type: string;
    brand: string | null;
    category: string | null;
    qr_capable: boolean;
    requires_shipping: boolean;
    image_url: string | null;
    variants: Variant[];
    gallery: GalleryItem[];
    contentSections: ContentSection[];
};

type RelatedProduct = {
    uuid: string;
    name: string;
    slug: string;
    category: string | null;
    from_price_minor: number | null;
    currency: string;
    in_stock: boolean;
    image_url: string | null;
    hover_image_url: string | null;
    variant_count: number;
};

const props = defineProps<{
    product: Product;
    relatedProducts: RelatedProduct[];
}>();

const canonicalUrl = useCanonicalUrl();
const metaDescription = computed(
    () =>
        props.product.description?.slice(0, 200) ??
        `${props.product.name} — parte del ecosistema Finisher Legacy. Conoce precio, opciones y disponibilidad.`,
);
const primaryImageUrl = computed(
    () =>
        props.product.gallery.find((item) => item.is_primary)?.url ??
        props.product.image_url,
);

// Falls back to the legacy single `image_path` when no gallery has been
// configured yet (brief §107: additive, never a breaking migration).
const galleryItems = computed<GalleryItem[]>(() =>
    props.product.gallery.length
        ? props.product.gallery
        : props.product.image_url
          ? [
                {
                    id: 0,
                    type: 'image',
                    url: props.product.image_url,
                    poster_url: null,
                    alt_text: props.product.name,
                    is_primary: true,
                },
            ]
          : [],
);

const activeIndex = ref(0);
const activeItem = computed(
    () => galleryItems.value[activeIndex.value] ?? null,
);

const selectedVariantId = ref<number | null>(
    props.product.variants[0]?.id ?? null,
);
const selectedVariant = computed(
    () =>
        props.product.variants.find((v) => v.id === selectedVariantId.value) ??
        null,
);

const form = useForm({
    product_variant_id: selectedVariantId.value,
    quantity: 1,
});

function addToCart() {
    if (!selectedVariant.value) {
        return;
    }

    form.product_variant_id = selectedVariant.value.id;
    form.post('/carrito/items', { preserveScroll: true });
}

const sectionLabels: Record<string, string> = {
    text: 'Qué es',
    features: 'Características',
    steps: 'Cómo se usa',
    video: 'Cómo funciona',
    faq: 'Preguntas frecuentes',
};

// Vue template expressions don't parse inline TS object-type casts (`as {
// ... }`) — these keep the `content` json's per-type shape typed without
// inlining an `as` cast into the template.
function textBody(section: ContentSection): string {
    return (section.content as { body: string }).body;
}

function featureItems(section: ContentSection): string[] {
    return (section.content as { items: string[] }).items;
}

function stepItems(section: ContentSection): { title: string; body: string }[] {
    return (section.content as { items: { title: string; body: string }[] })
        .items;
}

function faqItems(
    section: ContentSection,
): { question: string; answer: string }[] {
    return (
        section.content as {
            items: { question: string; answer: string }[];
        }
    ).items;
}
</script>

<template>
    <Head :title="`${product.name} — Finisher Legacy`">
        <meta name="description" :content="metaDescription" />
        <link v-if="canonicalUrl" rel="canonical" :href="canonicalUrl" />
        <meta property="og:type" content="product" />
        <meta
            property="og:title"
            :content="`${product.name} — Finisher Legacy`"
        />
        <meta property="og:description" :content="metaDescription" />
        <meta v-if="canonicalUrl" property="og:url" :content="canonicalUrl" />
        <meta
            v-if="primaryImageUrl"
            property="og:image"
            :content="primaryImageUrl"
        />
        <meta
            name="twitter:title"
            :content="`${product.name} — Finisher Legacy`"
        />
        <meta name="twitter:description" :content="metaDescription" />
        <meta
            v-if="primaryImageUrl"
            name="twitter:image"
            :content="primaryImageUrl"
        />
    </Head>

    <div class="bg-fl-black">
        <div class="mx-auto w-full max-w-[1600px] px-4 py-10 sm:px-6 xl:px-8">
            <Link
                href="/tienda"
                class="text-xs tracking-wide text-white/40 uppercase hover:text-fl-gold-soft"
                >← Volver a la tienda</Link
            >

            <div class="mt-6 grid gap-10 lg:grid-cols-2">
                <!-- Gallery -->
                <div>
                    <div
                        class="aspect-square overflow-hidden rounded-2xl border border-white/10 bg-fl-graphite/30"
                    >
                        <video
                            v-if="activeItem?.type === 'video'"
                            :src="activeItem.url"
                            :poster="activeItem.poster_url ?? undefined"
                            controls
                            class="size-full object-cover"
                        />
                        <img
                            v-else-if="activeItem"
                            :src="activeItem.url"
                            :alt="activeItem.alt_text ?? product.name"
                            class="size-full object-cover"
                        />
                        <div
                            v-else
                            class="flex size-full items-center justify-center"
                        >
                            <Package class="size-16 text-white/10" />
                        </div>
                    </div>
                    <div
                        v-if="galleryItems.length > 1"
                        class="mt-3 flex gap-2 overflow-x-auto"
                    >
                        <button
                            v-for="(item, index) in galleryItems"
                            :key="item.id"
                            type="button"
                            class="size-16 shrink-0 overflow-hidden rounded-lg border-2 transition-colors"
                            :class="
                                index === activeIndex
                                    ? 'border-fl-gold'
                                    : 'border-white/10 hover:border-white/30'
                            "
                            @click="activeIndex = index"
                        >
                            <img
                                v-if="item.type === 'image'"
                                :src="item.url"
                                alt=""
                                class="size-full object-cover"
                            />
                            <video
                                v-else
                                :src="item.url"
                                muted
                                class="size-full object-cover"
                            />
                        </button>
                    </div>
                </div>

                <div>
                    <p
                        v-if="product.category"
                        class="text-xs tracking-[0.2em] text-fl-gold-soft/70 uppercase"
                    >
                        {{ product.category }}
                    </p>
                    <h1 class="mt-2 text-3xl font-black text-white">
                        {{ product.name }}
                    </h1>
                    <p v-if="product.brand" class="mt-1 text-sm text-white/40">
                        {{ product.brand }}
                    </p>
                    <p v-if="product.description" class="mt-4 text-white/60">
                        {{ product.description }}
                    </p>

                    <div class="mt-8">
                        <p
                            class="mb-3 text-xs tracking-wide text-white/40 uppercase"
                        >
                            Selecciona una opción
                        </p>
                        <VariantSelector
                            v-model="selectedVariantId"
                            :variants="product.variants"
                        />
                    </div>

                    <div class="mt-8">
                        <Button
                            class="w-full bg-fl-gold text-fl-black hover:bg-fl-gold-soft sm:w-auto"
                            :disabled="
                                !selectedVariant ||
                                !selectedVariant.in_stock ||
                                form.processing
                            "
                            @click="addToCart"
                        >
                            {{
                                selectedVariant && !selectedVariant.in_stock
                                    ? 'Agotado'
                                    : 'Agregar al carrito'
                            }}
                        </Button>
                    </div>

                    <div
                        class="mt-8 space-y-2 border-t border-white/10 pt-6 text-sm text-white/50"
                    >
                        <p
                            v-if="product.requires_shipping"
                            class="flex items-center gap-2"
                        >
                            <Truck class="size-4" /> Incluye envío
                        </p>
                        <p
                            v-if="product.qr_capable"
                            class="flex items-center gap-2"
                        >
                            <ShieldCheck class="size-4" /> Vinculable a tu
                            Legacy Plate
                        </p>
                    </div>
                </div>
            </div>

            <!-- Content sections -->
            <div
                v-if="product.contentSections.length"
                class="mt-16 space-y-10 border-t border-white/10 pt-10"
            >
                <section
                    v-for="(section, index) in product.contentSections"
                    :key="index"
                >
                    <h2 class="text-lg font-bold text-white">
                        {{ section.title || sectionLabels[section.type] }}
                    </h2>

                    <p
                        v-if="section.type === 'text'"
                        class="mt-3 text-white/60"
                    >
                        {{ textBody(section) }}
                    </p>

                    <ul
                        v-else-if="section.type === 'features'"
                        class="mt-3 grid gap-2 sm:grid-cols-2"
                    >
                        <li
                            v-for="(item, i) in featureItems(section)"
                            :key="i"
                            class="flex items-start gap-2 text-white/60"
                        >
                            <span
                                class="mt-1.5 size-1.5 shrink-0 rounded-full bg-fl-gold"
                            />
                            {{ item }}
                        </li>
                    </ul>

                    <ol
                        v-else-if="section.type === 'steps'"
                        class="mt-4 space-y-4"
                    >
                        <li
                            v-for="(step, i) in stepItems(section)"
                            :key="i"
                            class="flex gap-3"
                        >
                            <span
                                class="flex size-6 shrink-0 items-center justify-center rounded-full bg-fl-gold/10 text-xs font-semibold text-fl-gold"
                                >{{ i + 1 }}</span
                            >
                            <div>
                                <p class="font-medium text-white">
                                    {{ step.title }}
                                </p>
                                <p class="text-sm text-white/50">
                                    {{ step.body }}
                                </p>
                            </div>
                        </li>
                    </ol>

                    <div
                        v-else-if="section.type === 'faq'"
                        class="mt-4 divide-y divide-white/5 rounded-xl border border-white/10"
                    >
                        <div
                            v-for="(item, i) in faqItems(section)"
                            :key="i"
                            class="p-4"
                        >
                            <p class="font-medium text-white">
                                {{ item.question }}
                            </p>
                            <p class="mt-1 text-sm text-white/50">
                                {{ item.answer }}
                            </p>
                        </div>
                    </div>
                </section>
            </div>

            <!-- Related products -->
            <div
                v-if="relatedProducts.length"
                class="mt-16 border-t border-white/10 pt-10"
            >
                <h2 class="mb-4 text-lg font-bold text-white">
                    Completa tu equipo
                </h2>
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <ProductCard
                        v-for="related in relatedProducts"
                        :key="related.uuid"
                        :name="related.name"
                        :slug="related.slug"
                        :category="related.category"
                        :from-price-minor="related.from_price_minor"
                        :currency="related.currency"
                        :in-stock="related.in_stock"
                        :image-url="related.image_url"
                        :hover-image-url="related.hover_image_url"
                        :variant-count="related.variant_count"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
