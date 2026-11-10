<script setup lang="ts">
/**
 * "Tienda" — one horizontal showcase of everything you can buy: the tag
 * collections (real tag artwork, flips to its back on hover) followed by
 * the rest of the store (Racepack, Trisuit, Chill Band… with their studio
 * renders, second angle on hover). Names, taglines and prices are exactly
 * what the server sent; nothing here is invented:
 *
 *   collections → collection cards        (PublicCollections)
 *   no collections → the tag products lead the rail instead
 *   gearProducts → product cards           (only priced products)
 *   nothing at all → an honest "están por llegar"
 *
 * The rail is a native scroll-snap list (touch, trackpad, keyboard) with
 * previous/next buttons that appear only when it overflows.
 */
import { Link } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight, ArrowUpRight } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, useTemplateRef } from 'vue';
import I18nT from '@/components/i18n/I18nT.vue';
import Money from '@/components/shared/Money.vue';
import TagFlipCard from '@/components/tag/TagFlipCard.vue';
import { vReveal } from '@/composables/useReveal';
import { howItWorks } from '@/routes';
import {
    index as collectionsIndex,
    show as collectionShow,
} from '@/routes/collections';
import {
    index as storeIndex,
    show as productShow,
} from '@/routes/store/products';
import type { PublicProductCard } from '@/types';
import type { CollectionCardData } from '@/types/collections';
import { collectionSubtitle, collectionTagArts } from '@/types/collections';
import HomeSectionHead from './HomeSectionHead.vue';

const props = defineProps<{
    collections: CollectionCardData[];
    tagProducts: PublicProductCard[];
    gearProducts: PublicProductCard[];
}>();

const hasAnything = computed(
    () =>
        props.collections.length > 0 ||
        props.tagProducts.length > 0 ||
        props.gearProducts.length > 0,
);

/** Without collections the tags themselves lead the rail. */
const railProducts = computed(() =>
    props.collections.length
        ? props.gearProducts
        : [...props.tagProducts, ...props.gearProducts],
);

const stage = (accent: string | null) => ({
    background: `radial-gradient(70% 60% at 50% 55%, color-mix(in srgb, ${accent ?? 'var(--fl-gold)'} 30%, transparent), transparent 78%), #fff`,
});

const eyebrow = (c: CollectionCardData) =>
    [collectionSubtitle(c), c.year].filter(Boolean).join(' · ');

// ---- Rail controls -------------------------------------------------------
const rail = useTemplateRef<HTMLElement>('rail');
const canPrev = ref(false);
const canNext = ref(false);

function measure() {
    const el = rail.value;

    if (!el) {
        return;
    }

    canPrev.value = el.scrollLeft > 8;
    canNext.value = el.scrollLeft + el.clientWidth < el.scrollWidth - 8;
}

function move(direction: 1 | -1) {
    const el = rail.value;

    if (!el) {
        return;
    }

    const card = el.querySelector<HTMLElement>('[data-card]');
    const step = card ? card.offsetWidth + 20 : el.clientWidth * 0.8;
    el.scrollBy({ left: direction * step * 2, behavior: 'smooth' });
}

onMounted(() => {
    measure();
    window.addEventListener('resize', measure, { passive: true });
});
onBeforeUnmount(() => window.removeEventListener('resize', measure));
</script>

<template>
    <section
        id="tienda"
        class="scroll-mt-20 overflow-hidden bg-fl-paper py-20 sm:py-24 lg:py-32"
        aria-labelledby="products-title"
    >
        <div class="fl-container">
            <div
                class="flex flex-col gap-8 lg:flex-row lg:items-end lg:justify-between"
            >
                <HomeSectionHead
                    id="products-title"
                    :eyebrow="$t('Tienda')"
                    :title="$t('Colecciones y equipo para tu día de carrera.')"
                    :lead="
                        $t(
                            'Cada destino tiene su propio tag de colección. Y para llegar a la meta, equipo pensado para atletas.',
                        )
                    "
                />
                <div
                    v-if="hasAnything"
                    v-reveal
                    class="flex shrink-0 items-center gap-3"
                >
                    <Link :href="storeIndex()" class="fl-btn-secondary">
                        {{ $t('Ver toda la tienda') }}
                    </Link>
                    <div
                        v-if="canPrev || canNext"
                        class="hidden items-center gap-2 sm:flex"
                    >
                        <button
                            type="button"
                            class="rail-btn"
                            :disabled="!canPrev"
                            :aria-label="$t('Productos anteriores')"
                            @click="move(-1)"
                        >
                            <ArrowLeft class="size-4" aria-hidden="true" />
                        </button>
                        <button
                            type="button"
                            class="rail-btn"
                            :disabled="!canNext"
                            :aria-label="$t('Más productos')"
                            @click="move(1)"
                        >
                            <ArrowRight class="size-4" aria-hidden="true" />
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- The rail (bleeds to the screen edge, aligned to the container) -->
        <ul
            v-if="hasAnything"
            ref="rail"
            v-reveal="{ y: 24 }"
            class="rail mt-10 flex snap-x snap-mandatory gap-5 overflow-x-auto pt-2 pb-8 lg:mt-14"
            tabindex="0"
            :aria-label="$t('Colecciones y productos de la tienda')"
            @scroll.passive="measure"
        >
            <li
                v-for="collection in collections"
                :key="`c-${collection.slug}`"
                data-card
                class="rail-card"
            >
                <Link
                    :href="collectionShow(collection.slug)"
                    class="fl-card-hover group flex h-full flex-col overflow-hidden rounded-[1.75rem] border border-fl-line bg-white focus-visible:ring-2 focus-visible:ring-fl-gold-dim focus-visible:ring-offset-4 focus-visible:outline-none"
                >
                    <span
                        class="relative block aspect-[4/5] overflow-hidden"
                        :style="stage(collection.accent_color)"
                    >
                        <span
                            class="absolute top-4 left-4 z-10 inline-flex h-7 items-center rounded-full bg-fl-black px-3 text-[10px] font-bold tracking-[0.16em] text-fl-gold uppercase"
                            >{{ $t('Tag de colección') }}</span
                        >
                        <span
                            class="absolute inset-x-0 top-[13%] mx-auto block w-[44%] transition-transform duration-700 ease-legacy group-hover:-translate-y-1.5 group-hover:-rotate-3 motion-reduce:transition-none"
                        >
                            <TagFlipCard
                                :art="collectionTagArts(collection)[0]"
                                :button="false"
                            />
                        </span>
                    </span>
                    <span class="flex flex-1 flex-col p-5">
                        <span
                            v-if="eyebrow(collection)"
                            class="block truncate text-[10px] font-bold tracking-[0.18em] text-fl-gold-ink uppercase"
                            >{{ eyebrow(collection) }}</span
                        >
                        <span
                            class="mt-1 block font-serif text-[1.35rem] leading-tight font-semibold tracking-[-0.02em] text-foreground"
                            >{{ collection.name }}</span
                        >
                        <span
                            v-if="collection.tagline"
                            class="mt-1 line-clamp-2 block text-sm text-muted-foreground"
                            >{{ collection.tagline }}</span
                        >
                        <span
                            class="mt-auto flex items-center justify-between pt-4 text-sm text-muted-foreground"
                        >
                            <span v-if="collection.from_price_minor !== null">
                                <I18nT :text="$t('Desde {price}')">
                                    <template #price>
                                        <Money
                                            :minor="collection.from_price_minor"
                                            :currency="collection.currency"
                                            class="font-semibold text-foreground"
                                        />
                                    </template>
                                </I18nT>
                            </span>
                            <span v-else>{{ $t('Muy pronto') }}</span>
                            <span
                                class="grid size-9 place-items-center rounded-full border border-foreground/10 text-foreground transition-all duration-300 group-hover:rotate-45 group-hover:border-fl-black group-hover:bg-fl-black group-hover:text-white motion-reduce:transition-none"
                                aria-hidden="true"
                            >
                                <ArrowUpRight class="size-4" />
                            </span>
                        </span>
                    </span>
                </Link>
            </li>

            <li
                v-for="product in railProducts"
                :key="`p-${product.uuid}`"
                data-card
                class="rail-card"
            >
                <Link
                    :href="productShow(product.slug)"
                    class="fl-card-hover group flex h-full flex-col overflow-hidden rounded-[1.75rem] border border-fl-line bg-white focus-visible:ring-2 focus-visible:ring-fl-gold-dim focus-visible:ring-offset-4 focus-visible:outline-none"
                >
                    <span
                        class="relative block aspect-[4/5] overflow-hidden bg-[#f4f1ea]"
                    >
                        <span
                            v-if="product.category"
                            class="absolute top-4 left-4 z-10 inline-flex h-7 items-center rounded-full bg-white/90 px-3 text-[10px] font-bold tracking-[0.16em] text-fl-black uppercase backdrop-blur"
                            >{{ product.category }}</span
                        >
                        <img
                            v-if="product.image_url"
                            :src="product.image_url"
                            :alt="product.image_alt ?? product.name"
                            width="800"
                            height="1000"
                            loading="lazy"
                            decoding="async"
                            class="absolute inset-0 size-full object-cover transition-[transform,opacity] duration-700 ease-legacy group-hover:scale-[1.05] motion-reduce:transition-none"
                            :class="{
                                'group-hover:opacity-0':
                                    product.hover_image_url,
                            }"
                        />
                        <img
                            v-else
                            src="/images/brand/logo/fl-monograma.png"
                            alt=""
                            width="160"
                            height="72"
                            loading="lazy"
                            decoding="async"
                            class="absolute top-1/2 left-1/2 w-24 -translate-x-1/2 -translate-y-1/2 opacity-70"
                        />
                        <img
                            v-if="product.hover_image_url"
                            :src="product.hover_image_url"
                            alt=""
                            loading="lazy"
                            decoding="async"
                            class="absolute inset-0 size-full scale-[1.03] object-cover opacity-0 transition-[transform,opacity] duration-700 ease-legacy group-hover:scale-100 group-hover:opacity-100 motion-reduce:transition-none"
                        />
                    </span>
                    <span class="flex flex-1 flex-col p-5">
                        <span
                            class="block font-serif text-[1.35rem] leading-tight font-semibold tracking-[-0.02em] text-foreground"
                            >{{ product.name }}</span
                        >
                        <span
                            v-if="product.tagline"
                            class="mt-1 line-clamp-2 block text-sm text-muted-foreground"
                            >{{ product.tagline }}</span
                        >
                        <span
                            class="mt-auto flex items-center justify-between pt-4 text-sm text-muted-foreground"
                        >
                            <span v-if="product.from_price_minor !== null">
                                <I18nT
                                    v-if="product.variant_count > 1"
                                    :text="$t('Desde {price}')"
                                >
                                    <template #price>
                                        <Money
                                            :minor="product.from_price_minor"
                                            :currency="product.currency"
                                            class="font-semibold text-foreground"
                                        />
                                    </template>
                                </I18nT>
                                <Money
                                    v-else
                                    :minor="product.from_price_minor"
                                    :currency="product.currency"
                                    class="font-semibold text-foreground"
                                />
                            </span>
                            <span v-else>{{
                                product.availability_label ?? $t('Muy pronto')
                            }}</span>
                            <span
                                class="grid size-9 place-items-center rounded-full border border-foreground/10 text-foreground transition-all duration-300 group-hover:rotate-45 group-hover:border-fl-black group-hover:bg-fl-black group-hover:text-white motion-reduce:transition-none"
                                aria-hidden="true"
                            >
                                <ArrowUpRight class="size-4" />
                            </span>
                        </span>
                    </span>
                </Link>
            </li>

            <!-- Last card: the whole catalog -->
            <li data-card class="rail-card">
                <Link
                    :href="collectionsIndex()"
                    class="group flex h-full min-h-[22rem] flex-col justify-between overflow-hidden rounded-[1.75rem] bg-fl-black p-7 text-white focus-visible:ring-2 focus-visible:ring-fl-gold-dim focus-visible:ring-offset-4 focus-visible:outline-none"
                >
                    <img
                        src="/images/brand/logo/fl-monograma.png"
                        alt=""
                        width="96"
                        height="43"
                        loading="lazy"
                        decoding="async"
                        class="w-16"
                    />
                    <span>
                        <span
                            class="block font-serif text-2xl leading-tight font-semibold tracking-[-0.02em]"
                            >{{ $t('¿Tu carrera no está aquí todavía?') }}</span
                        >
                        <span class="mt-2 block text-sm text-white/65">{{
                            $t(
                                'Mira todas las colecciones y las que vienen en camino.',
                            )
                        }}</span>
                        <span
                            class="mt-6 inline-flex items-center gap-2 text-sm font-semibold text-fl-gold"
                        >
                            {{ $t('Ver colecciones') }}
                            <ArrowRight
                                class="size-4 transition-transform group-hover:translate-x-1 motion-reduce:transition-none"
                                aria-hidden="true"
                            />
                        </span>
                    </span>
                </Link>
            </li>
        </ul>

        <div v-else class="fl-container">
            <!-- Honest empty state -->
            <div
                class="mt-12 rounded-hero border border-fl-line bg-white p-8 sm:p-10"
            >
                <p
                    class="font-serif text-3xl leading-tight font-semibold text-foreground sm:text-4xl"
                >
                    {{ $t('Las primeras colecciones están por llegar.') }}
                </p>
                <p class="mt-4 max-w-lg text-muted-foreground">
                    {{
                        $t(
                            'Estamos terminando los diseños. Mientras tanto, conoce cómo funciona tu tag de principio a fin.',
                        )
                    }}
                </p>
                <Link
                    :href="howItWorks()"
                    class="group mt-7 inline-flex min-h-11 items-center gap-2 text-sm font-semibold text-foreground"
                >
                    <span class="fl-link fl-link--gold">{{
                        $t('Cómo funciona')
                    }}</span>
                    <ArrowRight class="size-4" aria-hidden="true" />
                </Link>
            </div>
        </div>
    </section>
</template>

<style scoped>
.rail {
    /* Align the first card with the page container, bleed to the edges. */
    --rail-gutter: max(1rem, calc((100vw - 80rem) / 2 + 2rem));
    padding-inline: var(--rail-gutter);
    scroll-padding-inline: var(--rail-gutter);
    scrollbar-width: none;
    -webkit-overflow-scrolling: touch;
}
.rail::-webkit-scrollbar {
    display: none;
}
@media (min-width: 640px) and (max-width: 1343px) {
    .rail {
        --rail-gutter: 1.5rem;
    }
}
@media (min-width: 1024px) and (max-width: 1343px) {
    .rail {
        --rail-gutter: 2rem;
    }
}
.rail-card {
    flex: 0 0 auto;
    width: min(76vw, 19rem);
    scroll-snap-align: start;
}
@media (min-width: 1024px) {
    .rail-card {
        width: 18.5rem;
    }
}
.rail-btn {
    display: grid;
    width: 3rem;
    height: 3rem;
    place-items: center;
    border-radius: 9999px;
    border: 1px solid rgb(11 11 12 / 0.14);
    background: #fff;
    color: var(--fl-black);
    transition:
        background-color 200ms ease,
        color 200ms ease,
        opacity 200ms ease;
}
.rail-btn:hover:not(:disabled) {
    background: var(--fl-black);
    color: #fff;
}
.rail-btn:disabled {
    opacity: 0.35;
    cursor: not-allowed;
}
</style>
