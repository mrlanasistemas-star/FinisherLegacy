<script setup lang="ts">
/**
 * Buscar — one box, tabbed results: Personas · Eventos · Productos.
 * Privacy (athletes) and publication (events/products) are enforced by
 * SearchController; people carry mutuals + follow state. Typing searches
 * as you go (debounced); the URL keeps ?q= so results are shareable.
 */
import { Link, router } from '@inertiajs/vue3';
import {
    CalendarDays,
    Search,
    ShoppingBag,
    UserRoundSearch,
    Users,
    X,
} from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import PersonCard from '@/components/community/PersonCard.vue';
import EventCard from '@/components/public/EventCard.vue';
import SeoHead from '@/components/public/SeoHead.vue';
import ProductCard from '@/components/shared/ProductCard.vue';
import { search as searchRoute } from '@/routes';
import { index as communityIndex } from '@/routes/community';
import type {
    CommunityAthlete,
    EventEditionCard,
    PublicProductCard,
} from '@/types';

const props = defineProps<{
    query: string;
    searched: boolean;
    athletes: CommunityAthlete[];
    events: EventEditionCard[];
    products: PublicProductCard[];
}>();

type Tab = 'personas' | 'eventos' | 'productos';

const term = ref(props.query);
const loading = ref(false);

const tabs = computed(() => [
    {
        key: 'personas' as Tab,
        label: 'Personas',
        icon: Users,
        count: props.athletes.length,
    },
    {
        key: 'eventos' as Tab,
        label: 'Eventos',
        icon: CalendarDays,
        count: props.events.length,
    },
    {
        key: 'productos' as Tab,
        label: 'Productos',
        icon: ShoppingBag,
        count: props.products.length,
    },
]);

function firstWithResults(): Tab {
    return tabs.value.find((tab) => tab.count > 0)?.key ?? 'personas';
}

const active = ref<Tab>(firstWithResults());

// New results → jump to the first tab that has something.
watch(
    () => props.query,
    () => (active.value = firstWithResults()),
);

function run(value: string) {
    router.get(searchRoute().url, value.trim() ? { q: value.trim() } : {}, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onStart: () => (loading.value = true),
        onFinish: () => (loading.value = false),
    });
}

watchDebounced(
    term,
    (value) => {
        if (value.trim() !== props.query) {
            run(value);
        }
    },
    { debounce: 350 },
);

const nothing = computed(
    () =>
        props.searched &&
        !props.athletes.length &&
        !props.events.length &&
        !props.products.length,
);
</script>

<template>
    <SeoHead
        title="Buscar"
        description="Busca atletas, eventos y productos en Finisher Legacy."
        noindex
    />

    <section class="fl-container max-w-4xl pt-6 pb-16 sm:pt-10">
        <h1 class="font-serif text-3xl tracking-tight sm:text-4xl">Buscar</h1>

        <form class="relative mt-5" role="search" @submit.prevent="run(term)">
            <label for="search-input" class="sr-only"
                >Buscar atletas, eventos o productos</label
            >
            <Search
                class="pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2 text-muted-foreground"
                aria-hidden="true"
            />
            <input
                id="search-input"
                v-model="term"
                type="search"
                autofocus
                autocomplete="off"
                placeholder="Atletas, eventos, productos…"
                class="h-12 w-full rounded-full border border-input bg-card pr-12 pl-11 text-[15px] shadow-fl-micro placeholder:text-muted-foreground focus:border-foreground/30 focus:ring-2 focus:ring-ring/30 focus:outline-none [&::-webkit-search-cancel-button]:hidden"
            />
            <button
                v-if="term"
                type="button"
                class="absolute top-1/2 right-2 flex size-9 -translate-y-1/2 items-center justify-center rounded-full text-muted-foreground hover:bg-muted hover:text-foreground"
                aria-label="Limpiar búsqueda"
                @click="term = ''"
            >
                <X class="size-4" />
            </button>
        </form>

        <div
            v-if="!searched"
            class="mt-10 flex flex-col items-center text-center"
        >
            <span
                class="flex size-14 items-center justify-center rounded-full bg-fl-cream"
            >
                <UserRoundSearch class="size-6 text-fl-gold-ink" />
            </span>
            <p class="mt-4 font-semibold">
                Encuentra atletas, eventos y equipo
            </p>
            <p class="mt-1 text-sm text-muted-foreground">
                Escribe al menos 2 letras para buscar.
            </p>
            <Link
                :href="communityIndex()"
                class="mt-4 text-sm font-semibold text-fl-gold-ink hover:text-foreground"
                >Explorar la comunidad</Link
            >
        </div>

        <template v-else>
            <nav
                class="mt-6 grid grid-cols-3 border-b border-border"
                role="tablist"
                aria-label="Tipo de resultado"
            >
                <button
                    v-for="tab in tabs"
                    :id="`search-tab-${tab.key}`"
                    :key="tab.key"
                    type="button"
                    role="tab"
                    :aria-selected="active === tab.key"
                    :aria-controls="`search-panel-${tab.key}`"
                    class="-mb-px inline-flex min-h-12 items-center justify-center gap-1.5 border-b-2 text-sm font-semibold transition-colors"
                    :class="
                        active === tab.key
                            ? 'border-foreground text-foreground'
                            : 'border-transparent text-muted-foreground hover:text-foreground'
                    "
                    @click="active = tab.key"
                >
                    <component
                        :is="tab.icon"
                        class="hidden size-4 sm:block"
                        aria-hidden="true"
                    />
                    {{ tab.label }}
                    <span
                        class="legacy-numeric text-xs font-medium opacity-60"
                        >{{ tab.count }}</span
                    >
                </button>
            </nav>

            <div
                class="transition-opacity"
                :class="loading ? 'opacity-60' : ''"
            >
                <p
                    v-if="nothing"
                    class="mt-8 rounded-card border border-dashed border-foreground/15 bg-card p-10 text-center text-muted-foreground"
                >
                    No encontramos resultados para “{{ query }}”.
                </p>

                <template v-else>
                    <section
                        v-show="active === 'personas'"
                        id="search-panel-personas"
                        role="tabpanel"
                        aria-labelledby="search-tab-personas"
                    >
                        <div
                            v-if="athletes.length"
                            class="mt-2 divide-y divide-border"
                        >
                            <PersonCard
                                v-for="athlete in athletes"
                                :key="athlete.username ?? athlete.name"
                                :person="athlete"
                            />
                        </div>
                        <p
                            v-else
                            class="py-12 text-center text-sm text-muted-foreground"
                        >
                            Ningún atleta con perfil público coincide con “{{
                                query
                            }}”.
                        </p>
                    </section>

                    <section
                        v-show="active === 'eventos'"
                        id="search-panel-eventos"
                        role="tabpanel"
                        aria-labelledby="search-tab-eventos"
                    >
                        <div
                            v-if="events.length"
                            class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3"
                        >
                            <EventCard
                                v-for="edition in events"
                                :key="edition.id"
                                :edition="edition"
                                compact
                            />
                        </div>
                        <p
                            v-else
                            class="py-12 text-center text-sm text-muted-foreground"
                        >
                            Ningún evento coincide con “{{ query }}”.
                        </p>
                    </section>

                    <section
                        v-show="active === 'productos'"
                        id="search-panel-productos"
                        role="tabpanel"
                        aria-labelledby="search-tab-productos"
                    >
                        <div
                            v-if="products.length"
                            class="mt-6 grid grid-cols-2 gap-x-4 gap-y-10 lg:grid-cols-4"
                        >
                            <ProductCard
                                v-for="product in products"
                                :key="product.uuid"
                                :name="product.name"
                                :slug="product.slug"
                                :category="product.category"
                                :from-price-minor="product.from_price_minor"
                                :currency="product.currency"
                                :in-stock="product.in_stock"
                                :image-url="product.image_url"
                                :image-alt="product.image_alt"
                                :hover-image-url="product.hover_image_url"
                                :variant-count="product.variant_count"
                                :tagline="product.tagline"
                                :availability="product.availability"
                                :availability-label="product.availability_label"
                                :compare-at-minor="product.compare_at_minor"
                                :promotion-label="product.promotion_label"
                            />
                        </div>
                        <p
                            v-else
                            class="py-12 text-center text-sm text-muted-foreground"
                        >
                            Ningún producto coincide con “{{ query }}”.
                        </p>
                    </section>
                </template>
            </div>
        </template>
    </section>
</template>
