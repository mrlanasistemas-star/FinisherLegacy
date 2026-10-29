<script setup lang="ts">
/**
 * Catálogo → Colecciones: the tag designs grouped by destination,
 * discipline, event, year or season. Visual cards (a collection is an
 * image-led thing), filters in the URL, server pagination.
 */
import { Head, Link } from '@inertiajs/vue3';
import { ArrowDownUp, Layers, Plus, Search } from '@lucide/vue';
import { computed } from 'vue';
import {
    create,
    index,
    show,
} from '@/actions/App/Http/Controllers/Admin/Catalog/CollectionController';
import CatalogHeader from '@/components/admin/catalog/CatalogHeader.vue';
import EmptyState from '@/components/admin/catalog/EmptyState.vue';
import FancySelect from '@/components/forms/FancySelect.vue';
import Pagination from '@/components/public/Pagination.vue';
import type { PaginationLink } from '@/components/public/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { useAdminFilters } from '@/composables/useAdminFilters';
import { shortDate } from '@/lib/datetime';
import { COLLECTION_STATUS } from '@/lib/tagStatus';

type Row = {
    id: number;
    name: string;
    slug: string;
    tagline: string | null;
    discipline: string | null;
    destination: string | null;
    year: number | null;
    status: string;
    launch_at: string | null;
    cover_url: string | null;
    accent_color: string | null;
    products_count: number;
};

const props = defineProps<{
    collections: { data: Row[]; links: PaginationLink[]; total: number };
    filters: {
        q: string;
        status: string | null;
        discipline: string | null;
        year: number | null;
        sort: string;
        dir: string;
    };
    disciplines: string[];
    years: number[];
}>();

const { filters, loading, apply, applyDebounced } = useAdminFilters(
    () => index().url,
    { ...props.filters },
);

const statusTabs = [
    { value: null, label: 'Todas' },
    ...Object.entries(COLLECTION_STATUS).map(([value, s]) => ({
        value,
        label: s.label,
    })),
];

const disciplineOptions = computed(() => [
    { value: null, label: 'Todas las disciplinas' },
    ...props.disciplines.map((d) => ({ value: d, label: d })),
]);
const yearOptions = computed(() => [
    { value: null, label: 'Todos los años' },
    ...props.years.map((y) => ({ value: y, label: String(y) })),
]);
const sortOptions = [
    { value: 'sort_order', label: 'Orden manual' },
    { value: 'name', label: 'Nombre' },
    { value: 'year', label: 'Año' },
    { value: 'launch_at', label: 'Lanzamiento' },
    { value: 'created_at', label: 'Creación' },
];
</script>

<template>
    <Head title="Colecciones" />

    <div class="w-full px-4 py-6 md:px-6 md:py-8 xl:px-8">
        <CatalogHeader
            eyebrow="Catálogo"
            title="Colecciones"
            description="Los diseños del Finisher Legacy Tag, agrupados por destino, disciplina, evento o temporada."
            :icon="Layers"
        >
            <template #actions>
                <Button as-child class="rounded-full">
                    <Link :href="create().url">
                        <Plus class="size-4" /> Nueva colección
                    </Link>
                </Button>
            </template>
        </CatalogHeader>

        <div class="mb-5 space-y-3">
            <div
                class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 md:mx-0 md:px-0"
            >
                <button
                    v-for="tab in statusTabs"
                    :key="tab.label"
                    type="button"
                    class="inline-flex min-h-9 shrink-0 items-center rounded-full border px-3.5 text-sm font-medium transition"
                    :class="
                        filters.status === tab.value
                            ? 'border-foreground bg-foreground text-background'
                            : 'border-border bg-card hover:border-foreground/40'
                    "
                    @click="apply({ status: tab.value })"
                >
                    {{ tab.label }}
                </button>
            </div>
            <div
                class="grid gap-2 sm:grid-cols-2 lg:grid-cols-[1fr_200px_160px_200px_auto]"
            >
                <div class="relative">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <input
                        v-model="filters.q"
                        type="search"
                        placeholder="Buscar por nombre, destino o slug…"
                        aria-label="Buscar colección"
                        class="h-9 w-full rounded-full border border-input bg-card pr-4 pl-10 text-sm outline-none hover:border-foreground/25 focus:border-fl-gold focus:ring-4 focus:ring-fl-gold/15"
                        @input="applyDebounced"
                    />
                </div>
                <FancySelect
                    :model-value="filters.discipline"
                    :options="disciplineOptions"
                    aria-label="Disciplina"
                    @update:model-value="
                        (v) => apply({ discipline: (v as string) ?? null })
                    "
                />
                <FancySelect
                    :model-value="filters.year"
                    :options="yearOptions"
                    aria-label="Año"
                    @update:model-value="
                        (v) => apply({ year: (v as number) ?? null })
                    "
                />
                <FancySelect
                    :model-value="filters.sort"
                    :options="sortOptions"
                    aria-label="Ordenar por"
                    @update:model-value="
                        (v) => apply({ sort: String(v ?? 'sort_order') })
                    "
                />
                <Button
                    variant="outline"
                    class="h-9 rounded-full"
                    :aria-label="
                        filters.dir === 'asc'
                            ? 'Orden ascendente'
                            : 'Orden descendente'
                    "
                    @click="
                        apply({ dir: filters.dir === 'asc' ? 'desc' : 'asc' })
                    "
                >
                    <ArrowDownUp class="size-4" />
                    {{ filters.dir === 'asc' ? 'Asc' : 'Desc' }}
                </Button>
            </div>
        </div>

        <div
            v-if="loading"
            class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4"
        >
            <div v-for="i in 8" :key="i" class="fl-card overflow-hidden">
                <Skeleton class="aspect-[16/10] w-full rounded-none" />
                <div class="space-y-2 p-4">
                    <Skeleton class="h-4 w-2/3" />
                    <Skeleton class="h-3 w-1/2" />
                </div>
            </div>
        </div>

        <div
            v-else-if="collections.data.length"
            class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4"
        >
            <Link
                v-for="c in collections.data"
                :key="c.id"
                :href="show(c.id).url"
                class="group fl-card flex flex-col overflow-hidden transition-[border-color,box-shadow,transform] duration-200 hover:-translate-y-0.5 hover:border-foreground/20 hover:shadow-[0_22px_44px_-28px_rgb(23_23_20/0.4)]"
            >
                <div
                    class="relative aspect-[16/10] overflow-hidden bg-fl-cream"
                >
                    <img
                        v-if="c.cover_url"
                        :src="c.cover_url"
                        :alt="c.name"
                        loading="lazy"
                        class="size-full object-cover transition duration-500 group-hover:scale-[1.03]"
                    />
                    <div
                        v-else
                        class="flex size-full items-center justify-center text-fl-gold-ink/60"
                    >
                        <Layers class="size-8" />
                    </div>
                    <span
                        class="absolute top-3 left-3 rounded-full border px-2 py-0.5 text-xs font-medium backdrop-blur"
                        :class="COLLECTION_STATUS[c.status]?.class"
                        >{{
                            COLLECTION_STATUS[c.status]?.label ?? c.status
                        }}</span
                    >
                    <span
                        v-if="c.accent_color"
                        class="absolute right-3 bottom-3 size-4 rounded-full ring-2 ring-white"
                        :style="{ backgroundColor: c.accent_color }"
                        aria-hidden="true"
                    />
                </div>
                <div class="flex flex-1 flex-col gap-1 p-4">
                    <p class="leading-snug font-semibold">{{ c.name }}</p>
                    <p
                        v-if="c.tagline"
                        class="line-clamp-2 text-sm text-muted-foreground"
                    >
                        {{ c.tagline }}
                    </p>
                    <p class="mt-auto pt-2 text-xs text-muted-foreground">
                        {{
                            [c.discipline, c.destination, c.year]
                                .filter(Boolean)
                                .join(' · ') || 'Sin datos de colección'
                        }}
                    </p>
                    <div
                        class="flex items-center justify-between border-t border-border pt-2 text-xs text-muted-foreground"
                    >
                        <span
                            >{{ c.products_count }}
                            {{
                                c.products_count === 1 ? 'diseño' : 'diseños'
                            }}</span
                        >
                        <span v-if="c.launch_at"
                            >Lanza {{ shortDate(c.launch_at) }}</span
                        >
                    </div>
                </div>
            </Link>
        </div>

        <EmptyState
            v-else
            :icon="Layers"
            title="No hay colecciones con estos filtros"
            text="Crea la primera colección o ajusta los filtros."
        />

        <div class="mt-6">
            <Pagination :links="collections.links" />
        </div>
    </div>
</template>
