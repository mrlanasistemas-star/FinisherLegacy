<script setup lang="ts">
/**
 * Product detail for a Finisher Legacy Tag (ProductType::LegacyTag).
 * store/Show.vue renders this instead of its generic layout when
 * `product.type === 'legacy_tag'`; every other product type keeps the
 * existing page untouched.
 *
 * - Gallery: uploaded ProductMedia when present; otherwise the
 *   procedural LegacyTag3D (rotatable, front/back) — never the old plate renders.
 * - Price/availability: what ProductController resolved for the variant
 *   (ResolveProductPrice + IsVariantAvailableForCheckout for tags).
 * - Add to cart: the existing cart endpoint (store.cart.items).
 * - Material / correa / medidas / instrucciones: only when the admin
 *   filled the product columns. No invented specs.
 */
import { Link, useForm, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    BookOpen,
    Check,
    ChevronRight,
    Images,
    Lock,
    Mic,
    Minus,
    Plus,
    Rotate3d,
    ShieldCheck,
    Timer,
    Truck,
    Users,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import FeatureGrid from '@/components/marketing/FeatureGrid.vue';
import SectionIntro from '@/components/marketing/SectionIntro.vue';
import Money from '@/components/shared/Money.vue';
import ProductCard from '@/components/shared/ProductCard.vue';
import ProductGallery from '@/components/store/ProductGallery.vue';
import type { GalleryEntry } from '@/components/store/ProductGallery.vue';
import ProductStorySections from '@/components/store/ProductStorySections.vue';
import type { ContentSection } from '@/components/store/ProductStorySections.vue';
import StickyBuyBar from '@/components/store/StickyBuyBar.vue';
import StoreTrustNotes from '@/components/store/StoreTrustNotes.vue';
import { useStickyBuyBar } from '@/components/store/useStickyBuyBar';
import VariantPicker from '@/components/store/VariantPicker.vue';
import type { Variant } from '@/components/store/VariantPicker.vue';
import LegacyTag3D from '@/components/three/LegacyTag3D.vue';
import { Button } from '@/components/ui/button';
import { useAuthGate } from '@/composables/useAuthGate';
import { vReveal } from '@/composables/useReveal';
import { ACTIVATE_URL } from '@/config/public-links';
import { useI18n } from '@/i18n';
import { productCardProps } from '@/lib/store';
import { howItWorks } from '@/routes';
import {
    index as collectionsIndex,
    show as collectionShow,
} from '@/routes/collections';
import { store as addToCartRoute } from '@/routes/store/cart/items';
import { index as storeIndex } from '@/routes/store/products';
import type { ProductAvailability, PublicProductCard } from '@/types';
import type { TagDesign } from '@/types/collections';
import { tagArt } from '@/types/collections';

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
    /** The tag's design (deck pp. 7–8): artwork + header/back lines. */
    tag_design?: TagDesign | null;
};

const props = defineProps<{
    product: TagProduct;
    galleryItems: GalleryEntry[];
    videos: { id: number; url: string; poster_url: string | null }[];
    relatedProducts: PublicProductCard[];
}>();

const { t } = useI18n();
const page = usePage();
const isGuest = computed(() => !page.props.auth.user);
const { requireAuth, loginUrl } = useAuthGate();

const art = computed(() =>
    tagArt(
        props.product.collection ?? { name: props.product.name },
        props.product.tag_design ?? null,
    ),
);

/** Real photography wins; until it exists, the illustrative tag. */
const hasMedia = computed(
    () => props.galleryItems.length > 0 && props.product.gallery.length > 0,
);
const face = ref<'front' | 'back'>('front');
/** Uploaded GLB → the gallery already has the real 3D; otherwise the procedural tag. */
const hasModel = computed(() =>
    props.galleryItems.some((item) => item.type === 'model_3d'),
);
const view3d = ref(false);
const show3d = computed(() => !hasMedia.value || view3d.value);
const tag3d = ref<{ show: (f: 'front' | 'back') => void } | null>(null);

function showFace(next: 'front' | 'back') {
    face.value = next;
    tag3d.value?.show(next);
}
const faces = ['front', 'back'] as const;

// Mobile sticky buy bar (visual): shows once the buy button scrolls away.
const buyAnchor = ref<HTMLElement | null>(null);
const barEnabled = computed(() => props.product.is_purchasable);
const { visible: barVisible } = useStickyBuyBar(buyAnchor, barEnabled);

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
    if (!selectedVariant.value?.in_stock || !requireAuth()) {
        return;
    }

    form.product_variant_id = selectedVariant.value.id;
    form.quantity = quantity.value;
    form.post(addToCartRoute().url, { preserveScroll: true });
}

const ctaLabel = computed(() => {
    if (!selectedVariant.value) {
        return t('Sin opciones disponibles');
    }

    if (!selectedVariant.value.in_stock) {
        return t('Agotado');
    }

    return form.processing ? t('Agregando…') : t('Agregar al carrito');
});

/** Only what the admin filled, plus the facts every tag shares. */
const sheetRows = computed(() =>
    [
        {
            label: t('Colección'),
            value: props.product.collection?.name ?? null,
        },
        {
            label: t('Destino'),
            value: props.product.collection?.destination ?? null,
        },
        { label: t('Material'), value: props.product.specs.material },
        { label: t('Correa'), value: props.product.specs.strap_type },
        { label: t('Medidas'), value: props.product.specs.dimensions },
        { label: t('Conexión'), value: t('Chip NFC, sin batería') },
        {
            label: t('Para abrirlo'),
            value: t('Un teléfono con NFC o su código'),
        },
    ].filter((row): row is { label: string; value: string } => !!row.value),
);

const includes = computed(() => [
    props.product.collection
        ? t('El tag de la colección {collection}, terminado de fábrica', {
              collection: props.product.collection.name,
          })
        : t('El tag, terminado de fábrica'),
    props.product.specs.strap_type
        ? t('Correa: {strap}', { strap: props.product.specs.strap_type })
        : t('Su correa'),
    t('Chip NFC: acercas tu teléfono y se abre tu historia'),
    t('Tu Legacy digital, listo para activarlo cuando quieras'),
]);

const keeps = computed(() => [
    {
        icon: Images,
        title: t('Tus fotos'),
        text: t(
            'Las de la carrera, la meta y todo lo que quieras conservar de ese día.',
        ),
    },
    {
        icon: BookOpen,
        title: t('Tu historia'),
        text: t(
            'Lo que viviste, contado con tus palabras, y una dedicatoria si quieres.',
        ),
    },
    {
        icon: Timer,
        title: t('Tu resultado'),
        text: t('La carrera, la fecha, el lugar y tu tiempo.'),
    },
    {
        icon: Mic,
        title: t('Un mensaje de voz'),
        text: t(
            'Tu voz o la de alguien que estuvo contigo, para volver a escucharla.',
        ),
    },
    {
        icon: Lock,
        title: t('Tú decides quién lo ve'),
        text: t(
            'Público, solo con enlace o privado. Los enlaces que compartes se pueden revocar.',
        ),
    },
    {
        icon: Users,
        title: t('Tu familia no necesita app'),
        text: t(
            'Quien toca el tag o recibe tu enlace lo abre en el navegador de su teléfono.',
        ),
    },
]);

const after = computed(() => [
    {
        title: props.product.requires_shipping
            ? t('Te lo entregamos')
            : t('Lo recibes'),
        body: props.product.requires_shipping
            ? t(
                  'Sigue tu pedido desde Mis pedidos hasta que llegue a tus manos.',
              )
            : t('Te lo llevas listo para activarlo.'),
    },
    {
        title: t('Lo activas'),
        body: t(
            'Acercas tu teléfono al tag. Si no lee NFC, escribe su código en Activar.',
        ),
        link: true,
    },
    {
        title: t('Tu historia vive dentro'),
        body: t(
            'Agrégale lo que quieras recordar y compártela con quien estuvo ahí.',
        ),
    },
]);
</script>

<template>
    <!-- Hero: the object on warm paper, the purchase on a white card -->
    <section
        class="relative overflow-hidden bg-fl-paper pt-6 pb-14 text-foreground sm:pt-8 lg:pb-20"
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
                :aria-label="$t('Ruta de navegación')"
                class="flex flex-wrap items-center gap-1.5 text-sm text-muted-foreground"
            >
                <template v-if="product.collection">
                    <Link
                        :href="collectionsIndex()"
                        class="hover:text-foreground"
                        >{{ $t('Colecciones') }}</Link
                    >
                    <ChevronRight class="size-3.5" aria-hidden="true" />
                    <Link
                        :href="collectionShow(product.collection.slug)"
                        class="hover:text-foreground"
                        >{{ product.collection.name }}</Link
                    >
                </template>
                <Link v-else :href="storeIndex()" class="hover:text-foreground"
                    >{{ $t('Tienda') }}</Link
                >
                <ChevronRight class="size-3.5" aria-hidden="true" />
                <span class="truncate text-foreground" aria-current="page">{{
                    product.name
                }}</span>
            </nav>

            <div class="mt-8 grid items-start gap-10 lg:grid-cols-12 lg:gap-12">
                <!-- Visual -->
                <div class="min-w-0 lg:col-span-7">
                    <div
                        v-if="hasMedia && !show3d"
                        class="rounded-hero border border-border bg-white p-2 text-foreground shadow-fl-float sm:p-3"
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
                        <div
                            v-if="!hasModel"
                            class="flex justify-center px-2 pt-3 pb-1"
                        >
                            <button
                                type="button"
                                class="inline-flex min-h-11 items-center gap-2 rounded-full border border-border bg-white px-5 text-sm font-medium transition-colors hover:border-foreground/40"
                                @click="view3d = true"
                            >
                                <Rotate3d class="size-4" aria-hidden="true" />
                                {{ $t('Ver en 3D — frente y reverso') }}
                            </button>
                        </div>
                    </div>
                    <div v-else>
                        <div
                            class="mx-auto w-[78%] max-w-[26rem] py-2 sm:w-[56%] lg:w-[68%]"
                        >
                            <LegacyTag3D
                                ref="tag3d"
                                v-bind="art"
                                :label="$t('{name} en 3D', { name: product.name })"
                                @face-change="(f) => (face = f)"
                            />
                        </div>
                        <div
                            class="mt-2 flex flex-wrap items-center justify-center gap-2"
                            role="group"
                            :aria-label="$t('Cara del tag')"
                        >
                            <button
                                v-for="option in faces"
                                :key="option"
                                type="button"
                                class="min-h-11 rounded-full px-5 text-sm font-medium transition-colors"
                                :class="
                                    face === option
                                        ? 'bg-foreground text-background'
                                        : 'text-muted-foreground hover:text-foreground'
                                "
                                :aria-pressed="face === option"
                                @click="showFace(option)"
                            >
                                {{
                                    option === 'front'
                                        ? $t('Frente')
                                        : $t('Reverso')
                                }}
                            </button>
                            <button
                                v-if="hasMedia"
                                type="button"
                                class="min-h-11 rounded-full px-5 text-sm font-medium text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                                @click="view3d = false"
                            >
                                {{ $t('Ver fotos') }}
                            </button>
                        </div>
                        <p
                            class="mt-3 text-center text-[11px] tracking-[0.18em] text-muted-foreground uppercase"
                        >
                            {{
                                $t('Vista 3D ilustrativa · arrastra para girar')
                            }}
                        </p>
                    </div>
                </div>

                <!-- Purchase -->
                <div class="min-w-0 lg:col-span-5">
                    <div
                        class="rounded-hero border border-border bg-white p-6 text-foreground shadow-fl-float sm:p-8 lg:sticky lg:top-24"
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
                                        $t('Oferta')
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
                                    ? $t('Agotado por ahora')
                                    : selectedVariant.low_stock
                                      ? $t('Últimas piezas disponibles')
                                      : $t('Disponible')
                            }}
                        </p>

                        <div ref="buyAnchor" class="mt-6">
                            <div
                                v-if="!product.is_purchasable"
                                class="rounded-card border border-border bg-card p-5"
                            >
                                <p class="font-semibold">
                                    {{ product.availability_label }}
                                </p>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    {{
                                        $t(
                                            'Este tag todavía no está a la venta. Vuelve pronto.',
                                        )
                                    }}
                                </p>
                            </div>
                            <p
                                v-else-if="
                                    isGuest && !selectedVariant?.in_stock
                                "
                                class="rounded-card border border-border bg-card px-5 py-4 text-sm text-muted-foreground"
                            >
                                {{
                                    $t(
                                        'Estamos reponiendo este diseño. Vuelve pronto o búscalo con nuestros aliados.',
                                    )
                                }}
                            </p>
                            <Button
                                v-else-if="isGuest"
                                as-child
                                size="lg"
                                class="h-12 w-full rounded-full"
                            >
                                <Link :href="loginUrl()">{{
                                    $t('Inicia sesión para comprar')
                                }}</Link>
                            </Button>
                            <div v-else class="flex gap-3">
                                <div
                                    class="flex h-11 items-center rounded-full border border-border bg-card"
                                    role="group"
                                    :aria-label="$t('Cantidad')"
                                >
                                    <button
                                        type="button"
                                        class="flex size-10 items-center justify-center rounded-full disabled:opacity-30"
                                        :aria-label="$t('Quitar uno')"
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
                                        :aria-label="$t('Agregar uno')"
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
                                {{ $t('Envío a domicilio') }}
                            </li>
                            <li class="flex items-center gap-2.5">
                                <ShieldCheck
                                    class="size-4"
                                    aria-hidden="true"
                                />
                                {{ $t('Pago seguro') }}
                            </li>
                            <li>
                                <a
                                    :href="ACTIVATE_URL"
                                    class="inline-flex min-h-9 items-center gap-1 font-medium text-foreground underline-offset-4 hover:underline"
                                >
                                    {{ $t('¿Ya tienes tu tag? Actívalo') }}
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

    <!-- What the tag keeps -->
    <section
        class="fl-section border-t border-border bg-white"
        :aria-label="$t('Qué guarda tu tag')"
    >
        <div class="fl-container">
            <SectionIntro
                :eyebrow="$t('Qué guarda tu tag')"
                :title="$t('Todo lo que pasó')"
                :accent="$t('detrás del resultado.')"
                :text="
                    $t(
                        'El tag es la llave. Dentro vive tu recuerdo, y lo completas hoy o poco a poco.',
                    )
                "
            />
            <div class="mt-10">
                <FeatureGrid :items="keeps" :columns="3" />
            </div>
        </div>
    </section>

    <!-- What's included + specs -->
    <section
        class="fl-section bg-fl-paper"
        aria-labelledby="tag-includes-title"
    >
        <div class="fl-container grid gap-10 lg:grid-cols-2 lg:gap-16">
            <div>
                <p class="fl-eyebrow inline-flex items-center gap-3">
                    <span class="h-px w-6 bg-fl-gold" aria-hidden="true" />
                    {{ $t('Qué incluye') }}
                </p>
                <h2
                    id="tag-includes-title"
                    v-reveal
                    class="fl-display mt-4 text-[1.9rem] leading-[1.1] sm:text-[2.6rem]"
                >
                    {{ $t('Un recuerdo que') }}
                    <span class="block font-medium text-fl-gold-ink italic">{{
                        $t('puedes tocar.')
                    }}</span>
                </h2>
                <ul class="mt-8 grid gap-3">
                    <li
                        v-for="(item, index) in includes"
                        :key="item"
                        v-reveal="{ index }"
                        class="flex items-start gap-3.5 text-[15px] leading-relaxed text-foreground"
                    >
                        <span
                            class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-fl-cream text-fl-gold-ink"
                            aria-hidden="true"
                        >
                            <Check class="size-3.5" :stroke-width="2.4" />
                        </span>
                        {{ item }}
                    </li>
                </ul>
            </div>

            <div
                v-reveal="{ delay: 100 }"
                class="fl-card self-start p-6 sm:p-8"
            >
                <p class="fl-eyebrow">{{ $t('Ficha del tag') }}</p>
                <dl class="mt-4 divide-y divide-border">
                    <div
                        v-for="row in sheetRows"
                        :key="row.label"
                        class="flex items-baseline justify-between gap-6 py-3.5"
                    >
                        <dt class="shrink-0 text-sm text-muted-foreground">
                            {{ row.label }}
                        </dt>
                        <dd
                            class="text-right text-sm font-medium text-foreground"
                        >
                            {{ row.value }}
                        </dd>
                    </div>
                </dl>
                <div
                    v-if="product.specs.instructions"
                    class="mt-5 border-t border-border pt-5"
                >
                    <p class="fl-eyebrow">
                        {{ $t('Cuidados e instrucciones') }}
                    </p>
                    <p
                        class="mt-3 text-sm leading-relaxed whitespace-pre-line text-muted-foreground"
                    >
                        {{ product.specs.instructions }}
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- After buying: the three steps -->
    <section
        class="fl-section border-y border-border bg-white"
        aria-labelledby="tag-after-title"
    >
        <div class="fl-container">
            <div
                class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between"
            >
                <div>
                    <p class="fl-eyebrow inline-flex items-center gap-3">
                        <span class="h-px w-6 bg-fl-gold" aria-hidden="true" />
                        {{ $t('Cómo funciona') }}
                    </p>
                    <h2
                        id="tag-after-title"
                        v-reveal
                        class="fl-display mt-4 text-[1.9rem] leading-[1.1] sm:text-[2.6rem]"
                    >
                        {{ $t('Tu carrera termina.') }}
                        <span
                            class="block font-medium text-fl-gold-ink italic"
                            >{{ $t('Tu historia no.') }}</span
                        >
                    </h2>
                </div>
                <Link
                    :href="howItWorks()"
                    class="inline-flex min-h-11 shrink-0 items-center gap-2 text-sm font-semibold text-foreground"
                >
                    <span class="fl-link fl-link--gold">{{
                        $t('Ver el paso a paso completo')
                    }}</span>
                    <ArrowRight class="size-4" aria-hidden="true" />
                </Link>
            </div>
            <ol class="mt-10 grid gap-4 sm:grid-cols-3 sm:gap-5">
                <li
                    v-for="(step, index) in after"
                    :key="step.title"
                    v-reveal="{ index }"
                    class="fl-card fl-card-hover relative flex flex-col p-6 sm:p-7"
                >
                    <span
                        class="legacy-numeric flex size-10 items-center justify-center rounded-full bg-fl-black font-serif text-sm font-semibold text-fl-gold-soft"
                        aria-hidden="true"
                        >{{ index + 1 }}</span
                    >
                    <p
                        class="mt-5 font-serif text-xl font-semibold text-foreground"
                    >
                        {{ step.title }}
                    </p>
                    <p
                        class="mt-2 text-[15px] leading-relaxed text-muted-foreground"
                    >
                        {{ step.body }}
                    </p>
                    <a
                        v-if="step.link"
                        :href="ACTIVATE_URL"
                        class="mt-3 inline-flex min-h-11 items-center gap-2 text-sm font-semibold text-fl-gold-ink"
                    >
                        <span class="fl-link">{{ $t('Ir a Activar') }}</span>
                        <ArrowRight class="size-4" aria-hidden="true" />
                    </a>
                </li>
            </ol>
        </div>
    </section>

    <!-- Trust -->
    <section class="bg-fl-paper" :aria-label="$t('Comprar con confianza')">
        <div class="fl-container py-12 sm:py-14">
            <StoreTrustNotes :shipping="product.requires_shipping" />
        </div>
    </section>

    <section
        v-if="product.contentSections.length"
        class="fl-section border-t border-border bg-fl-paper"
        :aria-label="$t('Sobre este tag')"
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
        class="fl-section border-t border-border bg-white"
    >
        <div class="fl-container">
            <div class="flex items-end justify-between gap-4">
                <h2 class="fl-display text-[1.9rem] sm:text-4xl">
                    {{ $t('También te puede gustar') }}
                </h2>
                <Link
                    :href="collectionsIndex()"
                    class="hidden shrink-0 items-center gap-2 text-sm font-semibold sm:inline-flex"
                >
                    <span class="fl-link fl-link--gold">{{
                        $t('Ver colecciones')
                    }}</span>
                    <ArrowRight class="size-4" aria-hidden="true" />
                </Link>
            </div>
            <div
                class="mt-8 grid grid-cols-2 gap-x-4 gap-y-10 sm:gap-x-6 lg:grid-cols-4"
            >
                <ProductCard
                    v-for="(related, index) in relatedProducts"
                    :key="related.uuid"
                    v-reveal="{ index }"
                    v-bind="productCardProps(related)"
                />
            </div>
        </div>
    </section>

    <!-- Mobile sticky buy bar -->
    <StickyBuyBar
        v-if="selectedVariant && product.is_purchasable"
        :visible="barVisible"
        :label="product.name"
    >
        <Money
            :minor="
                selectedVariant.sale_price_minor ??
                selectedVariant.base_price_minor
            "
            :currency="selectedVariant.currency"
        />
        <template #action>
            <span
                v-if="isGuest && !selectedVariant.in_stock"
                class="text-sm text-muted-foreground"
                >{{ $t('Agotado por ahora') }}</span
            >
            <Button v-else-if="isGuest" as-child class="h-11 rounded-full px-5">
                <Link :href="loginUrl()">{{ $t('Inicia sesión') }}</Link>
            </Button>
            <Button
                v-else
                class="h-11 rounded-full px-5"
                :disabled="!selectedVariant.in_stock || form.processing"
                @click="addToCart"
            >
                {{ ctaLabel }}
            </Button>
        </template>
    </StickyBuyBar>
</template>
