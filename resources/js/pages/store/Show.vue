<script setup lang="ts">
/**
 * Product detail (owner spec §20-§22). Desktop: media 60% / sticky
 * purchase column 40%. Media = ProductGallery (images with zoom, video,
 * 3D, fullscreen). Below: the product's story (ProductContentSection),
 * how it works with Finisher Legacy when it connects by NFC, and
 * related products.
 *
 * Add to cart keeps using the existing cart endpoint (store.cart.items).
 * The Legacy Plate is personalized with an event result, so it is never
 * added from here: its column walks the athlete to their event instead.
 */
import { Link, useForm, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    ChevronRight,
    Minus,
    Nfc,
    PackageCheck,
    Plus,
    ShieldCheck,
    Smartphone,
    Truck,
} from '@lucide/vue';
import { useMediaQuery } from '@vueuse/core';
import { computed, onBeforeUnmount, onMounted, ref, watchEffect } from 'vue';
import PlateClipSteps from '@/components/public/PlateClipSteps.vue';
import SeoHead from '@/components/public/SeoHead.vue';
import Money from '@/components/shared/Money.vue';
import ProductCard from '@/components/shared/ProductCard.vue';
import type { Variant } from '@/components/shared/VariantSelector.vue';
import ProductGallery from '@/components/store/ProductGallery.vue';
import type { GalleryEntry } from '@/components/store/ProductGallery.vue';
import ProductStorySections from '@/components/store/ProductStorySections.vue';
import type { ContentSection } from '@/components/store/ProductStorySections.vue';
import VariantPicker from '@/components/store/VariantPicker.vue';
import { Button } from '@/components/ui/button';
import { productCardProps, PRODUCT_TYPE_LABELS } from '@/lib/store';
import { howItWorks, login } from '@/routes';
import { index as eventsIndex } from '@/routes/events';
import { store as addToCartRoute } from '@/routes/store/cart/items';
import { show as categoryShow } from '@/routes/store/categories';
import { index as storeIndex } from '@/routes/store/products';
import type {
    ProductAvailability,
    ProductMediaItem,
    PublicProductCard,
} from '@/types';

type StoreVariant = Variant & { low_stock?: boolean };

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
    category_slug: string | null;
    is_nfc: boolean;
    is_new: boolean;
    stock_level: 'ok' | 'low' | 'out' | null;
    requires_shipping: boolean;
    image_url: string | null;
    variants: StoreVariant[];
    gallery: (ProductMediaItem & { id: number })[];
    /** Product renders shown until a real photo is uploaded in admin. */
    concept_gallery: { url: string; alt: string; srcset?: string }[];
    contentSections: ContentSection[];
};

const props = defineProps<{
    product: Product;
    relatedProducts: PublicProductCard[];
}>();

const page = usePage();
const isGuest = computed(() => !page.props.auth.user);
const isLegacyPlate = computed(() => props.product.type === 'legacy_plate');
const typeLabel = computed(
    () => props.product.category ?? PRODUCT_TYPE_LABELS[props.product.type],
);

const metaDescription = computed(
    () =>
        props.product.description?.slice(0, 200) ??
        `${props.product.name} — parte del ecosistema Finisher Legacy.`,
);

// Uploaded media → legacy single image → product renders.
const isConcept = computed(
    () =>
        !props.product.gallery.length &&
        props.product.concept_gallery.length > 0,
);

const galleryItems = computed<GalleryEntry[]>(() => {
    if (props.product.gallery.length) {
        // Primary image first, the rest in admin order (3D and video included).
        const items: GalleryEntry[] = [...props.product.gallery]
            .sort(
                (a, b) =>
                    Number(b.is_primary) - Number(a.is_primary) ||
                    a.position - b.position,
            )
            .map((m) => ({
                key: m.uuid,
                type: m.type,
                url: m.url,
                poster_url: m.poster_url,
                alt: m.alt,
            }));

        // Only a video / 3D uploaded: lead with the product image so the
        // page never opens on a heavy model the shopper didn't ask for.
        if (
            !items.some((item) => item.type === 'image') &&
            props.product.image_url
        ) {
            items.unshift({
                key: 'image',
                type: 'image',
                url: props.product.image_url,
                poster_url: null,
                alt: props.product.name,
            });
        }

        return items;
    }

    if (isConcept.value) {
        return props.product.concept_gallery.map((item, index) => ({
            key: `concept-${index}`,
            type: 'image',
            url: item.url,
            srcset: item.srcset,
            poster_url: null,
            alt: item.alt,
        }));
    }

    return props.product.image_url
        ? [
              {
                  key: 'image',
                  type: 'image',
                  url: props.product.image_url,
                  poster_url: null,
                  alt: props.product.name,
              },
          ]
        : [];
});

const shareImage = computed(
    () => galleryItems.value.find((item) => item.type === 'image')?.url ?? null,
);
const videos = computed(() =>
    props.product.gallery
        .filter((m) => m.type === 'video')
        .map((m) => ({ id: m.id, url: m.url, poster_url: m.poster_url })),
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
const fromPrice = computed(() => {
    const prices = props.product.variants.map(
        (v) => v.sale_price_minor ?? v.base_price_minor,
    );

    return prices.length ? Math.min(...prices) : null;
});

const quantity = ref(1);
const MAX_QTY = 10;

const form = useForm({
    product_variant_id: selectedVariantId.value,
    quantity: 1,
});

function addToCart() {
    if (!selectedVariant.value || !selectedVariant.value.in_stock) {
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

const stockHint = computed(() => {
    const v = selectedVariant.value;

    if (!props.product.is_purchasable || !v) {
        return null;
    }

    if (!v.in_stock) {
        return { tone: 'out', text: 'Agotado por ahora' };
    }

    return v.low_stock
        ? { tone: 'low', text: 'Últimas piezas disponibles' }
        : { tone: 'ok', text: 'Disponible para envío' };
});

const showPlateHelp = ref(false);

// Mobile purchase bar: lift floating buttons (BackToTop) above it.
const showBar = computed(
    () =>
        !isLegacyPlate.value &&
        props.product.is_purchasable &&
        selectedVariant.value !== null,
);
const belowLg = useMediaQuery('(max-width: 1023.98px)');
onMounted(() =>
    watchEffect(() =>
        document.documentElement.style.setProperty(
            '--fl-bottom-bar',
            showBar.value && belowLg.value ? '72px' : '0px',
        ),
    ),
);
onBeforeUnmount(() =>
    document.documentElement.style.removeProperty('--fl-bottom-bar'),
);

const plateSteps = [
    {
        title: 'Elige tu evento',
        body: 'La placa se personaliza con tu participación.',
    },
    {
        title: 'Elige tu diseño frontal',
        body: 'Núcleo, Distancia o Trayecto.',
    },
    {
        title: 'Personalizamos tu placa',
        body: 'Tu nombre, fecha, tiempo, distancia y ritmo al frente, protegidos con resina.',
    },
    {
        title: 'Colócala y conéctala',
        body: 'El clip la sujeta al listón; acerca el teléfono al frente y se abre tu Legacy.',
    },
];

const plateSpec = [
    { label: 'Material', value: 'Zamak niquelado' },
    { label: 'Acabado', value: 'Resina protectora' },
    { label: 'Conexión', value: 'NFC' },
    { label: 'Sujeción', value: 'Clip posterior tipo money clip' },
    { label: 'Personalización', value: 'Diseño frontal' },
    { label: 'Medidas', value: '70 × 45 mm' },
];

const savedInMiEquipo = [
    'El producto, con su color y talla',
    'La fecha en que lo conectaste',
    'Los eventos en los que lo usaste',
    'Su enlace a tu perfil y tu legado',
];
</script>

<template>
    <SeoHead
        :title="product.name"
        :description="metaDescription"
        :image="shareImage"
        type="product"
    />

    <div class="fl-container pt-6 pb-28 sm:pt-8 lg:pb-20">
        <nav
            aria-label="Ruta de navegación"
            class="flex flex-wrap items-center gap-1.5 text-sm text-muted-foreground"
        >
            <Link
                :href="storeIndex()"
                class="text-muted-foreground hover:text-foreground"
                >Tienda</Link
            >
            <template v-if="product.category && product.category_slug">
                <ChevronRight class="size-3.5" aria-hidden="true" />
                <Link
                    :href="categoryShow(product.category_slug)"
                    class="text-muted-foreground hover:text-foreground"
                    >{{ product.category }}</Link
                >
            </template>
            <ChevronRight class="size-3.5" aria-hidden="true" />
            <span class="truncate text-foreground" aria-current="page">{{
                product.name
            }}</span>
        </nav>

        <div class="mt-6 grid gap-10 lg:grid-cols-12 lg:gap-12 xl:gap-16">
            <!-- Media · 60% -->
            <div class="min-w-0 lg:col-span-7">
                <ProductGallery
                    :items="galleryItems"
                    :product-name="product.name"
                    :note="isConcept ? 'Render de producto' : null"
                    :badge="
                        product.availability !== 'available'
                            ? product.availability_label
                            : null
                    "
                />
            </div>

            <!-- Purchase · 40% -->
            <div class="min-w-0 lg:col-span-5">
                <div class="lg:sticky lg:top-24">
                    <div class="flex flex-wrap items-center gap-2">
                        <p v-if="typeLabel" class="fl-eyebrow">
                            {{ typeLabel }}
                        </p>
                        <span
                            v-if="product.is_new"
                            class="rounded-full bg-foreground px-2 py-0.5 text-[10px] font-semibold tracking-[0.14em] text-background uppercase"
                            >Nuevo</span
                        >
                        <span
                            v-if="product.is_nfc"
                            class="inline-flex items-center gap-1 rounded-full border border-border bg-card px-2 py-0.5 text-[10px] font-semibold tracking-[0.12em] uppercase"
                        >
                            <Nfc
                                class="size-3 text-fl-gold-ink"
                                aria-hidden="true"
                            />
                            NFC
                        </span>
                    </div>

                    <h1
                        class="fl-display mt-3 text-[2.4rem] sm:text-5xl"
                        :class="isLegacyPlate ? 'uppercase' : ''"
                    >
                        {{ product.name }}
                    </h1>

                    <p
                        v-if="product.tagline || isLegacyPlate"
                        class="mt-3 font-serif text-xl text-foreground/80 italic"
                    >
                        {{
                            product.tagline ||
                            'Una pieza física que conecta tu medalla con tu historia digital.'
                        }}
                    </p>

                    <p
                        v-if="product.description"
                        class="mt-4 line-clamp-5 text-[15px] leading-relaxed text-muted-foreground"
                    >
                        {{ product.description }}
                    </p>

                    <!-- Legacy Plate: personalized through an event -->
                    <template v-if="isLegacyPlate">
                        <p v-if="fromPrice !== null" class="mt-6 text-sm">
                            <span class="text-muted-foreground">Desde </span>
                            <Money
                                :minor="fromPrice"
                                :currency="
                                    product.variants[0]?.currency ?? 'MXN'
                                "
                                class="font-serif text-3xl text-foreground"
                            />
                        </p>

                        <dl
                            class="mt-6 grid grid-cols-2 gap-px overflow-hidden rounded-2xl border border-border bg-border text-sm"
                        >
                            <div
                                v-for="row in plateSpec"
                                :key="row.label"
                                class="bg-card px-4 py-3"
                            >
                                <dt
                                    class="text-[10px] font-semibold tracking-[0.14em] text-muted-foreground uppercase"
                                >
                                    {{ row.label }}
                                </dt>
                                <dd class="mt-0.5 font-medium">
                                    {{ row.value }}
                                </dd>
                            </div>
                        </dl>

                        <ol class="mt-6 space-y-4">
                            <li
                                v-for="(step, index) in plateSteps"
                                :key="step.title"
                                class="flex gap-3"
                            >
                                <span
                                    class="legacy-numeric flex size-7 shrink-0 items-center justify-center rounded-full border border-border bg-card text-[11px] font-semibold"
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

                        <div class="mt-7 flex flex-col gap-3 sm:flex-row">
                            <Button
                                as-child
                                size="lg"
                                class="h-12 rounded-full sm:flex-1"
                            >
                                <Link :href="eventsIndex()">
                                    Personalizar mi placa
                                    <ArrowRight class="size-4" />
                                </Link>
                            </Button>
                            <Button
                                size="lg"
                                variant="outline"
                                class="h-12 rounded-full"
                                :aria-expanded="showPlateHelp"
                                aria-controls="ya-tengo-placa"
                                @click="showPlateHelp = !showPlateHelp"
                            >
                                <Nfc class="size-4" />
                                Ya tengo mi placa
                            </Button>
                        </div>
                        <div
                            v-if="showPlateHelp"
                            id="ya-tengo-placa"
                            class="mt-3 flex gap-3 rounded-2xl border border-border bg-card p-4 text-sm"
                        >
                            <Smartphone
                                class="mt-0.5 size-5 shrink-0 text-fl-gold-ink"
                                aria-hidden="true"
                            />
                            <p class="text-muted-foreground">
                                Acerca tu teléfono al frente de la placa. Se
                                abrirá su página y, con tu sesión iniciada,
                                podrás conectarla a tu perfil en un paso.
                                <Link
                                    v-if="isGuest"
                                    :href="login()"
                                    class="font-medium text-foreground underline underline-offset-4"
                                    >Iniciar sesión</Link
                                >
                            </p>
                        </div>
                        <p class="mt-3 text-xs text-muted-foreground">
                            Elige tu evento en Eventos: si tiene preventa de
                            Legacy Plate podrás seleccionar tu modelo y
                            comprarla ahí mismo.
                        </p>
                    </template>

                    <!-- Everything else: variants, quantity, cart -->
                    <template v-else>
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
                                class="font-serif text-[2rem] leading-none"
                                :class="
                                    selectedVariant.sale_price_minor
                                        ? 'text-[#9f2a1d]'
                                        : 'text-foreground'
                                "
                            />
                            <template v-if="selectedVariant.sale_price_minor">
                                <Money
                                    :minor="selectedVariant.base_price_minor"
                                    :currency="selectedVariant.currency"
                                    class="text-base text-muted-foreground line-through"
                                />
                                <span
                                    class="rounded-full bg-[#9f2a1d] px-2.5 py-0.5 text-[11px] font-bold text-white uppercase"
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
                                product.variants.length
                            "
                            class="mt-7"
                        >
                            <VariantPicker
                                v-model="selectedVariantId"
                                :variants="product.variants"
                            />
                        </div>

                        <p
                            v-if="stockHint"
                            class="mt-5 flex items-center gap-2 text-sm"
                            role="status"
                        >
                            <span
                                class="size-2 rounded-full"
                                :class="{
                                    'bg-[#3f7d64]': stockHint.tone === 'ok',
                                    'bg-[#c98a1b]': stockHint.tone === 'low',
                                    'bg-foreground/25':
                                        stockHint.tone === 'out',
                                }"
                                aria-hidden="true"
                            />
                            {{ stockHint.text }}
                        </p>

                        <div class="mt-6">
                            <div
                                v-if="!product.is_purchasable"
                                class="rounded-2xl border border-border bg-card p-5"
                            >
                                <p class="font-semibold">
                                    {{
                                        product.availability === 'concept'
                                            ? 'Producto en desarrollo'
                                            : 'Muy pronto disponible'
                                    }}
                                </p>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    {{
                                        product.availability === 'concept'
                                            ? 'Estamos diseñando este producto. Aún no está a la venta.'
                                            : 'Este producto todavía no está a la venta. Vuelve pronto.'
                                    }}
                                </p>
                            </div>

                            <Button
                                v-else-if="isGuest"
                                as-child
                                size="lg"
                                class="h-12 w-full rounded-full"
                            >
                                <Link :href="login()"
                                    >Inicia sesión para comprar</Link
                                >
                            </Button>

                            <div v-else class="flex gap-3">
                                <div
                                    class="flex h-12 items-center rounded-full border border-border bg-card"
                                    role="group"
                                    aria-label="Cantidad"
                                >
                                    <button
                                        type="button"
                                        class="flex size-11 items-center justify-center rounded-full text-foreground disabled:opacity-30"
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
                                        class="flex size-11 items-center justify-center rounded-full text-foreground disabled:opacity-30"
                                        aria-label="Agregar uno"
                                        :disabled="quantity >= MAX_QTY"
                                        @click="quantity++"
                                    >
                                        <Plus class="size-4" />
                                    </button>
                                </div>
                                <Button
                                    size="lg"
                                    class="h-12 flex-1 rounded-full"
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
                    </template>

                    <!-- Connected product -->
                    <div
                        v-if="product.is_nfc"
                        class="mt-8 overflow-hidden rounded-2xl border border-border bg-card"
                    >
                        <div class="flex items-start gap-4 p-5">
                            <span
                                class="fl-nfc-dot relative flex size-11 shrink-0 items-center justify-center rounded-full bg-foreground text-fl-gold"
                                aria-hidden="true"
                            >
                                <Nfc class="size-5" />
                            </span>
                            <div>
                                <p class="font-semibold text-foreground">
                                    Producto conectado
                                </p>
                                <p
                                    class="mt-1 text-sm leading-relaxed text-muted-foreground"
                                >
                                    Acerca tu teléfono y se abre su página.
                                    Conéctalo una vez y queda guardado en tu
                                    perfil.
                                </p>
                            </div>
                        </div>
                        <div
                            class="border-t border-border bg-background/60 p-5"
                        >
                            <p
                                class="text-[11px] font-semibold tracking-[0.14em] text-muted-foreground uppercase"
                            >
                                Qué se guarda en Mi equipo
                            </p>
                            <ul class="mt-3 grid gap-2 text-sm sm:grid-cols-2">
                                <li
                                    v-for="item in savedInMiEquipo"
                                    :key="item"
                                    class="flex items-start gap-2"
                                >
                                    <PackageCheck
                                        class="mt-0.5 size-4 shrink-0 text-fl-gold-ink"
                                        aria-hidden="true"
                                    />
                                    {{ item }}
                                </li>
                            </ul>
                        </div>
                    </div>

                    <ul
                        class="mt-6 grid gap-2.5 border-t border-border pt-6 text-sm text-muted-foreground"
                    >
                        <li
                            v-if="product.requires_shipping"
                            class="flex items-center gap-2.5"
                        >
                            <Truck class="size-4" aria-hidden="true" /> Envío a
                            domicilio
                        </li>
                        <li class="flex items-center gap-2.5">
                            <ShieldCheck class="size-4" aria-hidden="true" />
                            Pago seguro
                        </li>
                        <li
                            v-if="product.brand"
                            class="flex items-center gap-2.5"
                        >
                            <span class="size-4" aria-hidden="true" />
                            Marca: {{ product.brand }}
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Legacy Plate: how the clip holds it -->
        <section
            v-if="isLegacyPlate"
            id="como-se-sujeta"
            class="mt-20 border-t border-border pt-14"
        >
            <p class="fl-eyebrow">Cómo se sujeta</p>
            <h2 class="mt-3 mb-8 font-serif text-3xl text-foreground">
                El reverso es funcional: un clip que la mantiene firme.
            </h2>
            <PlateClipSteps />
        </section>

        <!-- The product's story -->
        <section
            v-if="product.contentSections.length"
            class="mt-20 border-t border-border pt-14"
            aria-label="Sobre este producto"
        >
            <ProductStorySections
                :sections="product.contentSections"
                :videos="videos"
            />
        </section>

        <!-- How it works with Finisher Legacy -->
        <section
            v-if="product.is_nfc"
            class="mt-20 overflow-hidden rounded-[1.75rem] border border-border bg-card"
        >
            <div class="grid lg:grid-cols-2">
                <div class="p-8 sm:p-12">
                    <p class="fl-eyebrow">Cómo funciona con Finisher Legacy</p>
                    <h2 class="fl-display mt-4 text-3xl sm:text-4xl">
                        Tu equipo también forma parte de tu historia.
                    </h2>
                    <ol class="mt-8 space-y-5">
                        <li class="flex gap-4">
                            <span class="fl-step">1</span>
                            <p class="text-muted-foreground">
                                <strong class="text-foreground"
                                    >Acerca tu teléfono.</strong
                                >
                                El producto abre su página al instante.
                            </p>
                        </li>
                        <li class="flex gap-4">
                            <span class="fl-step">2</span>
                            <p class="text-muted-foreground">
                                <strong class="text-foreground"
                                    >Conéctalo a tu perfil.</strong
                                >
                                Inicia sesión y confírmalo: queda en Mi equipo.
                            </p>
                        </li>
                        <li class="flex gap-4">
                            <span class="fl-step">3</span>
                            <p class="text-muted-foreground">
                                <strong class="text-foreground"
                                    >Llévalo a tus carreras.</strong
                                >
                                Se suma a tu legado junto a tus resultados y
                                fotos.
                            </p>
                        </li>
                    </ol>
                    <Link
                        :href="howItWorks()"
                        class="mt-8 inline-flex items-center gap-2 text-sm font-semibold text-foreground underline-offset-4 hover:underline"
                    >
                        Descubre cómo funciona
                        <ArrowRight class="size-4" />
                    </Link>
                </div>
                <div class="relative min-h-72 bg-[#efece5]">
                    <img
                        src="/media/brand/plate/legacy-plate-nfc-900.webp"
                        srcset="
                            /media/brand/plate/legacy-plate-nfc-600.webp   600w,
                            /media/brand/plate/legacy-plate-nfc-900.webp   900w,
                            /media/brand/plate/legacy-plate-nfc-1400.webp 1400w
                        "
                        sizes="(min-width: 1024px) 40vw, 100vw"
                        alt="Un teléfono acercándose a un producto Finisher Legacy para abrir su página por NFC"
                        loading="lazy"
                        decoding="async"
                        class="absolute inset-0 size-full object-cover"
                    />
                </div>
            </div>
        </section>

        <!-- Related -->
        <section v-if="relatedProducts.length" class="mt-20">
            <div class="flex items-end justify-between gap-4">
                <h2 class="fl-display text-3xl sm:text-4xl">
                    Completa tu equipo
                </h2>
                <Link
                    :href="storeIndex()"
                    class="hidden items-center gap-2 text-sm font-semibold underline-offset-4 hover:underline sm:inline-flex"
                >
                    Ver la tienda <ArrowRight class="size-4" />
                </Link>
            </div>
            <div
                class="mt-8 grid grid-cols-2 gap-x-4 gap-y-10 sm:gap-x-6 lg:grid-cols-4"
            >
                <ProductCard
                    v-for="related in relatedProducts"
                    :key="related.uuid"
                    v-bind="productCardProps(related)"
                />
            </div>
        </section>
    </div>

    <!-- Mobile purchase bar -->
    <div
        v-if="showBar && selectedVariant"
        class="fixed inset-x-0 bottom-0 z-40 border-t border-border bg-background/95 px-4 py-3 backdrop-blur lg:hidden"
        style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom))"
    >
        <div class="flex items-center gap-3">
            <div class="min-w-0 flex-1">
                <p class="truncate text-xs text-muted-foreground">
                    {{ selectedVariant.name }}
                </p>
                <Money
                    :minor="
                        selectedVariant.sale_price_minor ??
                        selectedVariant.base_price_minor
                    "
                    :currency="selectedVariant.currency"
                    class="font-semibold"
                />
            </div>
            <Button v-if="isGuest" as-child class="h-11 rounded-full px-5">
                <Link :href="login()">Inicia sesión</Link>
            </Button>
            <Button
                v-else
                class="h-11 rounded-full px-5"
                :disabled="!selectedVariant.in_stock || form.processing"
                @click="addToCart"
            >
                {{ ctaLabel }}
            </Button>
        </div>
    </div>
</template>

<style scoped>
.fl-step {
    display: flex;
    width: 1.75rem;
    height: 1.75rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 9999px;
    border: 1px solid var(--border);
    font-size: 0.75rem;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
}
.fl-nfc-dot::after {
    content: '';
    position: absolute;
    inset: -4px;
    border-radius: inherit;
    border: 1px solid color-mix(in srgb, var(--fl-gold) 60%, transparent);
    animation: fl-nfc-ring 2.4s ease-out infinite;
}
@keyframes fl-nfc-ring {
    0% {
        transform: scale(0.9);
        opacity: 0.9;
    }
    100% {
        transform: scale(1.45);
        opacity: 0;
    }
}
@media (prefers-reduced-motion: reduce) {
    .fl-nfc-dot::after {
        animation: none;
        opacity: 0;
    }
}
</style>
