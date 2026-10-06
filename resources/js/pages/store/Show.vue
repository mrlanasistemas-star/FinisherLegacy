<script setup lang="ts">
/**
 * Product detail — gallery left, purchase panel right on desktop; below,
 * the admin-authored content sections and related products. Gallery,
 * variants, prices and availability all come from the backend
 * (ProductMedia / ProductVariant / ProductAvailability).
 *
 * Legacy Plate is personalized with an event result, so it is never
 * added from here (AddCartItem requires the event): its panel walks the
 * athlete to their event's presale instead, and to the existing Legacy
 * Code flow to link a plate they already have.
 */
import { Link, useForm, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    Check,
    Nfc,
    ShieldCheck,
    Truck,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import PlateShowcase from '@/components/public/PlateShowcase.vue';
import SeoHead from '@/components/public/SeoHead.vue';
import LinkPlateDialog from '@/components/shared/LinkPlateDialog.vue';
import Money from '@/components/shared/Money.vue';
import ProductCard from '@/components/shared/ProductCard.vue';
import ProductImagePlaceholder from '@/components/shared/ProductImagePlaceholder.vue';
import VariantSelector from '@/components/shared/VariantSelector.vue';
import type { Variant } from '@/components/shared/VariantSelector.vue';
import { Button } from '@/components/ui/button';
import { login } from '@/routes';
import { index as eventsIndex } from '@/routes/events';
import { store as addToCartRoute } from '@/routes/store/cart/items';
import { index as storeIndex } from '@/routes/store/products';
import type { ProductAvailability, PublicProductCard } from '@/types';

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
    tagline: string | null;
    type: string;
    availability: ProductAvailability;
    availability_label: string;
    is_purchasable: boolean;
    brand: string | null;
    category: string | null;
    qr_capable: boolean;
    requires_shipping: boolean;
    image_url: string | null;
    variants: Variant[];
    gallery: GalleryItem[];
    /** Product renders shown until a real photo is uploaded in admin. */
    concept_gallery: { url: string; alt: string }[];
    contentSections: ContentSection[];
};

const props = defineProps<{
    product: Product;
    relatedProducts: PublicProductCard[];
}>();

const page = usePage();
const isGuest = computed(() => !page.props.auth.user);
const isLegacyPlate = computed(() => props.product.type === 'legacy_plate');

const metaDescription = computed(
    () =>
        props.product.description?.slice(0, 200) ??
        `${props.product.name} — parte del ecosistema Finisher Legacy. Conoce precio, opciones y disponibilidad.`,
);

// Uploaded gallery → legacy single `image_path` → conceptual renders.
const isConcept = computed(
    () =>
        !props.product.gallery.length &&
        props.product.concept_gallery.length > 0,
);
const galleryItems = computed<GalleryItem[]>(() => {
    if (props.product.gallery.length) {
        return props.product.gallery;
    }

    if (isConcept.value) {
        return props.product.concept_gallery.map((item, index) => ({
            id: -1 - index,
            type: 'image' as const,
            url: item.url,
            poster_url: null,
            alt_text: item.alt,
            is_primary: index === 0,
        }));
    }

    return props.product.image_url
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
        : [];
});

const activeIndex = ref(0);
const activeItem = computed(
    () => galleryItems.value[activeIndex.value] ?? null,
);
const primaryImageUrl = computed(
    () => galleryItems.value.find((item) => item.type === 'image')?.url ?? null,
);

const selectedVariantId = ref<number | null>(
    props.product.variants.find((v) => v.in_stock)?.id ??
        props.product.variants[0]?.id ??
        null,
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
    form.post(addToCartRoute().url, { preserveScroll: true });
}

const linkDialogOpen = ref(false);

const sectionLabels: Record<string, string> = {
    text: 'Qué es',
    features: 'Características',
    steps: 'Cómo se usa',
    video: 'Cómo funciona',
    faq: 'Preguntas frecuentes',
};

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

const plateSteps = [
    {
        title: 'Elige tu evento',
        body: 'La placa se personaliza con tu participación.',
    },
    {
        title: 'Selecciona tu layout',
        body: 'Uno de los tres diseños de frente y reverso.',
    },
    {
        title: 'Personalizamos tu placa',
        body: 'Tu nombre, evento, distancia y tiempo, impresos y protegidos con resina.',
    },
    {
        title: 'Acerca tu teléfono',
        body: 'El NFC integrado abre tu Legacy.',
    },
];

const plateFeatures = [
    'Zamak niquelado',
    'Acabado en resina',
    'Personalizada con tu carrera',
    'NFC integrado · sin QR impreso',
];
</script>

<template>
    <SeoHead
        :title="product.name"
        :description="metaDescription"
        :image="primaryImageUrl"
        type="product"
    />

    <div class="fl-container pt-8 pb-16 sm:pt-10">
        <Link
            :href="storeIndex()"
            class="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="size-4" />
            Volver a la tienda
        </Link>

        <div class="mt-6 grid gap-10 lg:grid-cols-12 lg:gap-14">
            <!-- Gallery -->
            <div class="lg:col-span-7">
                <div
                    class="relative aspect-square overflow-hidden rounded-2xl border border-border bg-card"
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
                        fetchpriority="high"
                    />
                    <span
                        v-if="isConcept && activeItem"
                        class="absolute right-4 bottom-4 rounded-full bg-white/85 px-2.5 py-1 text-[10px] font-medium tracking-wide text-muted-foreground backdrop-blur"
                        >Render de producto</span
                    >
                    <div
                        v-else-if="isLegacyPlate"
                        class="flex size-full items-center justify-center bg-fl-cream p-10"
                        style="
                            background-image: radial-gradient(
                                circle at 50% 45%,
                                rgb(255 255 255 / 0.95),
                                transparent 65%
                            );
                        "
                    >
                        <div class="w-full max-w-md -rotate-3">
                            <PlateShowcase
                                event-name="Tu evento"
                                athlete-name="Tu nombre"
                                time="03:42:18"
                            />
                        </div>
                    </div>
                    <ProductImagePlaceholder
                        v-else
                        :name="product.name"
                        size="lg"
                        label="Finisher Legacy"
                    />

                    <span
                        v-if="product.availability !== 'available'"
                        class="absolute top-4 left-4 rounded-full px-3 py-1 text-[11px] font-semibold tracking-[0.14em] uppercase"
                        :class="
                            product.availability === 'concept'
                                ? 'border border-foreground/15 bg-white/90'
                                : 'bg-foreground text-background'
                        "
                        >{{ product.availability_label }}</span
                    >
                </div>

                <div
                    v-if="galleryItems.length > 1"
                    class="mt-3 flex gap-2 overflow-x-auto pb-1"
                    role="tablist"
                    aria-label="Galería del producto"
                >
                    <button
                        v-for="(item, index) in galleryItems"
                        :key="item.id"
                        type="button"
                        role="tab"
                        :aria-selected="index === activeIndex"
                        :aria-label="`Ver imagen ${index + 1}`"
                        class="size-20 shrink-0 overflow-hidden rounded-lg border-2 bg-card transition-colors"
                        :class="
                            index === activeIndex
                                ? 'border-foreground'
                                : 'border-transparent hover:border-foreground/20'
                        "
                        @click="activeIndex = index"
                    >
                        <img
                            v-if="item.type === 'image'"
                            :src="item.url"
                            alt=""
                            loading="lazy"
                            class="size-full object-cover"
                        />
                        <video
                            v-else
                            :src="item.url"
                            muted
                            preload="metadata"
                            class="size-full object-cover"
                        />
                    </button>
                </div>
            </div>

            <!-- Purchase panel -->
            <div class="lg:col-span-5">
                <div class="lg:sticky lg:top-24">
                    <p v-if="product.category" class="fl-eyebrow">
                        {{ product.category }}
                    </p>
                    <h1
                        class="fl-display mt-3 text-4xl sm:text-5xl"
                        :class="isLegacyPlate ? 'uppercase' : ''"
                    >
                        {{ product.name }}
                    </h1>
                    <p
                        v-if="product.tagline || isLegacyPlate"
                        class="mt-3 text-sm font-medium tracking-wide text-muted-foreground"
                    >
                        {{
                            product.tagline ||
                            'Zamak niquelado · resina · NFC integrado'
                        }}
                    </p>
                    <p
                        v-if="isLegacyPlate"
                        class="mt-5 font-serif text-2xl text-foreground"
                    >
                        “Tu historia, siempre contigo.”
                    </p>
                    <p
                        v-if="product.description"
                        class="mt-4 text-[15px] leading-relaxed text-muted-foreground"
                    >
                        {{ product.description }}
                    </p>

                    <!-- Legacy Plate: personalize through an event -->
                    <template v-if="isLegacyPlate">
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

                        <p
                            v-if="selectedVariant"
                            class="mt-6 text-sm text-muted-foreground"
                        >
                            Desde
                            <Money
                                :minor="selectedVariant.base_price_minor"
                                :currency="selectedVariant.currency"
                                class="text-xl font-semibold text-foreground"
                            />
                        </p>

                        <ol
                            class="mt-6 space-y-4 rounded-xl border border-border bg-card p-5"
                        >
                            <li
                                v-for="(step, index) in plateSteps"
                                :key="step.title"
                                class="flex gap-3"
                            >
                                <span
                                    class="legacy-numeric flex size-6 shrink-0 items-center justify-center rounded-full bg-foreground text-[11px] font-semibold text-background"
                                    >{{ index + 1 }}</span
                                >
                                <span>
                                    <span class="block text-sm font-semibold">{{
                                        step.title
                                    }}</span>
                                    <span
                                        class="block text-sm text-muted-foreground"
                                        >{{ step.body }}</span
                                    >
                                </span>
                            </li>
                        </ol>

                        <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                            <Button
                                as-child
                                size="lg"
                                class="h-12 flex-1 rounded-full"
                            >
                                <Link :href="eventsIndex()">
                                    Personalizar mi placa
                                    <ArrowRight class="size-4" />
                                </Link>
                            </Button>
                            <Button
                                v-if="!isGuest"
                                size="lg"
                                variant="outline"
                                class="h-12 rounded-full"
                                @click="linkDialogOpen = true"
                            >
                                <Nfc class="size-4" />
                                Ya tengo mi placa
                            </Button>
                            <Button
                                v-else
                                as-child
                                size="lg"
                                variant="outline"
                                class="h-12 rounded-full"
                            >
                                <Link :href="login()">Ya tengo mi placa</Link>
                            </Button>
                        </div>
                        <p class="mt-3 text-xs text-muted-foreground">
                            Elige tu evento en Eventos: si tiene preventa de
                            Legacy Plate podrás seleccionar tu modelo y
                            comprarla ahí mismo.
                        </p>
                    </template>

                    <!-- Everything else: variants + cart -->
                    <template v-else>
                        <div
                            v-if="
                                product.is_purchasable &&
                                product.variants.length
                            "
                            class="mt-8"
                        >
                            <p
                                class="mb-3 text-xs font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                Selecciona una opción
                            </p>
                            <VariantSelector
                                v-model="selectedVariantId"
                                :variants="product.variants"
                            />
                        </div>

                        <div class="mt-8">
                            <template v-if="!product.is_purchasable">
                                <div
                                    class="rounded-xl border border-border bg-card p-5"
                                >
                                    <p class="font-semibold">
                                        {{
                                            product.availability === 'concept'
                                                ? 'Producto en desarrollo'
                                                : 'Muy pronto disponible'
                                        }}
                                    </p>
                                    <p
                                        class="mt-1 text-sm text-muted-foreground"
                                    >
                                        {{
                                            product.availability === 'concept'
                                                ? 'Estamos diseñando este producto. Aún no está a la venta.'
                                                : 'Este producto todavía no está a la venta. Vuelve pronto.'
                                        }}
                                    </p>
                                </div>
                            </template>
                            <template v-else-if="isGuest">
                                <Button
                                    as-child
                                    size="lg"
                                    class="h-12 w-full rounded-full"
                                >
                                    <Link :href="login()"
                                        >Inicia sesión para comprar</Link
                                    >
                                </Button>
                            </template>
                            <Button
                                v-else
                                size="lg"
                                class="h-12 w-full rounded-full"
                                :disabled="
                                    !selectedVariant ||
                                    !selectedVariant.in_stock ||
                                    form.processing
                                "
                                @click="addToCart"
                            >
                                {{
                                    !selectedVariant
                                        ? 'Sin opciones disponibles'
                                        : !selectedVariant.in_stock
                                          ? 'Agotado'
                                          : form.processing
                                            ? 'Agregando…'
                                            : 'Agregar al carrito'
                                }}
                            </Button>
                        </div>
                    </template>

                    <ul
                        class="mt-8 space-y-2 border-t border-border pt-6 text-sm text-muted-foreground"
                    >
                        <li
                            v-if="product.requires_shipping"
                            class="flex items-center gap-2"
                        >
                            <Truck class="size-4" /> Producto físico con envío
                        </li>
                        <li
                            v-if="product.qr_capable"
                            class="flex items-center gap-2"
                        >
                            <ShieldCheck class="size-4" /> Vinculable a tu
                            perfil con Legacy Code
                        </li>
                        <li
                            v-if="product.brand"
                            class="flex items-center gap-2"
                        >
                            <span class="size-4" aria-hidden="true" />
                            Marca: {{ product.brand }}
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Content sections -->
        <div
            v-if="product.contentSections.length"
            class="mt-20 grid gap-12 border-t border-border pt-14 lg:grid-cols-2"
        >
            <section
                v-for="(section, index) in product.contentSections"
                :key="index"
                :class="section.type === 'faq' ? 'lg:col-span-2' : ''"
            >
                <h2 class="font-serif text-2xl text-foreground sm:text-3xl">
                    {{ section.title || sectionLabels[section.type] }}
                </h2>

                <p
                    v-if="section.type === 'text'"
                    class="mt-4 leading-relaxed whitespace-pre-line text-muted-foreground"
                >
                    {{ textBody(section) }}
                </p>

                <ul
                    v-else-if="section.type === 'features'"
                    class="mt-4 grid gap-2.5 sm:grid-cols-2"
                >
                    <li
                        v-for="(item, i) in featureItems(section)"
                        :key="i"
                        class="flex items-start gap-2.5 text-muted-foreground"
                    >
                        <Check class="mt-1 size-4 shrink-0 text-fl-gold-ink" />
                        {{ item }}
                    </li>
                </ul>

                <ol v-else-if="section.type === 'steps'" class="mt-5 space-y-4">
                    <li
                        v-for="(step, i) in stepItems(section)"
                        :key="i"
                        class="flex gap-3"
                    >
                        <span
                            class="legacy-numeric flex size-6 shrink-0 items-center justify-center rounded-full bg-fl-cream text-xs font-semibold text-fl-gold-ink"
                            >{{ i + 1 }}</span
                        >
                        <div>
                            <p class="font-medium text-foreground">
                                {{ step.title }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                {{ step.body }}
                            </p>
                        </div>
                    </li>
                </ol>

                <div
                    v-else-if="section.type === 'faq'"
                    class="mt-5 divide-y divide-border rounded-xl border border-border bg-card"
                >
                    <details
                        v-for="(item, i) in faqItems(section)"
                        :key="i"
                        class="group p-5"
                    >
                        <summary
                            class="cursor-pointer list-none font-medium text-foreground"
                        >
                            {{ item.question }}
                        </summary>
                        <p class="mt-2 text-sm text-muted-foreground">
                            {{ item.answer }}
                        </p>
                    </details>
                </div>
            </section>
        </div>

        <!-- Related products -->
        <div
            v-if="relatedProducts.length"
            class="mt-20 border-t border-border pt-14"
        >
            <h2 class="font-serif text-3xl text-foreground">
                Completa tu equipo
            </h2>
            <div
                class="mt-8 grid grid-cols-2 gap-x-4 gap-y-10 sm:gap-x-6 lg:grid-cols-4"
            >
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
                    :image-alt="related.image_alt"
                    :hover-image-url="related.hover_image_url"
                    :variant-count="related.variant_count"
                    :tagline="related.tagline"
                    :availability="related.availability"
                    :availability-label="related.availability_label"
                    :compare-at-minor="related.compare_at_minor"
                    :promotion-label="related.promotion_label"
                />
            </div>
        </div>
    </div>

    <LinkPlateDialog v-if="!isGuest" v-model:open="linkDialogOpen" />
</template>
