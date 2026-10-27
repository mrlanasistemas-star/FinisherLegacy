<script setup lang="ts">
/**
 * One collection: its data and every design (product) in it with stock,
 * physical units and activations per SKU. Delete archives instead when the
 * collection still has products (decided server-side).
 */
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Archive,
    CalendarDays,
    ExternalLink,
    Layers,
    Package,
    Pencil,
    Trash2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import {
    destroy,
    edit,
    index,
} from '@/actions/App/Http/Controllers/Admin/Catalog/CollectionController';
import { index as tagsIndex } from '@/actions/App/Http/Controllers/Admin/Catalog/LegacyTagController';
import ActionDialog from '@/components/admin/catalog/ActionDialog.vue';
import CatalogHeader from '@/components/admin/catalog/CatalogHeader.vue';
import EmptyState from '@/components/admin/catalog/EmptyState.vue';
import { Button } from '@/components/ui/button';
import { shortDate } from '@/lib/datetime';
import { productType, statusLabel } from '@/lib/statusLabels';
import { COLLECTION_STATUS } from '@/lib/tagStatus';

type VariantRow = {
    id: number;
    sku: string;
    name: string;
    stock: number;
    units: number;
    units_in_stock: number;
    activated: number;
};

type CollectionDetail = {
    id: number;
    name: string;
    slug: string;
    tagline: string | null;
    description: string | null;
    discipline: string | null;
    destination: string | null;
    year: number | null;
    edition: string | null;
    season: string | null;
    status: string;
    launch_at: string | null;
    accent_color: string | null;
    sort_order: number;
    seo_title: string | null;
    seo_description: string | null;
    event: { id: number; name: string } | null;
    cover_url: string | null;
    hero_url: string | null;
    is_published: boolean;
};

const props = defineProps<{
    collection: CollectionDetail;
    products: {
        id: number;
        name: string;
        type: string;
        status: string;
        tracks_units: boolean;
        variants: VariantRow[];
    }[];
}>();

defineOptions({
    layout: (props: { collection: CollectionDetail }) => ({
        breadcrumbs: [
            { title: 'Administración', href: '/admin' },
            { title: 'Colecciones', href: index().url },
            { title: props.collection.name, href: '#' },
        ],
    }),
});

const totals = computed(() => {
    const all = props.products.flatMap((p) => p.variants);

    return {
        designs: props.products.length,
        stock: all.reduce((s, v) => s + v.stock, 0),
        units: all.reduce((s, v) => s + v.units, 0),
        activated: all.reduce((s, v) => s + v.activated, 0),
    };
});

const confirmOpen = ref(false);
const deleting = ref(false);
const hasProducts = computed(() => props.products.length > 0);

function remove() {
    router.delete(destroy(props.collection.id).url, {
        onStart: () => (deleting.value = true),
        onFinish: () => {
            deleting.value = false;
            confirmOpen.value = false;
        },
    });
}

const details = computed(() =>
    [
        ['Disciplina', props.collection.discipline],
        ['Destino', props.collection.destination],
        ['Año', props.collection.year],
        ['Edición', props.collection.edition],
        ['Temporada', props.collection.season],
        ['Evento', props.collection.event?.name],
        ['Orden', props.collection.sort_order],
        ['Título SEO', props.collection.seo_title],
    ].filter(([, v]) => v !== null && v !== undefined && v !== ''),
);
</script>

<template>
    <Head :title="collection.name" />

    <div class="w-full p-4 md:p-8">
        <CatalogHeader
            eyebrow="Catálogo · Colección"
            :title="collection.name"
            :description="collection.tagline ?? undefined"
            :icon="Layers"
        >
            <template #actions>
                <a
                    v-if="collection.is_published"
                    :href="`/colecciones/${collection.slug}`"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex min-h-9 items-center gap-1.5 px-2 text-sm font-medium text-muted-foreground hover:text-foreground"
                >
                    Ver pública <ExternalLink class="size-3.5" />
                </a>
                <Button as-child variant="outline" class="rounded-full">
                    <Link :href="edit(collection.id).url">
                        <Pencil class="size-4" /> Editar
                    </Link>
                </Button>
                <Button
                    v-if="collection.status !== 'archived' || !hasProducts"
                    variant="ghost"
                    class="rounded-full text-red-700 hover:bg-red-50"
                    @click="confirmOpen = true"
                >
                    <component
                        :is="hasProducts ? Archive : Trash2"
                        class="size-4"
                    />
                    {{ hasProducts ? 'Archivar' : 'Eliminar' }}
                </Button>
            </template>
        </CatalogHeader>

        <div class="mb-6 grid gap-5 lg:grid-cols-[2fr_3fr]">
            <div class="fl-card overflow-hidden">
                <div class="aspect-[16/10] bg-fl-cream">
                    <img
                        v-if="collection.hero_url || collection.cover_url"
                        :src="(collection.hero_url || collection.cover_url)!"
                        :alt="collection.name"
                        class="size-full object-cover"
                    />
                    <div
                        v-else
                        class="flex size-full items-center justify-center text-fl-gold-ink/60"
                    >
                        <Layers class="size-10" />
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2 p-4 text-sm">
                    <span
                        class="rounded-full border px-2 py-0.5 text-xs font-medium"
                        :class="COLLECTION_STATUS[collection.status]?.class"
                        >{{
                            COLLECTION_STATUS[collection.status]?.label ??
                            collection.status
                        }}</span
                    >
                    <span
                        v-if="collection.launch_at"
                        class="inline-flex items-center gap-1 text-muted-foreground"
                    >
                        <CalendarDays class="size-3.5" />
                        Lanzamiento {{ shortDate(collection.launch_at) }}
                    </span>
                    <span
                        v-if="collection.accent_color"
                        class="ml-auto inline-flex items-center gap-1.5 font-mono text-xs"
                    >
                        <span
                            class="size-4 rounded-full ring-1 ring-border"
                            :style="{
                                backgroundColor: collection.accent_color,
                            }"
                        />
                        {{ collection.accent_color }}
                    </span>
                </div>
            </div>

            <div class="space-y-4">
                <dl class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <div
                        v-for="[label, value] in [
                            ['Diseños', totals.designs],
                            ['Disponibles', totals.stock],
                            ['Piezas', totals.units],
                            ['Activadas', totals.activated],
                        ]"
                        :key="label"
                        class="fl-card p-4"
                    >
                        <dt class="text-xs text-muted-foreground">
                            {{ label }}
                        </dt>
                        <dd class="legacy-numeric text-2xl font-semibold">
                            {{ value }}
                        </dd>
                    </div>
                </dl>
                <div class="fl-card p-5">
                    <p
                        v-if="collection.description"
                        class="mb-4 text-sm whitespace-pre-line text-foreground"
                    >
                        {{ collection.description }}
                    </p>
                    <dl class="grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                        <div
                            v-for="[label, value] in details"
                            :key="String(label)"
                            class="flex gap-2"
                        >
                            <dt class="w-24 shrink-0 text-muted-foreground">
                                {{ label }}
                            </dt>
                            <dd class="min-w-0 break-words">{{ value }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>

        <h2 class="mb-3 text-sm font-semibold text-muted-foreground uppercase">
            Diseños de la colección
        </h2>

        <div v-if="products.length" class="space-y-4">
            <article
                v-for="product in products"
                :key="product.id"
                class="overflow-hidden rounded-xl border border-border bg-card"
            >
                <header
                    class="flex flex-wrap items-center gap-2 border-b border-border bg-muted/30 px-4 py-3"
                >
                    <Package class="size-4 text-fl-gold-ink" />
                    <Link
                        :href="`/admin/products/${product.id}`"
                        class="font-semibold hover:underline"
                        >{{ product.name }}</Link
                    >
                    <span class="text-xs text-muted-foreground">{{
                        statusLabel(productType, product.type)
                    }}</span>
                    <span
                        v-if="product.tracks_units"
                        class="rounded-full border border-fl-gold/30 px-2 py-0.5 text-xs text-fl-gold-ink"
                        >Pieza única</span
                    >
                </header>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[560px] text-sm">
                        <thead>
                            <tr
                                class="text-left text-[11px] tracking-wide text-muted-foreground uppercase"
                            >
                                <th class="px-4 py-2 font-semibold">SKU</th>
                                <th class="px-3 py-2 text-right font-semibold">
                                    Disponible
                                </th>
                                <th class="px-3 py-2 text-right font-semibold">
                                    Piezas en stock
                                </th>
                                <th class="px-3 py-2 text-right font-semibold">
                                    Piezas totales
                                </th>
                                <th class="px-3 py-2 text-right font-semibold">
                                    Activadas
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="v in product.variants"
                                :key="v.id"
                                class="border-t border-border"
                            >
                                <td class="px-4 py-2">
                                    <Link
                                        :href="
                                            tagsIndex({
                                                query: { variant: v.id },
                                            }).url
                                        "
                                        class="font-mono text-xs hover:underline"
                                        >{{ v.sku }}</Link
                                    >
                                    <span
                                        class="block text-xs text-muted-foreground"
                                        >{{ v.name }}</span
                                    >
                                </td>
                                <td
                                    class="legacy-numeric px-3 py-2 text-right font-semibold"
                                >
                                    {{ v.stock }}
                                </td>
                                <td class="legacy-numeric px-3 py-2 text-right">
                                    {{ v.units_in_stock }}
                                </td>
                                <td
                                    class="legacy-numeric px-3 py-2 text-right text-muted-foreground"
                                >
                                    {{ v.units }}
                                </td>
                                <td
                                    class="legacy-numeric px-3 py-2 text-right text-emerald-700"
                                >
                                    {{ v.activated }}
                                </td>
                            </tr>
                            <tr v-if="!product.variants.length">
                                <td
                                    colspan="5"
                                    class="px-4 py-4 text-center text-xs text-muted-foreground"
                                >
                                    Sin variantes.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </article>
        </div>
        <EmptyState
            v-else
            :icon="Package"
            title="Aún no hay diseños"
            text="Asigna esta colección desde la ficha de un producto (Información → Colección)."
        />

        <ActionDialog
            v-model:open="confirmOpen"
            :title="hasProducts ? 'Archivar colección' : 'Eliminar colección'"
            :description="
                hasProducts
                    ? 'Tiene diseños: se archivará (deja de mostrarse en público) y sus productos conservan el vínculo.'
                    : 'No tiene diseños. Se eliminará junto con sus imágenes. Esta acción no se puede deshacer.'
            "
            :confirm-label="hasProducts ? 'Archivar' : 'Eliminar'"
            destructive
            :processing="deleting"
            @confirm="remove"
        />
    </div>
</template>
