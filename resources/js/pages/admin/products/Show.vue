<script setup lang="ts">
/**
 * Admin product (spec §16): one product, five in-page tabs — Información,
 * Media, Variantes + stock, Ventas, Pedidos recientes — instead of four
 * separate modules. Stock is changed only through the safe-adjust drawer
 * (audited, reason required). The Comercio workspace tabs come from the
 * admin shell.
 */
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    ExternalLink,
    Package,
    Plus,
    SlidersHorizontal,
    Trash2,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import ChannelPricesPanel from '@/components/admin/catalog/ChannelPricesPanel.vue';
import type { ChannelPricing } from '@/components/admin/catalog/ChannelPricesPanel.vue';
import ProductGalleryManager from '@/components/admin/ProductGalleryManager.vue';
import type { AdminProductMedia } from '@/components/admin/ProductGalleryManager.vue';
import InventoryMatrix from '@/components/commerce/InventoryMatrix.vue';
import type { MatrixCell } from '@/components/commerce/InventoryMatrix.vue';
import SalesBarChart from '@/components/commerce/SalesBarChart.vue';
import StockAdjustSheet from '@/components/commerce/StockAdjustSheet.vue';
import type { AdjustTarget } from '@/components/commerce/StockAdjustSheet.vue';
import FancySelect from '@/components/forms/FancySelect.vue';
import InputError from '@/components/InputError.vue';
import Money from '@/components/shared/Money.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    fulfillmentStatus,
    paymentStatus,
    productContentSectionType,
    productType,
    statusClass,
    statusLabel,
} from '@/lib/statusLabels';

type Variant = {
    id: number;
    sku: string;
    name: string;
    attributes: Record<string, string> | null;
    size: string | null;
    color: string | null;
    color_hex: string | null;
    base_price_minor: number;
    currency: string;
    active: boolean;
    stock: number;
    reserved: number;
    available: number;
    minimum_stock: number | null;
    minimum: number;
    alert: 'out' | 'low' | 'ok';
};

const props = defineProps<{
    product: {
        id: number;
        name: string;
        slug: string;
        description: string | null;
        tagline: string | null;
        type: string;
        availability: string;
        sort_order: number;
        public_url: string;
        category_id: number | null;
        brand: string | null;
        status: string;
        qr_capable: boolean;
        requires_shipping: boolean;
        tracks_inventory: boolean;
        minimum_stock: number | null;
        is_featured: boolean;
        active: boolean;
        concept_gallery?: { url: string; alt: string; srcset?: string }[];
        collection_id?: number | null;
        tracks_units?: boolean;
        material?: string | null;
        strap_type?: string | null;
        dimensions?: string | null;
        instructions?: string | null;
        seo_title?: string | null;
        seo_description?: string | null;
    };
    collections?: { id: number; name: string }[];
    channelPricing?: ChannelPricing;
    variants: Variant[];
    matrix: {
        sizes: string[];
        colors: string[];
        cells: Record<string, Record<string, MatrixCell>>;
    } | null;
    sales: {
        currency: string;
        all_time: { units: number; revenue_minor: number; orders: number };
        last_30: { units: number; revenue_minor: number; orders: number };
        series: { date: string; total_minor: number; units: number }[];
        by_variant: {
            variant_id: number | null;
            name: string;
            units: number;
            revenue_minor: number;
        }[];
    };
    recentOrders: {
        uuid: string;
        order_number: string;
        customer: string;
        units: number;
        lines: string[];
        status: string;
        payment_status: string;
        fulfillment_status: string;
        total_minor: number;
        currency: string;
        created_at: string;
    }[];
    reasons: { value: string; label: string }[];
    categories: { id: number; name: string }[];
    media: AdminProductMedia[];
    contentSections: {
        id: number;
        type: 'text' | 'features' | 'steps' | 'video' | 'faq';
        title: string;
        content: Record<string, unknown>;
        sort_order: number;
    }[];
}>();

// --- Tabs (kept in ?tab= so a reload or a shared link lands on it) ---------

const TABS = [
    { key: 'info', label: 'Información' },
    { key: 'media', label: 'Media' },
    { key: 'variants', label: 'Variantes y stock' },
    { key: 'sales', label: 'Ventas' },
    { key: 'orders', label: 'Pedidos recientes' },
] as const;
type TabKey = (typeof TABS)[number]['key'];

const page = usePage();
const initialTab = (): TabKey => {
    const match = /[?&]tab=([a-z]+)/.exec(page.url);
    const found = TABS.find((t) => t.key === match?.[1]);

    return found ? found.key : 'info';
};
const tab = ref<TabKey>(initialTab());

watch(tab, (value) => {
    if (typeof window === 'undefined') {
        return;
    }

    const url = new URL(window.location.href);
    url.searchParams.set('tab', value);
    window.history.replaceState(window.history.state, '', url);
});

// --- Variants --------------------------------------------------------------

const dialogOpen = ref(false);
const form = useForm({
    sku: '',
    name: '',
    size: '',
    color: '',
    base_price_minor: 0,
    minimum_stock: '' as number | '',
    active: true,
});

function submitVariant() {
    form.post(`/admin/products/${props.product.id}/variants`, {
        preserveScroll: true,
        onSuccess: () => {
            dialogOpen.value = false;
            form.reset();
        },
    });
}

const editingVariant = ref<Variant | null>(null);
const variantForm = useForm({
    name: '',
    size: '',
    color: '',
    base_price_minor: 0,
    minimum_stock: '' as number | '',
    active: true,
});

function editVariant(variant: Variant) {
    editingVariant.value = variant;
    variantForm.name = variant.name;
    variantForm.size = variant.size ?? '';
    variantForm.color = variant.color ?? '';
    variantForm.base_price_minor = variant.base_price_minor;
    variantForm.minimum_stock = variant.minimum_stock ?? '';
    variantForm.active = variant.active;
}

function saveVariant() {
    if (!editingVariant.value) {
        return;
    }

    variantForm.patch(`/admin/products/variants/${editingVariant.value.id}`, {
        preserveScroll: true,
        onSuccess: () => (editingVariant.value = null),
    });
}

const sheetOpen = ref(false);
const target = ref<AdjustTarget | null>(null);

function adjustVariant(variant: Variant) {
    target.value = {
        variant_id: variant.id,
        label: `${props.product.name} — ${variant.name}`,
        sku: variant.sku,
        available: variant.available,
        on_hand: variant.stock,
        reserved: variant.reserved,
        minimum: variant.minimum,
        minimum_own: variant.minimum_stock,
        image_url: props.media.find((m) => m.is_primary)?.url ?? null,
    };
    sheetOpen.value = true;
}

function adjustCell(cell: NonNullable<MatrixCell>) {
    const variant = props.variants.find((v) => v.id === cell.variant_id);

    if (variant) {
        adjustVariant(variant);
    }
}

const alertUi = {
    out: { label: 'Agotado', class: 'bg-red-50 text-red-700 ring-red-200' },
    low: { label: 'Bajo', class: 'bg-amber-50 text-amber-800 ring-amber-200' },
    ok: {
        label: 'Suficiente',
        class: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    },
} as const;

const hasMatrix = computed(
    () =>
        props.matrix !== null &&
        (props.matrix.sizes.length > 1 || props.matrix.colors.length > 1),
);

// --- Product details ---------------------------------------------------------

const detailsForm = useForm({
    name: props.product.name,
    tagline: props.product.tagline ?? '',
    description: props.product.description ?? '',
    type: props.product.type,
    category_id: props.product.category_id,
    brand: props.product.brand ?? '',
    status: props.product.status,
    availability: props.product.availability,
    sort_order: props.product.sort_order,
    minimum_stock: (props.product.minimum_stock ?? '') as number | '',
    is_featured: props.product.is_featured,
    qr_capable: props.product.qr_capable,
    requires_shipping: props.product.requires_shipping,
    tracks_inventory: props.product.tracks_inventory,
    active: props.product.active,
    // Finisher Legacy Tag catalog fields (all optional server-side).
    collection_id: (props.product.collection_id ?? null) as
        string | number | null,
    tracks_units: props.product.tracks_units ?? false,
    material: props.product.material ?? '',
    strap_type: props.product.strap_type ?? '',
    dimensions: props.product.dimensions ?? '',
    instructions: props.product.instructions ?? '',
    seo_title: props.product.seo_title ?? '',
    seo_description: props.product.seo_description ?? '',
});

const collectionOptions = computed(() => [
    { value: null, label: 'Sin colección' },
    ...(props.collections ?? []).map((c) => ({ value: c.id, label: c.name })),
]);

function saveDetails() {
    detailsForm.patch(`/admin/products/${props.product.id}`, {
        preserveScroll: true,
    });
}

const categoryOptions = computed(() => [
    { value: null, label: 'Sin categoría' },
    ...props.categories.map((c) => ({ value: c.id, label: c.name })),
]);

const availabilityOptions = [
    { value: 'available', label: 'Disponible (se puede comprar)' },
    { value: 'coming_soon', label: 'Próximamente (visible, sin venta)' },
    { value: 'concept', label: 'Concepto (visible, en desarrollo)' },
];

const statusOptions = [
    { value: 'draft', label: 'Borrador (oculto en la tienda)' },
    { value: 'active', label: 'Publicado' },
    { value: 'archived', label: 'Archivado' },
];

const typeOptions = [
    { value: 'legacy_tag', label: 'Finisher Legacy Tag' },
    { value: 'legacy_plate', label: 'Legacy Plate' },
    { value: 'apparel', label: 'Textil' },
    { value: 'accessory', label: 'Accesorio' },
    { value: 'equipment', label: 'Equipo' },
];

// --- Content sections --------------------------------------------------------

const sectionDialogOpen = ref(false);
const sectionForm = useForm({
    type: 'text' as string | number | null,
    title: '',
    body: '', // one field, interpreted per `type` on submit
});

const sectionTypeOptions = [
    { value: 'text', label: 'Texto (Qué es)' },
    { value: 'features', label: 'Características' },
    { value: 'steps', label: 'Cómo se usa (pasos)' },
    { value: 'video', label: 'Cómo funciona (video)' },
    { value: 'faq', label: 'Preguntas frecuentes' },
];

function submitSection() {
    let content: Record<string, unknown>;
    const lines = sectionForm.body.split('\n').filter(Boolean);
    const pair = (line: string) => {
        const [first, ...rest] = line.split('|');

        return [first.trim(), rest.join('|').trim()];
    };

    if (sectionForm.type === 'text' || sectionForm.type === 'video') {
        content = { body: sectionForm.body };
    } else if (sectionForm.type === 'features') {
        content = { items: lines };
    } else if (sectionForm.type === 'steps') {
        content = {
            items: lines.map((l) => {
                const [title, body] = pair(l);

                return { title, body };
            }),
        };
    } else {
        content = {
            items: lines.map((l) => {
                const [question, answer] = pair(l);

                return { question, answer };
            }),
        };
    }

    router.post(
        `/admin/products/${props.product.id}/content-sections`,
        // `content`'s shape varies per section type; it is plain JSON.
        { type: sectionForm.type, title: sectionForm.title, content } as any,
        {
            preserveScroll: true,
            onSuccess: () => {
                sectionDialogOpen.value = false;
                sectionForm.reset();
            },
        },
    );
}

function deleteSection(id: number) {
    router.delete(`/admin/products/content-sections/${id}`, {
        preserveScroll: true,
    });
}

const sectionTypeHelp: Record<string, string> = {
    text: 'Un párrafo de texto libre.',
    features: 'Una característica por línea.',
    steps: 'Un paso por línea: Título | Descripción',
    video: 'Describe el video (el archivo se sube en la pestaña Media).',
    faq: 'Una pregunta por línea: Pregunta | Respuesta',
};

const date = (iso: string) =>
    new Intl.DateTimeFormat('es-MX', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).format(new Date(iso));
</script>

<template>
    <Head :title="product.name" />

    <div class="w-full p-4 md:p-8">
        <div class="mb-2 flex flex-wrap items-center gap-2">
            <h1
                class="flex items-center gap-2 text-xl font-bold text-foreground"
            >
                <Package class="size-5 text-fl-gold-ink" />
                {{ product.name }}
            </h1>
            <Badge
                variant="outline"
                :class="statusClass(productType, product.type)"
                >{{ statusLabel(productType, product.type) }}</Badge
            >
            <Badge
                v-if="product.qr_capable"
                variant="outline"
                class="border-fl-gold/30 text-fl-gold-ink"
                >Producto conectado (NFC)</Badge
            >
            <a
                :href="product.public_url"
                target="_blank"
                rel="noopener"
                class="ml-auto inline-flex min-h-10 items-center gap-1.5 text-sm font-medium text-muted-foreground hover:text-foreground"
            >
                Ver en la tienda
                <ExternalLink class="size-3.5" />
            </a>
        </div>

        <!-- In-page tabs -->
        <nav
            class="-mx-4 mb-6 flex gap-1 overflow-x-auto border-b border-border px-4 md:mx-0 md:px-0"
            role="tablist"
            aria-label="Secciones del producto"
        >
            <button
                v-for="t in TABS"
                :key="t.key"
                type="button"
                role="tab"
                :aria-selected="tab === t.key"
                class="min-h-11 shrink-0 border-b-2 px-3 text-sm font-medium transition-colors"
                :class="
                    tab === t.key
                        ? 'border-fl-gold text-foreground'
                        : 'border-transparent text-muted-foreground hover:text-foreground'
                "
                @click="tab = t.key"
            >
                {{ t.label }}
            </button>
        </nav>

        <!-- INFORMACIÓN -->
        <section v-show="tab === 'info'" role="tabpanel">
            <form
                class="fl-card mb-8 grid gap-5 p-5 md:grid-cols-2 md:p-6"
                @submit.prevent="saveDetails"
            >
                <div class="grid gap-1.5">
                    <Label for="p-name">Nombre</Label>
                    <Input id="p-name" v-model="detailsForm.name" required />
                    <InputError :message="detailsForm.errors.name" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="p-tagline">Frase corta (tarjeta y ficha)</Label>
                    <Input
                        id="p-tagline"
                        v-model="detailsForm.tagline"
                        maxlength="160"
                        placeholder="Ej. Zamak niquelado · NFC integrado"
                    />
                    <InputError :message="detailsForm.errors.tagline" />
                </div>
                <div class="grid gap-1.5 md:col-span-2">
                    <Label for="p-description">Descripción</Label>
                    <Textarea
                        id="p-description"
                        v-model="detailsForm.description"
                        rows="3"
                    />
                    <InputError :message="detailsForm.errors.description" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="p-category">Categoría</Label>
                    <FancySelect
                        id="p-category"
                        v-model="detailsForm.category_id"
                        :options="categoryOptions"
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label for="p-type">Tipo</Label>
                    <FancySelect
                        id="p-type"
                        v-model="detailsForm.type"
                        :options="typeOptions"
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label for="p-status">Publicación</Label>
                    <FancySelect
                        id="p-status"
                        v-model="detailsForm.status"
                        :options="statusOptions"
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label for="p-availability">Disponibilidad</Label>
                    <FancySelect
                        id="p-availability"
                        v-model="detailsForm.availability"
                        :options="availabilityOptions"
                    />
                    <InputError :message="detailsForm.errors.availability" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="p-brand">Marca</Label>
                    <Input id="p-brand" v-model="detailsForm.brand" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="p-order">Orden en la tienda</Label>
                    <Input
                        id="p-order"
                        v-model.number="detailsForm.sort_order"
                        type="number"
                        min="0"
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label for="p-min"
                        >Mínimo para reponer (por variante)</Label
                    >
                    <Input
                        id="p-min"
                        v-model.number="detailsForm.minimum_stock"
                        type="number"
                        min="0"
                        placeholder="5 (por defecto)"
                    />
                    <InputError :message="detailsForm.errors.minimum_stock" />
                </div>
                <div
                    class="flex flex-wrap gap-x-6 gap-y-3 text-sm md:col-span-2"
                >
                    <label class="flex min-h-10 items-center gap-2">
                        <Checkbox
                            :model-value="detailsForm.active"
                            @update:model-value="
                                (v) => (detailsForm.active = !!v)
                            "
                        />
                        Activo
                    </label>
                    <label class="flex min-h-10 items-center gap-2">
                        <Checkbox
                            :model-value="detailsForm.is_featured"
                            @update:model-value="
                                (v) => (detailsForm.is_featured = !!v)
                            "
                        />
                        Destacado en la tienda
                    </label>
                    <label class="flex min-h-10 items-center gap-2">
                        <Checkbox
                            :model-value="detailsForm.qr_capable"
                            @update:model-value="
                                (v) => (detailsForm.qr_capable = !!v)
                            "
                        />
                        Producto conectado (NFC / Mi equipo)
                    </label>
                    <label class="flex min-h-10 items-center gap-2">
                        <Checkbox
                            :model-value="detailsForm.requires_shipping"
                            @update:model-value="
                                (v) => (detailsForm.requires_shipping = !!v)
                            "
                        />
                        Requiere envío
                    </label>
                    <label class="flex min-h-10 items-center gap-2">
                        <Checkbox
                            :model-value="detailsForm.tracks_inventory"
                            @update:model-value="
                                (v) => (detailsForm.tracks_inventory = !!v)
                            "
                        />
                        Controla inventario
                    </label>
                </div>

                <!-- Finisher Legacy Tag: collection, unit tracking, physical spec, SEO -->
                <div
                    class="grid gap-5 border-t border-border pt-5 md:col-span-2 md:grid-cols-2"
                >
                    <h3
                        class="text-sm font-semibold text-muted-foreground uppercase md:col-span-2"
                    >
                        Colección y ficha física
                    </h3>
                    <div class="grid gap-1.5">
                        <Label for="p-collection">Colección</Label>
                        <FancySelect
                            id="p-collection"
                            v-model="detailsForm.collection_id"
                            :options="collectionOptions"
                        />
                        <InputError
                            :message="detailsForm.errors.collection_id"
                        />
                    </div>
                    <label
                        class="flex min-h-10 items-start gap-2 rounded-xl border border-border p-3 text-sm"
                    >
                        <Checkbox
                            class="mt-0.5"
                            :model-value="detailsForm.tracks_units"
                            @update:model-value="
                                (v) => (detailsForm.tracks_units = !!v)
                            "
                        />
                        <span>
                            <span class="font-medium"
                                >Cada pieza es única y se activa</span
                            >
                            <span class="block text-xs text-muted-foreground"
                                >Cada unidad tiene su serie, su código NFC/QR y
                                su historial (lotes y tags).</span
                            >
                        </span>
                    </label>
                    <div class="grid gap-1.5">
                        <Label for="p-material">Material</Label>
                        <Input
                            id="p-material"
                            v-model="detailsForm.material"
                            maxlength="255"
                            placeholder="Ej. Acero inoxidable"
                        />
                        <InputError :message="detailsForm.errors.material" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="p-strap">Tipo de correa</Label>
                        <Input
                            id="p-strap"
                            v-model="detailsForm.strap_type"
                            maxlength="255"
                        />
                        <InputError :message="detailsForm.errors.strap_type" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="p-dimensions">Dimensiones</Label>
                        <Input
                            id="p-dimensions"
                            v-model="detailsForm.dimensions"
                            maxlength="120"
                            placeholder="Ej. 70 × 45 mm"
                        />
                        <InputError :message="detailsForm.errors.dimensions" />
                    </div>
                    <div class="grid gap-1.5 md:col-span-2">
                        <Label for="p-instructions"
                            >Instrucciones / cuidados</Label
                        >
                        <Textarea
                            id="p-instructions"
                            v-model="detailsForm.instructions"
                            rows="3"
                        />
                        <InputError
                            :message="detailsForm.errors.instructions"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="p-seo-title">Título SEO</Label>
                        <Input
                            id="p-seo-title"
                            v-model="detailsForm.seo_title"
                            maxlength="255"
                        />
                        <InputError :message="detailsForm.errors.seo_title" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="p-seo-desc">Descripción SEO</Label>
                        <Textarea
                            id="p-seo-desc"
                            v-model="detailsForm.seo_description"
                            rows="2"
                            maxlength="320"
                        />
                        <InputError
                            :message="detailsForm.errors.seo_description"
                        />
                    </div>
                </div>
                <div class="flex justify-end md:col-span-2">
                    <Button
                        type="submit"
                        class="rounded-full"
                        :disabled="detailsForm.processing"
                    >
                        Guardar cambios
                    </Button>
                </div>
            </form>

            <div class="mb-4 flex items-center justify-between">
                <h2
                    class="text-sm font-semibold text-muted-foreground uppercase"
                >
                    Contenido de la ficha
                </h2>
                <Button
                    size="sm"
                    class="rounded-full"
                    @click="sectionDialogOpen = true"
                >
                    <Plus class="size-3.5" />
                    Nueva sección
                </Button>
            </div>
            <div class="space-y-3">
                <div
                    v-for="section in contentSections"
                    :key="section.id"
                    class="flex items-start justify-between gap-3 rounded-xl border border-border bg-card p-4"
                >
                    <div>
                        <Badge
                            variant="outline"
                            :class="
                                statusClass(
                                    productContentSectionType,
                                    section.type,
                                )
                            "
                        >
                            {{
                                statusLabel(
                                    productContentSectionType,
                                    section.type,
                                )
                            }}
                        </Badge>
                        <p class="mt-1 font-medium text-foreground">
                            {{ section.title }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="flex size-10 shrink-0 items-center justify-center rounded-full text-red-700 hover:bg-red-50"
                        :aria-label="`Eliminar ${section.title}`"
                        @click="deleteSection(section.id)"
                    >
                        <Trash2 class="size-4" />
                    </button>
                </div>
                <p
                    v-if="!contentSections.length"
                    class="rounded-xl border border-dashed border-border px-4 py-8 text-center text-sm text-muted-foreground/80"
                >
                    Sin secciones todavía.
                </p>
            </div>
        </section>

        <!-- MEDIA -->
        <section v-show="tab === 'media'" role="tabpanel">
            <p class="mb-4 max-w-2xl text-sm text-muted-foreground">
                Fotos, videos y modelo 3D (GLB/GLTF). La principal es la portada
                en la tienda; la marcada como hover aparece al pasar el cursor.
                Arrastra para ordenar.
            </p>
            <ProductGalleryManager
                :product-id="product.id"
                :product-name="product.name"
                :concept-gallery="product.concept_gallery ?? []"
                :media="media"
            />
        </section>

        <!-- VARIANTES + STOCK -->
        <section v-show="tab === 'variants'" role="tabpanel" class="space-y-6">
            <div class="flex items-center justify-between">
                <p class="text-sm text-muted-foreground">
                    {{ variants.length }}
                    {{ variants.length === 1 ? 'variante' : 'variantes' }}
                    <template v-if="!product.tracks_inventory">
                        · este producto no controla inventario</template
                    >
                </p>
                <Button
                    size="sm"
                    class="rounded-full"
                    @click="dialogOpen = true"
                >
                    <Plus class="size-3.5" />
                    Nueva variante
                </Button>
            </div>

            <div
                v-if="hasMatrix && matrix"
                class="rounded-xl border border-border bg-card p-4"
            >
                <h3 class="mb-3 text-sm font-semibold">Tallas × color</h3>
                <InventoryMatrix
                    :sizes="matrix.sizes"
                    :colors="matrix.colors"
                    :cells="matrix.cells"
                    interactive
                    @select="adjustCell"
                />
            </div>

            <ul class="grid gap-3 lg:grid-cols-2">
                <li
                    v-for="variant in variants"
                    :key="variant.id"
                    class="rounded-xl border border-border bg-card p-4"
                >
                    <div class="flex items-start gap-3">
                        <span
                            v-if="variant.color_hex"
                            class="mt-1 size-4 shrink-0 rounded-full ring-1 ring-border"
                            :style="{ backgroundColor: variant.color_hex }"
                            aria-hidden="true"
                        />
                        <div class="min-w-0 flex-1">
                            <p class="font-medium">
                                {{ variant.name }}
                                <span
                                    v-if="!variant.active"
                                    class="ml-1 text-xs font-normal text-muted-foreground"
                                    >(inactiva)</span
                                >
                            </p>
                            <p class="text-xs text-muted-foreground">
                                <span class="font-mono">{{ variant.sku }}</span>
                                <template v-if="variant.size">
                                    · Talla {{ variant.size }}</template
                                >
                                <template v-if="variant.color">
                                    · {{ variant.color }}</template
                                >
                            </p>
                        </div>
                        <Money
                            class="legacy-numeric shrink-0 font-semibold"
                            :minor="variant.base_price_minor"
                            :currency="variant.currency"
                        />
                    </div>
                    <div
                        v-if="product.tracks_inventory"
                        class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground"
                    >
                        <span
                            >Disponible
                            <strong
                                class="legacy-numeric text-sm text-foreground"
                                >{{ variant.available }}</strong
                            ></span
                        >
                        <span>Almacén {{ variant.stock }}</span>
                        <span>Reservado {{ variant.reserved }}</span>
                        <span>Mín. {{ variant.minimum }}</span>
                        <span
                            class="rounded-full px-2 py-0.5 font-medium ring-1 ring-inset"
                            :class="alertUi[variant.alert].class"
                            >{{ alertUi[variant.alert].label }}</span
                        >
                    </div>
                    <div class="mt-3 flex gap-2">
                        <Button
                            v-if="product.tracks_inventory"
                            size="sm"
                            class="h-10 rounded-full"
                            @click="adjustVariant(variant)"
                        >
                            <SlidersHorizontal class="size-3.5" />
                            Ajustar stock
                        </Button>
                        <Button
                            size="sm"
                            variant="outline"
                            class="h-10 rounded-full"
                            @click="editVariant(variant)"
                            >Editar</Button
                        >
                    </div>
                </li>
            </ul>
            <p
                v-if="!variants.length"
                class="rounded-xl border border-dashed border-border px-4 py-10 text-center text-sm text-muted-foreground"
            >
                Sin variantes todavía.
            </p>

            <ChannelPricesPanel
                v-if="channelPricing"
                :product-id="product.id"
                :pricing="channelPricing"
                :variants="variants"
            />
        </section>

        <!-- VENTAS -->
        <section v-show="tab === 'sales'" role="tabpanel" class="space-y-6">
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div class="fl-card p-4">
                    <p class="text-xs text-muted-foreground">Últimos 30 días</p>
                    <p class="legacy-numeric mt-1 text-xl font-semibold">
                        <Money
                            :minor="sales.last_30.revenue_minor"
                            :currency="sales.currency"
                        />
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ sales.last_30.units }} piezas
                    </p>
                </div>
                <div class="fl-card p-4">
                    <p class="text-xs text-muted-foreground">Pedidos (30 d)</p>
                    <p class="legacy-numeric mt-1 text-xl font-semibold">
                        {{ sales.last_30.orders }}
                    </p>
                </div>
                <div class="fl-card p-4">
                    <p class="text-xs text-muted-foreground">Histórico</p>
                    <p class="legacy-numeric mt-1 text-xl font-semibold">
                        <Money
                            :minor="sales.all_time.revenue_minor"
                            :currency="sales.currency"
                        />
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ sales.all_time.units }} piezas
                    </p>
                </div>
                <div class="fl-card p-4">
                    <p class="text-xs text-muted-foreground">
                        Pedidos (histórico)
                    </p>
                    <p class="legacy-numeric mt-1 text-xl font-semibold">
                        {{ sales.all_time.orders }}
                    </p>
                </div>
            </div>
            <div class="fl-card p-4 md:p-5">
                <h3 class="mb-3 text-sm font-semibold">
                    Ventas por día (30 d)
                </h3>
                <SalesBarChart
                    :points="sales.series"
                    :currency="sales.currency"
                    :label="`Ventas pagadas por día de ${product.name}`"
                />
            </div>
            <div v-if="sales.by_variant.length" class="fl-card p-4 md:p-5">
                <h3 class="mb-3 text-sm font-semibold">Por variante</h3>
                <ul class="divide-y divide-border">
                    <li
                        v-for="row in sales.by_variant"
                        :key="row.variant_id ?? row.name"
                        class="flex items-center justify-between gap-3 py-2.5 text-sm"
                    >
                        <span class="min-w-0 truncate">{{ row.name }}</span>
                        <span class="shrink-0 text-right">
                            <span class="font-semibold"
                                >{{ row.units }} u.</span
                            >
                            ·
                            <Money
                                :minor="row.revenue_minor"
                                :currency="sales.currency"
                            />
                        </span>
                    </li>
                </ul>
            </div>
        </section>

        <!-- PEDIDOS RECIENTES -->
        <section v-show="tab === 'orders'" role="tabpanel">
            <ul v-if="recentOrders.length" class="space-y-2">
                <li v-for="order in recentOrders" :key="order.uuid">
                    <Link
                        :href="`/admin/orders/${order.uuid}`"
                        class="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-xl border border-border bg-card p-4 transition hover:border-foreground/30"
                    >
                        <div class="min-w-0 flex-1">
                            <p class="font-medium">
                                #{{ order.order_number }} · {{ order.customer }}
                            </p>
                            <p class="truncate text-xs text-muted-foreground">
                                {{ date(order.created_at) }} ·
                                {{ order.lines.join(', ') }}
                            </p>
                        </div>
                        <span
                            class="rounded-full border px-2 py-0.5 text-xs"
                            :class="
                                statusClass(paymentStatus, order.payment_status)
                            "
                            >{{
                                statusLabel(paymentStatus, order.payment_status)
                            }}</span
                        >
                        <span
                            class="rounded-full border px-2 py-0.5 text-xs"
                            :class="
                                statusClass(
                                    fulfillmentStatus,
                                    order.fulfillment_status,
                                )
                            "
                            >{{
                                statusLabel(
                                    fulfillmentStatus,
                                    order.fulfillment_status,
                                )
                            }}</span
                        >
                        <Money
                            class="legacy-numeric font-semibold"
                            :minor="order.total_minor"
                            :currency="order.currency"
                        />
                    </Link>
                </li>
            </ul>
            <p
                v-else
                class="rounded-xl border border-dashed border-border px-4 py-10 text-center text-sm text-muted-foreground"
            >
                Este producto todavía no aparece en ningún pedido.
            </p>
        </section>

        <StockAdjustSheet
            v-model:open="sheetOpen"
            :target="target"
            :reasons="reasons"
        />

        <!-- New variant -->
        <Dialog v-model:open="dialogOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Nueva variante</DialogTitle>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitVariant">
                    <div class="grid gap-2">
                        <Label for="nv-sku">SKU</Label>
                        <Input id="nv-sku" v-model="form.sku" required />
                        <InputError :message="form.errors.sku" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="nv-name">Nombre</Label>
                        <Input
                            id="nv-name"
                            v-model="form.name"
                            placeholder="Ej. Negro · M"
                            required
                        />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="grid gap-2">
                            <Label for="nv-size">Talla</Label>
                            <Input
                                id="nv-size"
                                v-model="form.size"
                                placeholder="M"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="nv-color">Color</Label>
                            <Input
                                id="nv-color"
                                v-model="form.color"
                                placeholder="Negro"
                            />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="grid gap-2">
                            <Label for="nv-price">Precio (centavos)</Label>
                            <Input
                                id="nv-price"
                                v-model.number="form.base_price_minor"
                                type="number"
                                placeholder="90000 = $900.00"
                                required
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="nv-min">Mínimo</Label>
                            <Input
                                id="nv-min"
                                v-model.number="form.minimum_stock"
                                type="number"
                                min="0"
                                placeholder="Heredado"
                            />
                        </div>
                    </div>
                    <label class="flex items-center gap-2 text-sm">
                        <Checkbox
                            :model-value="form.active"
                            @update:model-value="(v) => (form.active = !!v)"
                        />
                        Activa
                    </label>
                    <DialogFooter>
                        <Button
                            type="submit"
                            class="w-full rounded-full sm:w-auto"
                            :disabled="form.processing"
                        >
                            Crear variante
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Edit variant -->
        <Dialog
            :open="editingVariant !== null"
            @update:open="(v) => !v && (editingVariant = null)"
        >
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Editar variante</DialogTitle>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="saveVariant">
                    <div class="grid gap-2">
                        <Label for="ev-name">Nombre</Label>
                        <Input
                            id="ev-name"
                            v-model="variantForm.name"
                            required
                        />
                        <InputError :message="variantForm.errors.name" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="grid gap-2">
                            <Label for="ev-size">Talla</Label>
                            <Input id="ev-size" v-model="variantForm.size" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="ev-color">Color</Label>
                            <Input id="ev-color" v-model="variantForm.color" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="grid gap-2">
                            <Label for="ev-price">Precio (centavos)</Label>
                            <Input
                                id="ev-price"
                                v-model.number="variantForm.base_price_minor"
                                type="number"
                                required
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="ev-min">Mínimo</Label>
                            <Input
                                id="ev-min"
                                v-model.number="variantForm.minimum_stock"
                                type="number"
                                min="0"
                                placeholder="Heredado"
                            />
                        </div>
                    </div>
                    <label class="flex items-center gap-2 text-sm">
                        <Checkbox
                            :model-value="variantForm.active"
                            @update:model-value="
                                (v) => (variantForm.active = !!v)
                            "
                        />
                        Activa
                    </label>
                    <DialogFooter>
                        <Button
                            type="submit"
                            class="w-full rounded-full sm:w-auto"
                            :disabled="variantForm.processing"
                            >Guardar</Button
                        >
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- New content section -->
        <Dialog v-model:open="sectionDialogOpen">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Nueva sección de contenido</DialogTitle>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitSection">
                    <div class="grid gap-2">
                        <Label for="sec-type">Tipo</Label>
                        <FancySelect
                            id="sec-type"
                            v-model="sectionForm.type"
                            :options="sectionTypeOptions"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="sec-title">Título</Label>
                        <Input
                            id="sec-title"
                            v-model="sectionForm.title"
                            required
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="sec-body">Contenido</Label>
                        <p class="text-xs text-muted-foreground/80">
                            {{ sectionTypeHelp[String(sectionForm.type)] }}
                        </p>
                        <Textarea
                            id="sec-body"
                            v-model="sectionForm.body"
                            rows="6"
                        />
                    </div>
                    <DialogFooter>
                        <Button
                            type="submit"
                            class="w-full rounded-full sm:w-auto"
                        >
                            Guardar sección
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
