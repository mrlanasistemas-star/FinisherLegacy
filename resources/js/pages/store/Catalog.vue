<script setup lang="ts">
/**
 * Store listing (owner spec §18-§19): the whole catalog at
 * /tienda/catalogo and each category at /tienda/categoria/{slug}.
 * Filters live in the query string (shareable, back-button friendly) and
 * are applied server-side; results are paginated.
 */
import { Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Nfc, SlidersHorizontal, X } from '@lucide/vue';
import { computed, reactive, watch } from 'vue';
import FancySelect from '@/components/forms/FancySelect.vue';
import Pagination from '@/components/public/Pagination.vue';
import type { PaginationLink } from '@/components/public/Pagination.vue';
import SeoHead from '@/components/public/SeoHead.vue';
import ProductCard from '@/components/shared/ProductCard.vue';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetClose,
    SheetContent,
    SheetDescription,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { productCardProps } from '@/lib/store';
import { catalog } from '@/routes/store';
import { show as categoryShow } from '@/routes/store/categories';
import { index as storeIndex } from '@/routes/store/products';
import type { PublicProductCard } from '@/types';

type Filters = {
    tipo: string | null;
    talla: string | null;
    nfc: boolean;
    disponibles: boolean;
    orden: string;
};

const props = defineProps<{
    category: { name: string; slug: string; description: string | null } | null;
    products: {
        data: PublicProductCard[];
        links: PaginationLink[];
        total: number;
        current_page: number;
    };
    categories: {
        name: string;
        slug: string;
        description: string | null;
        products_count: number;
    }[];
    sizes: string[];
    types: { value: string; label: string }[];
    filters: Filters;
}>();

const state = reactive<Filters>({ ...props.filters });

watch(
    () => props.filters,
    (next) => Object.assign(state, next),
);

const baseUrl = computed(() =>
    props.category ? categoryShow(props.category.slug).url : catalog().url,
);

function apply() {
    const query: Record<string, string> = {};

    if (state.tipo) {
        query.tipo = state.tipo;
    }

    if (state.talla) {
        query.talla = state.talla;
    }

    if (state.nfc) {
        query.nfc = '1';
    }

    if (state.disponibles) {
        query.disponibles = '1';
    }

    if (state.orden && state.orden !== 'destacados') {
        query.orden = state.orden;
    }

    router.get(baseUrl.value, query, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
        only: ['products', 'filters'],
    });
}

function set<K extends keyof Filters>(key: K, value: Filters[K]) {
    state[key] = value;
    apply();
}

function clearAll() {
    Object.assign(state, {
        tipo: null,
        talla: null,
        nfc: false,
        disponibles: false,
        orden: 'destacados',
    });
    apply();
}

const activeCount = computed(
    () =>
        Number(!!state.tipo) +
        Number(!!state.talla) +
        Number(state.nfc) +
        Number(state.disponibles),
);

const sortOptions = [
    { value: 'destacados', label: 'Destacados' },
    { value: 'novedades', label: 'Novedades' },
    { value: 'precio-asc', label: 'Precio: menor a mayor' },
    { value: 'precio-desc', label: 'Precio: mayor a menor' },
];

const typeOptions = computed(() => [
    { value: null, label: 'Todos los tipos' },
    ...props.types,
]);

const title = computed(() => props.category?.name ?? 'Todo el catálogo');
const lede = computed(
    () =>
        props.category?.description ??
        'Legacy Plate, ropa técnica y accesorios pensados para correr, terminar y recordar.',
);
</script>

<template>
    <SeoHead :title="title" :description="lede" />

    <section class="fl-container pt-8 pb-6 sm:pt-12">
        <Link
            :href="storeIndex()"
            class="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="size-4" />
            Tienda
        </Link>
        <div class="mt-6 grid items-end gap-4 lg:grid-cols-12">
            <h1 class="fl-display text-[2.6rem] sm:text-6xl lg:col-span-7">
                {{ title }}
            </h1>
            <p
                class="text-base leading-relaxed text-muted-foreground sm:text-lg lg:col-span-5 lg:pb-2"
            >
                {{ lede }}
            </p>
        </div>

        <!-- Categories -->
        <nav
            class="-mx-4 mt-8 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:px-0"
            aria-label="Categorías"
        >
            <Link
                :href="catalog()"
                class="fl-chip"
                :class="!category ? 'fl-chip--on' : ''"
                :aria-current="!category ? 'page' : undefined"
                >Todo</Link
            >
            <Link
                v-for="item in categories.filter((c) => c.products_count > 0)"
                :key="item.slug"
                :href="categoryShow(item.slug)"
                class="fl-chip"
                :class="category?.slug === item.slug ? 'fl-chip--on' : ''"
                :aria-current="
                    category?.slug === item.slug ? 'page' : undefined
                "
            >
                {{ item.name }}
                <span class="text-xs opacity-60">{{
                    item.products_count
                }}</span>
            </Link>
        </nav>
    </section>

    <!-- Filter bar -->
    <div
        class="sticky top-16 z-30 border-y border-border bg-background/90 backdrop-blur-md"
    >
        <div class="fl-container flex items-center justify-between gap-3 py-3">
            <p class="text-sm text-muted-foreground" aria-live="polite">
                <span class="legacy-numeric font-semibold text-foreground">{{
                    products.total
                }}</span>
                {{ products.total === 1 ? 'producto' : 'productos' }}
            </p>

            <div class="hidden items-center gap-2 lg:flex">
                <div
                    v-if="sizes.length"
                    class="flex items-center gap-1"
                    role="group"
                    aria-label="Talla"
                >
                    <button
                        v-for="size in sizes"
                        :key="size"
                        type="button"
                        class="fl-chip fl-chip--sm"
                        :class="state.talla === size ? 'fl-chip--on' : ''"
                        :aria-pressed="state.talla === size"
                        @click="
                            set('talla', state.talla === size ? null : size)
                        "
                    >
                        {{ size }}
                    </button>
                </div>
                <span class="mx-1 h-6 w-px bg-border" aria-hidden="true" />
                <button
                    type="button"
                    class="fl-chip fl-chip--sm"
                    :class="state.nfc ? 'fl-chip--on' : ''"
                    :aria-pressed="state.nfc"
                    @click="set('nfc', !state.nfc)"
                >
                    <Nfc class="size-3.5" aria-hidden="true" /> Con NFC
                </button>
                <button
                    type="button"
                    class="fl-chip fl-chip--sm"
                    :class="state.disponibles ? 'fl-chip--on' : ''"
                    :aria-pressed="state.disponibles"
                    @click="set('disponibles', !state.disponibles)"
                >
                    Disponibles
                </button>
                <FancySelect
                    v-if="!category"
                    :model-value="state.tipo"
                    :options="typeOptions"
                    placeholder="Todos los tipos"
                    size="sm"
                    class="w-40"
                    aria-label="Tipo de producto"
                    @update:model-value="
                        set('tipo', ($event as string) ?? null)
                    "
                />
                <FancySelect
                    :model-value="state.orden"
                    :options="sortOptions"
                    size="sm"
                    class="w-52"
                    aria-label="Ordenar"
                    @update:model-value="
                        set('orden', ($event as string) ?? 'destacados')
                    "
                />
                <button
                    v-if="activeCount"
                    type="button"
                    class="inline-flex h-9 items-center gap-1 rounded-full px-3 text-sm text-muted-foreground hover:text-foreground"
                    @click="clearAll"
                >
                    <X class="size-3.5" /> Limpiar
                </button>
            </div>

            <!-- Mobile: filters in a sheet -->
            <Sheet>
                <SheetTrigger as-child>
                    <Button
                        variant="outline"
                        class="h-10 rounded-full lg:hidden"
                    >
                        <SlidersHorizontal class="size-4" />
                        Filtrar
                        <span
                            v-if="activeCount"
                            class="legacy-numeric rounded-full bg-foreground px-1.5 text-[11px] text-background"
                            >{{ activeCount }}</span
                        >
                    </Button>
                </SheetTrigger>
                <SheetContent
                    side="bottom"
                    class="max-h-[85vh] gap-0 overflow-y-auto rounded-t-3xl bg-background p-0"
                >
                    <div class="mx-auto mt-3 h-1 w-10 rounded-full bg-border" />
                    <SheetTitle class="px-5 pt-4 font-serif text-2xl"
                        >Filtrar</SheetTitle
                    >
                    <SheetDescription class="sr-only"
                        >Ordena y filtra los productos del
                        catálogo.</SheetDescription
                    >
                    <div class="space-y-6 px-5 py-5">
                        <div>
                            <p class="mb-2 text-sm font-medium">Ordenar</p>
                            <FancySelect
                                :model-value="state.orden"
                                :options="sortOptions"
                                aria-label="Ordenar"
                                @update:model-value="
                                    set(
                                        'orden',
                                        ($event as string) ?? 'destacados',
                                    )
                                "
                            />
                        </div>
                        <div v-if="!category">
                            <p class="mb-2 text-sm font-medium">Tipo</p>
                            <FancySelect
                                :model-value="state.tipo"
                                :options="typeOptions"
                                placeholder="Todos los tipos"
                                aria-label="Tipo de producto"
                                @update:model-value="
                                    set('tipo', ($event as string) ?? null)
                                "
                            />
                        </div>
                        <div v-if="sizes.length">
                            <p class="mb-2 text-sm font-medium">Talla</p>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="size in sizes"
                                    :key="size"
                                    type="button"
                                    class="fl-chip"
                                    :class="
                                        state.talla === size
                                            ? 'fl-chip--on'
                                            : ''
                                    "
                                    :aria-pressed="state.talla === size"
                                    @click="
                                        set(
                                            'talla',
                                            state.talla === size ? null : size,
                                        )
                                    "
                                >
                                    {{ size }}
                                </button>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="fl-chip"
                                :class="state.nfc ? 'fl-chip--on' : ''"
                                :aria-pressed="state.nfc"
                                @click="set('nfc', !state.nfc)"
                            >
                                <Nfc class="size-3.5" aria-hidden="true" /> Con
                                NFC
                            </button>
                            <button
                                type="button"
                                class="fl-chip"
                                :class="state.disponibles ? 'fl-chip--on' : ''"
                                :aria-pressed="state.disponibles"
                                @click="set('disponibles', !state.disponibles)"
                            >
                                Solo disponibles
                            </button>
                        </div>
                    </div>
                    <div
                        class="sticky bottom-0 flex gap-3 border-t border-border bg-background px-5 py-4"
                    >
                        <Button
                            variant="outline"
                            class="h-11 flex-1 rounded-full"
                            :disabled="!activeCount"
                            @click="clearAll"
                            >Limpiar</Button
                        >
                        <SheetClose as-child>
                            <Button class="h-11 flex-1 rounded-full"
                                >Ver {{ products.total }}</Button
                            >
                        </SheetClose>
                    </div>
                </SheetContent>
            </Sheet>
        </div>
    </div>

    <section class="fl-container py-10 sm:py-14">
        <div
            v-if="products.data.length"
            class="grid grid-cols-2 gap-x-4 gap-y-12 sm:gap-x-6 lg:grid-cols-3 xl:grid-cols-4"
        >
            <ProductCard
                v-for="(product, index) in products.data"
                :key="product.uuid"
                v-bind="productCardProps(product)"
                :priority="index < 4"
            />
        </div>
        <div
            v-else
            class="mx-auto max-w-lg rounded-3xl border border-dashed border-foreground/15 bg-card px-6 py-14 text-center"
        >
            <p class="font-serif text-2xl text-foreground">
                Nada con esos filtros… todavía.
            </p>
            <p class="mt-2 text-sm text-muted-foreground">
                Prueba quitando alguno o mira todo el catálogo.
            </p>
            <Button
                class="mt-6 rounded-full"
                variant="outline"
                @click="clearAll"
                >Quitar filtros</Button
            >
        </div>

        <div class="mt-14">
            <Pagination :links="products.links" />
        </div>
    </section>
</template>

<style scoped>
.fl-chip {
    display: inline-flex;
    flex-shrink: 0;
    align-items: center;
    gap: 0.4rem;
    min-height: 2.75rem;
    border-radius: 9999px;
    border: 1px solid var(--border);
    background: var(--card);
    padding: 0 1rem;
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--foreground);
    white-space: nowrap;
    transition:
        background-color 160ms ease,
        border-color 160ms ease,
        color 160ms ease;
}
.fl-chip--sm {
    min-height: 2.25rem;
    padding: 0 0.8rem;
    font-size: 0.8125rem;
}
.fl-chip:hover {
    border-color: rgb(23 23 20 / 0.35);
}
.fl-chip:focus-visible {
    outline: 2px solid var(--ring);
    outline-offset: 2px;
}
.fl-chip--on {
    border-color: var(--foreground);
    background: var(--foreground);
    color: var(--background);
}
</style>
