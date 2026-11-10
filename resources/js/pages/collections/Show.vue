<script setup lang="ts">
/**
 * /colecciones/{slug} — one published collection (CollectionController@show):
 * cinematic hero (admin hero image, or the illustrative tag wearing the
 * collection), its story, its active products with the real price
 * (ResolveProductPrice) and checkout availability, how the tag works,
 * and other collections. Product CTA → /tienda/{slug}.
 */
import { Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    ChevronRight,
    Images,
    Lock,
    Nfc,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import CollectionCard from '@/components/collections/CollectionCard.vue';
import TagProductTile from '@/components/collections/TagProductTile.vue';
import CtaBand from '@/components/marketing/CtaBand.vue';
import FeatureGrid from '@/components/marketing/FeatureGrid.vue';
import SectionIntro from '@/components/marketing/SectionIntro.vue';
import Reveal from '@/components/motion/Reveal.vue';
import SeoHead from '@/components/public/SeoHead.vue';
import TagFlipCard from '@/components/tag/TagFlipCard.vue';
import { vReveal } from '@/composables/useReveal';
import { ACTIVATE_URL, STORE_URL } from '@/config/public-links';
import { useI18n } from '@/i18n';
import { howItWorks } from '@/routes';
import { index as collectionsIndex } from '@/routes/collections';
import { show as productShow } from '@/routes/store/products';
import type { CollectionCardData, CollectionDetail } from '@/types/collections';
import {
    collectionBaseName,
    collectionSubtitle,
    collectionTagArts,
} from '@/types/collections';

const props = defineProps<{
    collection: CollectionDetail;
    otherCollections: CollectionCardData[];
}>();

const { t } = useI18n();

const subtitle = computed(() => collectionSubtitle(props.collection));
const baseName = computed(() => collectionBaseName(props.collection));
/** Every tag design of the collection (deck pp. 7–8), primary first. */
const designs = computed(() => collectionTagArts(props.collection));

const seoTitle = computed(
    () =>
        props.collection.seo_title ??
        t('Colección {name}', {
            name: `${baseName.value}${props.collection.year ? ` ${props.collection.year}` : ''}`,
        }),
);
const seoDescription = computed(
    () =>
        props.collection.seo_description ??
        props.collection.tagline ??
        props.collection.description?.slice(0, 180) ??
        t('Finisher Legacy Tag de la colección {name}: el recuerdo físico de tu logro, con tu historia dentro.', { name: props.collection.name }),
);

const origin = typeof window === 'undefined' ? '' : window.location.origin;

const jsonLd = computed(() => ({
    '@context': 'https://schema.org',
    '@type': 'CollectionPage',
    name: seoTitle.value,
    description: seoDescription.value,
    hasPart: props.collection.products.map((p) => ({
        '@type': 'Product',
        name: p.name,
        url: origin ? `${origin}${productShow.url(p.slug)}` : undefined,
        image: p.image_url ?? undefined,
        brand: { '@type': 'Brand', name: 'Finisher Legacy' },
        offers:
            p.price_minor !== null
                ? {
                      '@type': 'Offer',
                      price: (p.price_minor / 100).toFixed(2),
                      priceCurrency: p.currency,
                      availability: p.available
                          ? 'https://schema.org/InStock'
                          : 'https://schema.org/OutOfStock',
                  }
                : undefined,
    })),
}));

const tagFeatures = computed(() => [
    {
        icon: Nfc,
        title: t('Se abre con un toque'),
        text: t('Acercas tu teléfono al tag y tu recuerdo aparece. Si tu teléfono no lee NFC, escribes su código.'),
    },
    {
        icon: Images,
        title: t('Guarda tu recuerdo'),
        text: t('Fotos, tu historia, tu resultado y un mensaje de voz de ese día.'),
    },
    {
        icon: Lock,
        title: t('Tú decides quién lo ve'),
        text: t('Público, solo con enlace o privado. Los enlaces que compartes se pueden revocar.'),
    },
    {
        icon: Users,
        title: t('Tu familia no necesita app'),
        text: t('Quien toca el tag o recibe tu enlace lo abre en el navegador de su teléfono.'),
    },
]);

const steps = computed(() => [
    {
        n: '01',
        title: t('Lo compras'),
        body: t('Eliges tu diseño y lo recibes listo, con su correa.'),
    },
    {
        n: '02',
        title: t('Lo activas'),
        body: t('Acercas el teléfono y se abre. Sin NFC, escribes su código en Activar.'),
    },
    {
        n: '03',
        title: t('Guardas tu historia'),
        body: t('Fotos, resultado, lugar, dedicatoria y mensaje de voz.'),
    },
]);
</script>

<template>
    <SeoHead
        :title="seoTitle"
        :description="seoDescription"
        :image="collection.hero_url"
        :json-ld="jsonLd"
    />

    <!-- Hero -->
    <section
        class="relative overflow-hidden bg-background text-foreground"
        aria-labelledby="collection-title"
    >
        <template v-if="collection.hero_url">
            <img
                :src="collection.hero_url"
                alt=""
                fetchpriority="high"
                decoding="async"
                class="absolute inset-0 size-full object-cover"
            />
            <div
                class="absolute inset-0 bg-gradient-to-r from-background via-background/85 to-background/25"
                aria-hidden="true"
            />
            <div
                class="absolute inset-x-0 bottom-0 h-32 bg-gradient-to-t from-background to-transparent"
                aria-hidden="true"
            />
        </template>
        <div
            v-else
            class="pointer-events-none absolute inset-0"
            :style="{
                background: `radial-gradient(45% 60% at 78% 45%, color-mix(in srgb, ${collection.accent_color ?? 'var(--fl-gold)'} 18%, transparent), transparent 70%)`,
            }"
            aria-hidden="true"
        />

        <div
            class="fl-container relative grid items-center gap-12 pt-10 pb-16 sm:pt-14 lg:min-h-[34rem] lg:grid-cols-12 lg:pb-20"
        >
            <div class="lg:col-span-7">
                <nav
                    :aria-label="$t('Ruta de navegación')"
                    class="flex items-center gap-1.5 text-sm text-muted-foreground"
                >
                    <Link
                        :href="collectionsIndex()"
                        class="hover:text-foreground"
                        >{{ $t('Colecciones') }}</Link
                    >
                    <ChevronRight class="size-3.5" aria-hidden="true" />
                    <span
                        class="truncate text-foreground"
                        aria-current="page"
                        >{{ collection.name }}</span
                    >
                </nav>
                <p
                    v-if="subtitle"
                    class="mt-10 flex items-center gap-3 text-[11px] font-semibold tracking-[0.24em] text-fl-gold-ink uppercase"
                >
                    <span class="h-px w-10 bg-fl-gold" aria-hidden="true" />
                    {{ subtitle }}
                </p>
                <h1
                    id="collection-title"
                    class="mt-5 font-serif text-[clamp(2.75rem,7vw,5.5rem)] leading-[0.98] font-semibold tracking-[-0.03em]"
                >
                    {{ baseName }}
                    <span
                        v-if="collection.year"
                        class="legacy-numeric block text-fl-gold-ink italic"
                        >{{ collection.year }}</span
                    >
                </h1>
                <p
                    class="mt-6 max-w-[32rem] text-lg leading-relaxed text-muted-foreground"
                >
                    {{
                        collection.tagline ||
                        $t('El Finisher Legacy Tag de {name}: lo tocas con tu teléfono y se abre el recuerdo de tu carrera.', { name: baseName })
                    }}
                </p>
                <p
                    v-if="collection.edition || collection.season"
                    class="mt-3 text-sm text-muted-foreground"
                >
                    {{
                        [collection.edition, collection.season]
                            .filter(Boolean)
                            .join(' · ')
                    }}
                </p>
                <div class="mt-9 flex flex-wrap items-center gap-3">
                    <a
                        v-if="collection.products.length"
                        href="#piezas"
                        class="fl-btn-primary"
                    >
                        {{
                            collection.products.length === 1
                                ? $t('Ver el tag')
                                : $t('Ver los tags')
                        }}
                        <ArrowRight class="size-4" aria-hidden="true" />
                    </a>
                    <Link :href="howItWorks()" class="fl-btn-secondary">
                        {{ $t('Cómo funciona') }}
                    </Link>
                </div>
            </div>
            <div
                class="mx-auto w-[56%] max-w-[18rem] sm:w-[40%] lg:col-span-4 lg:col-start-9 lg:w-full"
            >
                <TagFlipCard :art="designs[0]" />
                <p
                    class="mt-4 text-center text-[11px] tracking-[0.18em] text-muted-foreground uppercase"
                >
                    {{ $t('Render ilustrativo') }}
                </p>
            </div>
        </div>
    </section>

    <!-- Designs: every tag front of the collection (data-driven) -->
    <section
        v-if="designs.length > 1 && !collection.products.length"
        class="border-t border-fl-line bg-fl-paper py-14 sm:py-20"
        aria-labelledby="designs-title"
    >
        <div class="fl-container">
            <p class="fl-eyebrow">{{ $t('Cada sede, su diseño') }}</p>
            <h2
                id="designs-title"
                class="fl-display mt-3 text-[clamp(1.7rem,3vw,2.5rem)]"
            >
                {{ $t('{count} diseños de {name}', { count: designs.length, name: baseName }) }}
            </h2>
            <ul
                v-reveal.stagger
                class="mt-10 grid grid-cols-2 gap-6 sm:grid-cols-3 lg:grid-cols-4"
            >
                <li
                    v-for="(design, index) in designs"
                    :key="`${design.frontArt ?? 'base'}-${index}`"
                    class="fl-card-hover rounded-media border border-transparent bg-card p-5 ring-1 ring-fl-line"
                >
                    <TagFlipCard :art="design" class="mx-auto w-[72%]" />
                    <p
                        class="mt-4 text-center text-[11px] font-semibold tracking-[0.18em] text-fl-gold-ink uppercase"
                    >
                        {{
                            collection.tag_designs?.[index]?.name ??
                            collection.name
                        }}
                    </p>
                </li>
            </ul>
            <p class="mt-6 text-xs text-muted-foreground">
                {{ $t('Render ilustrativo · arte conceptual sujeto al troquel real.') }}
            </p>
        </div>
    </section>

    <!-- Story -->
    <section
        v-if="collection.description"
        class="bg-fl-paper py-16 sm:py-24"
        :aria-label="$t('La colección')"
    >
        <div class="fl-container">
            <Reveal class="mx-auto max-w-[68ch]">
                <p class="fl-eyebrow">{{ $t('La colección') }}</p>
                <p
                    class="mt-5 font-serif text-[clamp(1.4rem,2.4vw,2rem)] leading-snug whitespace-pre-line text-foreground"
                >
                    {{ collection.description }}
                </p>
            </Reveal>
        </div>
    </section>

    <!-- Products -->
    <section
        id="piezas"
        class="scroll-mt-20 bg-fl-paper pb-16 sm:pb-24"
        aria-labelledby="pieces-title"
    >
        <div class="fl-container">
            <div class="border-t border-foreground/10 pt-12">
                <p class="fl-eyebrow inline-flex items-center gap-3">
                    <span class="h-px w-6 bg-fl-gold" aria-hidden="true" />
                    {{
                        collection.products.length > 1
                            ? $tc('{count} diseño|{count} diseños', collection.products.length)
                            : $t('Disponible en la tienda')
                    }}
                </p>
                <h2
                    id="pieces-title"
                    class="fl-display mt-4 text-[1.9rem] leading-[1.1] sm:text-[2.6rem]"
                >
                    {{
                        collection.products.length === 1
                            ? $t('El tag de la colección')
                            : $t('Los tags de la colección')
                    }}
                </h2>
            </div>

            <ul
                v-if="collection.products.length"
                class="mt-10 grid gap-x-8 gap-y-12 min-[520px]:grid-cols-2 lg:grid-cols-3"
            >
                <Reveal
                    v-for="(product, index) in collection.products"
                    :key="product.uuid"
                    as="li"
                    :delay-ms="(index % 3) * 80"
                >
                    <TagProductTile
                        :name="product.name"
                        :slug="product.slug"
                        :tagline="product.tagline"
                        :image-url="product.image_url"
                        :image-alt="product.image_alt"
                        :price-minor="product.price_minor"
                        :compare-at-minor="product.compare_at_minor"
                        :currency="product.currency"
                        :available="product.available"
                        :status-label="
                            product.is_purchasable
                                ? $t('Agotado')
                                : product.availability_label
                        "
                        :art-name="collection.name"
                        :art-subtitle="subtitle"
                        :art-year="collection.year"
                        :accent="collection.accent_color"
                        :design="product.tag_design"
                    />
                    <Link
                        :href="productShow(product.slug)"
                        class="mt-3 inline-flex min-h-11 items-center gap-2 text-sm font-semibold text-foreground"
                    >
                        <span class="fl-link fl-link--gold">{{
                            product.available ? $t('Ver y comprar') : $t('Ver detalles')
                        }}</span>
                        <ArrowRight class="size-4" aria-hidden="true" />
                    </Link>
                </Reveal>
            </ul>
            <p v-else class="mt-10 max-w-lg text-muted-foreground">
                {{ $t('Los tags de esta colección estarán disponibles muy pronto.') }}
            </p>
        </div>
    </section>

    <!-- What the tag does -->
    <section
        class="fl-section border-y border-fl-line bg-white text-foreground"
        :aria-label="$t('Qué hace tu tag')"
    >
        <div class="fl-container">
            <SectionIntro
                :eyebrow="$t('Qué hace tu tag')"
                :title="$t('Tu carrera termina.')"
                :accent="$t('Tu historia no.')"
                :text="$t('El tag es un objeto de colección y, a la vez, la llave de tu recuerdo.')"
            />
            <div class="mt-10">
                <FeatureGrid :items="tagFeatures" :columns="4" />
            </div>

            <ol
                v-reveal.stagger
                class="mt-12 grid gap-8 border-t border-border pt-10 sm:grid-cols-3"
                :aria-label="$t('Después de comprarlo')"
            >
                <li v-for="step in steps" :key="step.n" class="flex gap-4">
                    <span
                        class="legacy-numeric flex size-10 shrink-0 items-center justify-center rounded-full bg-fl-black font-serif text-sm font-semibold text-fl-gold-soft"
                        aria-hidden="true"
                        >{{ Number(step.n) }}</span
                    >
                    <div>
                        <p class="font-serif text-lg font-semibold">
                            {{ step.title }}
                        </p>
                        <p
                            class="mt-1 text-[15px] leading-relaxed text-muted-foreground"
                        >
                            {{ step.body }}
                        </p>
                    </div>
                </li>
            </ol>
        </div>
    </section>

    <!-- Other collections -->
    <section
        v-if="otherCollections.length"
        class="bg-fl-paper py-16 sm:py-24"
        aria-labelledby="other-collections-title"
    >
        <div class="fl-container">
            <div class="flex items-end justify-between gap-6">
                <h2
                    id="other-collections-title"
                    class="fl-display text-[clamp(1.9rem,3.6vw,3rem)]"
                >
                    {{ $t('Otras colecciones') }}
                </h2>
                <Link
                    :href="collectionsIndex()"
                    class="group inline-flex min-h-11 shrink-0 items-center gap-2 text-sm font-semibold"
                >
                    <span class="fl-link">{{ $t('Ver todas') }}</span>
                    <ArrowRight
                        class="size-4 transition-transform group-hover:translate-x-1"
                        aria-hidden="true"
                    />
                </Link>
            </div>
            <ul
                class="mt-10 grid grid-cols-2 gap-x-4 gap-y-12 sm:gap-x-8 lg:grid-cols-3"
            >
                <li
                    v-for="(other, index) in otherCollections"
                    :key="other.slug"
                    v-reveal="{ index }"
                >
                    <CollectionCard :collection="other" />
                </li>
            </ul>
        </div>
    </section>

    <CtaBand
        :title="$t('¿Ya tienes tu tag?')"
        :accent="$t('Actívalo en un toque.')"
        :text="$t('Acerca tu teléfono al tag o escribe su código para empezar tu recuerdo.')"
    >
        <template #actions>
            <a :href="ACTIVATE_URL" class="mk-cta-primary">
                {{ $t('Activar mi tag') }}
                <ArrowRight class="size-4" aria-hidden="true" />
            </a>
            <Link :href="STORE_URL" class="mk-cta-secondary">
                {{ $t('Ir a la tienda') }}
            </Link>
        </template>
    </CtaBand>
</template>
