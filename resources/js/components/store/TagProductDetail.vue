<script setup lang="ts">
/**
 * Product detail for a Finisher Legacy Tag (ProductType::LegacyTag).
 * store/Show.vue renders this instead of its generic layout when
 * `product.type === 'legacy_tag'`; every other product type keeps the
 * existing page untouched.
 *
 * - Gallery: uploaded ProductMedia when present; otherwise the
 *   illustrative TagVisualizer (front/back) — never the old plate renders.
 * - Price/availability: what ProductController resolved for the variant
 *   (ResolveProductPrice + IsVariantAvailableForCheckout for tags).
 * - Add to cart: the existing cart endpoint (store.cart.items).
 * - Material / correa / medidas / instrucciones: only when the admin
 *   filled the product columns. No invented specs.
 */
import { Link, useForm, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    ChevronRight,
    Minus,
    Plus,
    ShieldCheck,
    Truck,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import Money from '@/components/shared/Money.vue';
import ProductCard from '@/components/shared/ProductCard.vue';
import ProductGallery from '@/components/store/ProductGallery.vue';
import type { GalleryEntry } from '@/components/store/ProductGallery.vue';
import ProductStorySections from '@/components/store/ProductStorySections.vue';
import type { ContentSection } from '@/components/store/ProductStorySections.vue';
import VariantPicker from '@/components/store/VariantPicker.vue';
import type { Variant } from '@/components/store/VariantPicker.vue';
import TagVisualizer from '@/components/tag/TagVisualizer.vue';
import { Button } from '@/components/ui/button';
import { ACTIVATE_URL } from '@/config/public-links';
import { productCardProps } from '@/lib/store';
import { howItWorks, login } from '@/routes';
import {
    index as collectionsIndex,
    show as collectionShow,
} from '@/routes/collections';
import { store as addToCartRoute } from '@/routes/store/cart/items';
import { index as storeIndex } from '@/routes/store/products';
import type { ProductAvailability, PublicProductCard } from '@/types';
import { collectionSubtitle } from '@/types/collections';

export type TagProduct = {
    uuid: string;
    name: string;
    slug: string;
    description: string | null;
    tagline: string | null;
    availability: ProductAvailability;
    availability_label: string;
    is_purchasable: boolean;
    requires_shipping: boolean;
    variants: (Variant & { low_stock?: boolean })[];
    gallery: unknown[];
    contentSections: ContentSection[];
    specs: {
        material: string | null;
        strap_type: string | null;
        dimensions: string | null;
        instructions: string | null;
    };
    collection: {
        name: string;
        slug: string;
        tagline: string | null;
        destination: string | null;
        discipline: string | null;
        year: number | null;
        accent_color: string | null;
    } | null;
};

const props = defineProps<{
    product: TagProduct;
    galleryItems: GalleryEntry[];
    videos: { id: number; url: string; poster_url: string | null }[];
    relatedProducts: PublicProductCard[];
}>();

const page = usePage();
const isGuest = computed(() => !page.props.auth.user);

const art = computed(() => ({
    name: props.product.collection?.name ?? props.product.name,
    subtitle: props.product.collection
        ? collectionSubtitle(props.product.collection)
        : null,
    year: props.product.collection?.year ?? null,
    accent: props.product.collection?.accent_color ?? null,
}));

/** Real photography wins; until it exists, the illustrative tag. */
const hasMedia = computed(
    () => props.galleryItems.length > 0 && props.product.gallery.length > 0,
);
const face = ref<'front' | 'back'>('front');
const faces = ['front', 'back'] as const;

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

const quantity = ref(1);
const MAX_QTY = 10;
const form = useForm({
    product_variant_id: selectedVariantId.value,
    quantity: 1,
});

function addToCart() {
    if (!selectedVariant.value?.in_stock) {
        return;
    }

    form.product_variant_id = selectedVariant.value.id;
    form.quantity = quantity.value;
    form.post(addToCartRoute().url, { preserveScroll: true });
}

const ctaLabel = computed(() => {
    if (!selectedVariant.value) {
        return 'Sin opciones disponibles';
    }

    if (!selectedVariant.value.in_stock) {
        return 'Agotado';
    }

    return form.processing ? 'Agregando…' : 'Agregar al carrito';
});

const specRows = computed(() =>
    [
        { label: 'Material', value: props.product.specs.material },
        { label: 'Correa', value: props.product.specs.strap_type },
        { label: 'Medidas', value: props.product.specs.dimensions },
    ].filter((row): row is { label: string; value: string } => !!row.value),
);

const includes = computed(() => [
    `El tag${props.product.collection ? ` de la colección ${props.product.collection.name}` : ''}, terminado de fábrica`,
    props.product.specs.strap_type
        ? `Correa: ${props.product.specs.strap_type}`
        : 'Su correa',
    'NFC, QR de respaldo y código impreso para abrir tu historia',
    'Tu Legacy digital, listo para activarlo cuando quieras',
]);

const how = [
    {
        title: 'Acerca tu teléfono',
        body: 'El NFC del tag abre su página. También puedes escanear el QR del reverso.',
    },
    {
        title: 'Actívalo',
        body: 'Inicia sesión y confírmalo: a partir de ahí, el tag es tuyo.',
    },
    {
        title: 'Construye tu historia',
        body: 'Foto, carrera, tiempo, fecha, lugar, dedicatoria, audio.',
    },
];

const after = computed(() => [
    {
        title: props.product.requires_shipping
            ? 'Te lo entregamos'
            : 'Lo recibes',
        body: props.product.requires_shipping
            ? 'Sigue tu pedido desde Mis pedidos hasta que llegue a tus manos.'
            : 'Te lo llevas listo para activarlo.',
    },
    {
        title: 'Lo activas',
        body: 'Desde Activar: con el NFC, el QR o el código impreso.',
        link: true,
    },
    {
        title: 'Tu historia vive dentro',
        body: 'Agrégale lo que quieras recordar y compártela con quien estuvo ahí.',
    },
]);
</script>

<template>
    <!-- Hero: the object on dark, the purchase on paper -->
    <section
        class="relative -mt-16 overflow-hidden bg-fl-black pt-24 pb-14 text-white sm:pt-28 lg:pb-24"
    >
        <div
            class="pointer-events-none absolute inset-0"
            :style="{
                background: `radial-gradient(45% 55% at 30% 45%, color-mix(in srgb, ${art.accent ?? 'var(--fl-gold)'} 16%, transparent), transparent 70%)`,
            }"
            aria-hidden="true"
        />
        <div class="fl-container relative">
            <nav
                aria-label="Ruta de navegación"
                class="flex flex-wrap items-center gap-1.5 text-sm text-white/55"
            >
                <template v-if="product.collection">
                    <Link :href="collectionsIndex()" class="hover:text-white"
                        >Colecciones</Link
                    >
                    <ChevronRight class="size-3.5" aria-hidden="true" />
                    <Link
                        :href="collectionShow(product.collection.slug)"
                        class="hover:text-white"
                        >{{ product.collection.name }}</Link
                    >
                </template>
                <Link v-else :href="storeIndex()" class="hover:text-white"
                    >Tienda</Link
                >
                <ChevronRight class="size-3.5" aria-hidden="true" />
                <span class="truncate text-white/85" aria-current="page">{{
                    product.name
                }}</span>
            </nav>

            <div class="mt-8 grid items-start gap-10 lg:grid-cols-12 lg:gap-12">
                <!-- Visual -->
                <div class="min-w-0 lg:col-span-7">
                    <div
                        v-if="hasMedia"
                        class="rounded-hero bg-fl-paper p-2 text-foreground sm:p-3"
                    >
                        <ProductGallery
                            :items="galleryItems"
                            :product-name="product.name"
                            :badge="
                                product.availability !== 'available'
                                    ? product.availability_label
                                    : null
                            "
                        />
                    </div>
                    <div v-else>
                        <div
                            class="mx-auto w-[62%] max-w-[22rem] py-6 sm:w-[44%] lg:w-[52%]"
                        >
                            <TagVisualizer
                                :name="art.name"
                                :subtitle="art.subtitle"
                                :year="art.year"
                                :accent="art.accent"
                                :face="face"
                            />
                        </div>
                        <div
                            class="mt-2 flex items-center justify-center gap-2"
                            role="group"
                            aria-label="Cara del tag"
                        >
                            <button
                                v-for="option in faces"
                                :key="option"
                                type="button"
                                class="min-h-11 rounded-full px-5 text-sm font-medium transition-colors"
                                :class="
                                    face === option
                                        ? 'bg-white text-fl-black'
                                        : 'text-white/70 hover:text-white'
                                "
                                :aria-pressed="face === option"
                                @click="face = option"
                            >
                                {{ option === 'front' ? 'Frente' : 'Reverso' }}
                            </button>
                        </div>
                        <p
                            class="mt-3 text-center text-[11px] tracking-[0.18em] text-white/40 uppercase"
                        >
                            Render ilustrativo · fotografía próximamente
                        </p>
                    </div>
                </div>

                <!-- Purchase -->
                <div class="min-w-0 lg:col-span-5">
                    <div
                        class="rounded-hero bg-fl-paper p-6 text-foreground shadow-fl-overlay sm:p-8 lg:sticky lg:top-24"
                    >
                        <p class="fl-eyebrow">
                            Finisher Legacy Tag<template
                                v-if="product.collection"
                            >
                                · {{ product.collection.name }}</template
                            >
                        </p>
                        <h1
                            class="fl-display mt-3 text-[clamp(2rem,3.6vw,3rem)]"
                        >
                            {{ product.name }}
                        </h1>
                        <p
                            v-if="product.tagline"
                            class="mt-3 font-serif text-lg text-foreground/80 italic"
                        >
                            {{ product.tagline }}
                        </p>

                        <div
                            v-if="selectedVariant && product.is_purchasable"
                            class="mt-6 flex flex-wrap items-baseline gap-2"
                        >
                            <Money
                                :minor="
                                    selectedVariant.sale_price_minor ??
                                    selectedVariant.base_price_minor
                                "
                                :currency="selectedVariant.currency"
                                class="font-serif text-[2rem] leading-none text-foreground"
                            />
                            <template v-if="selectedVariant.sale_price_minor">
                                <Money
                                    :minor="selectedVariant.base_price_minor"
                                    :currency="selectedVariant.currency"
                                    class="text-base text-muted-foreground line-through"
                                />
                                <span
                                    class="rounded-full bg-foreground px-2.5 py-0.5 text-[11px] font-semibold text-background uppercase"
                                    >{{
                                        selectedVariant.promotion_label ??
                                        'Oferta'
                                    }}</span
                                >
                            </template>
                        </div>

                        <div
                            v-if="
                                product.is_purchasable &&
                                product.variants.length > 1
                            "
                            class="mt-6"
                        >
                            <VariantPicker
                                v-model="selectedVariantId"
                                :variants="product.variants"
                            />
                        </div>

                        <p
                            v-if="product.is_purchasable && selectedVariant"
                            class="mt-5 flex items-center gap-2 text-sm"
                            role="status"
                        >
                            <span
                                class="size-2 rounded-full"
                                :class="
                                    selectedVariant.in_stock
                                        ? 'bg-fl-gold'
                                        : 'bg-foreground/25'
                                "
                                aria-hidden="true"
                            />
                            {{
                                !selectedVariant.in_stock
                                    ? 'Agotado por ahora'
                                    : selectedVariant.low_stock
                                      ? 'Últimas piezas disponibles'
                                      : 'Disponible'
                            }}
                        </p>

                        <div class="mt-6">
                            <div
                                v-if="!product.is_purchasable"
                                class="rounded-card border border-border bg-card p-5"
                            >
                                <p class="font-semibold">
                                    {{ product.availability_label }}
                                </p>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    Este tag todavía no está a la venta. Vuelve
                                    pronto.
                                </p>
                            </div>
                            <Button
                                v-else-if="isGuest"
                                as-child
                                size="lg"
                                class="h-11 w-full rounded-full"
                            >
                                <Link :href="login()"
                                    >Inicia sesión para comprar</Link
                                >
                            </Button>
                            <div v-else class="flex gap-3">
                                <div
                                    class="flex h-11 items-center rounded-full border border-border bg-card"
                                    role="group"
                                    aria-label="Cantidad"
                                >
                                    <button
                                        type="button"
                                        class="flex size-10 items-center justify-center rounded-full disabled:opacity-30"
                                        aria-label="Quitar uno"
                                        :disabled="quantity <= 1"
                                        @click="quantity--"
                                    >
                                        <Minus class="size-4" />
                                    </button>
                                    <span
                                        class="legacy-numeric w-6 text-center text-sm font-semibold"
                                        aria-live="polite"
                                        >{{ quantity }}</span
                                    >
                                    <button
                                        type="button"
                                        class="flex size-10 items-center justify-center rounded-full disabled:opacity-30"
                                        aria-label="Agregar uno"
                                        :disabled="quantity >= MAX_QTY"
                                        @click="quantity++"
                                    >
                                        <Plus class="size-4" />
                                    </button>
                                </div>
                                <Button
                                    size="lg"
                                    class="h-11 flex-1 rounded-full"
                                    :disabled="
                                        !selectedVariant ||
                                        !selectedVariant.in_stock ||
                                        form.processing
                                    "
                                    @click="addToCart"
                                >
                                    {{ ctaLabel }}
                                </Button>
                            </div>
                            <p
                                v-if="form.hasErrors"
                                class="mt-2 text-sm text-destructive"
                                role="alert"
                            >
                                {{ Object.values(form.errors)[0] }}
                            </p>
                        </div>

                        <p
                            v-if="product.description"
                            class="mt-6 text-[15px] leading-relaxed whitespace-pre-line text-muted-foreground"
                        >
                            {{ product.description }}
                        </p>

                        <ul
                            class="mt-6 grid gap-2.5 border-t border-border pt-6 text-sm text-muted-foreground"
                        >
                            <li
                                v-if="product.requires_shipping"
                                class="flex items-center gap-2.5"
                            >
                                <Truck class="size-4" aria-hidden="true" />
                                Envío a domicilio
                            </li>
                            <li class="flex items-center gap-2.5">
                                <ShieldCheck
                                    class="size-4"
                                    aria-hidden="true"
                                />
                                Pago seguro
                            </li>
                            <li>
                                <a
                                    :href="ACTIVATE_URL"
                                    class="inline-flex min-h-9 items-center gap-1 font-medium text-foreground underline-offset-4 hover:underline"
                                >
                                    ¿Ya tienes tu tag? Actívalo
                                    <ArrowRight
                                        class="size-3.5"
                                        aria-hidden="true"
                                    />
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- What's included + specs -->
    <section
        class="bg-fl-paper py-16 sm:py-24"
        aria-labelledby="tag-includes-title"
    >
        <div class="fl-container grid gap-14 lg:grid-cols-12">
            <div class="lg:col-span-5">
                <p class="fl-eyebrow">Qué incluye</p>
                <h2
                    id="tag-includes-title"
                    class="fl-display mt-4 text-[clamp(1.9rem,3.4vw,2.75rem)]"
                >
                    Un recuerdo que puedes tocar.
                </h2>
                <ul class="mt-8 grid gap-4">
                    <li
                        v-for="item in includes"
                        :key="item"
                        class="flex gap-4 border-t border-foreground/10 pt-4 text-[15px] text-foreground"
                    >
                        <span
                            class="mt-2.5 h-px w-4 shrink-0 bg-fl-gold"
                            aria-hidden="true"
                        />
                        {{ item }}
                    </li>
                </ul>
            </div>

            <div class="lg:col-span-6 lg:col-start-7">
                <dl
                    v-if="specRows.length"
                    class="grid gap-px overflow-hidden rounded-card border border-border bg-border sm:grid-cols-3"
                >
                    <div
                        v-for="row in specRows"
                        :key="row.label"
                        class="bg-card px-5 py-4"
                    >
                        <dt
                            class="text-[10px] font-semibold tracking-[0.16em] text-muted-foreground uppercase"
                        >
                            {{ row.label }}
                        </dt>
                        <dd class="mt-1 font-medium text-foreground">
                            {{ row.value }}
                        </dd>
                    </div>
                </dl>
                <div v-if="product.specs.instructions" class="mt-8">
                    <p class="fl-eyebrow">Cuidados e instrucciones</p>
                    <p
                        class="mt-3 text-[15px] leading-relaxed whitespace-pre-line text-muted-foreground"
                    >
                        {{ product.specs.instructions }}
                    </p>
                </div>

                <div
                    :class="{
                        'mt-12': specRows.length || product.specs.instructions,
                    }"
                >
                    <p class="fl-eyebrow">Cómo funciona</p>
                    <ol class="mt-6 grid gap-8 sm:grid-cols-3">
                        <li v-for="(step, index) in how" :key="step.title">
                            <span
                                class="legacy-numeric text-xs text-fl-gold-ink"
                                >0{{ index + 1 }}</span
                            >
                            <p class="mt-2 font-serif text-xl text-foreground">
                                {{ step.title }}
                            </p>
                            <p
                                class="mt-2 text-sm leading-relaxed text-muted-foreground"
                            >
                                {{ step.body }}
                            </p>
                        </li>
                    </ol>
                    <Link
                        :href="howItWorks()"
                        class="mt-8 inline-flex min-h-11 items-center gap-2 text-sm font-semibold underline-offset-4 hover:underline"
                    >
                        Ver cómo funciona completo
                        <ArrowRight class="size-4" aria-hidden="true" />
                    </Link>
                </div>
            </div>
        </div>
    </section>

    <!-- After buying -->
    <section
        class="bg-fl-graphite py-16 text-white sm:py-24"
        aria-labelledby="tag-after-title"
    >
        <div class="fl-container">
            <p
                class="text-[11px] font-semibold tracking-[0.24em] text-fl-gold uppercase"
            >
                Qué pasa después de comprarlo
            </p>
            <h2
                id="tag-after-title"
                class="mt-4 max-w-2xl font-serif text-[clamp(1.9rem,3.6vw,3rem)] leading-tight"
            >
                Tu carrera termina.
                <span class="text-white/50 italic">Tu historia no.</span>
            </h2>
            <ol class="mt-12 grid gap-10 sm:grid-cols-3">
                <li
                    v-for="(step, index) in after"
                    :key="step.title"
                    class="border-t border-white/15 pt-6"
                >
                    <span class="legacy-numeric text-xs text-fl-gold"
                        >0{{ index + 1 }}</span
                    >
                    <p class="mt-3 font-serif text-2xl">{{ step.title }}</p>
                    <p class="mt-2 text-[15px] text-white/60">
                        {{ step.body }}
                    </p>
                    <a
                        v-if="step.link"
                        :href="ACTIVATE_URL"
                        class="mt-3 inline-flex min-h-11 items-center gap-2 text-sm font-semibold text-fl-gold-soft underline-offset-4 hover:underline"
                    >
                        Ir a Activar
                        <ArrowRight class="size-4" aria-hidden="true" />
                    </a>
                </li>
            </ol>
        </div>
    </section>

    <section
        v-if="product.contentSections.length"
        class="bg-fl-paper py-16 sm:py-24"
        aria-label="Sobre este tag"
    >
        <div class="fl-container">
            <ProductStorySections
                :sections="product.contentSections"
                :videos="videos"
            />
        </div>
    </section>

    <section
        v-if="relatedProducts.length"
        class="bg-fl-paper pb-20 sm:pb-28"
        :class="{ 'pt-16 sm:pt-24': !product.contentSections.length }"
    >
        <div class="fl-container">
            <h2 class="fl-display text-3xl sm:text-4xl">
                También te puede gustar
            </h2>
            <div
                class="mt-8 grid grid-cols-2 gap-x-4 gap-y-10 sm:gap-x-6 lg:grid-cols-4"
            >
                <ProductCard
                    v-for="related in relatedProducts"
                    :key="related.uuid"
                    v-bind="productCardProps(related)"
                />
            </div>
        </div>
    </section>
</template>
