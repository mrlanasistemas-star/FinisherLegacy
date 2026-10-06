<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ExternalLink, Package, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import ProductGalleryManager from '@/components/admin/ProductGalleryManager.vue';
import type { AdminProductMedia } from '@/components/admin/ProductGalleryManager.vue';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import {
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
    base_price_minor: number;
    currency: string;
    active: boolean;
    stock: number;
    reserved: number;
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
        active: boolean;
    };
    variants: Variant[];
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

const dialogOpen = ref(false);
const form = useForm({
    sku: '',
    name: '',
    base_price_minor: 0,
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
    qr_capable: props.product.qr_capable,
    requires_shipping: props.product.requires_shipping,
    tracks_inventory: props.product.tracks_inventory,
    active: props.product.active,
});

function saveDetails() {
    detailsForm.patch(`/admin/products/${props.product.id}`, {
        preserveScroll: true,
    });
}

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
    { value: 'legacy_plate', label: 'Legacy Plate' },
    { value: 'apparel', label: 'Textil' },
    { value: 'accessory', label: 'Accesorio' },
    { value: 'equipment', label: 'Equipo' },
];

// --- Content sections --------------------------------------------------------

const sectionDialogOpen = ref(false);
const sectionForm = useForm({
    type: 'text',
    title: '',
    body: '', // one field, interpreted per `type` on submit
});

function submitSection() {
    let content: Record<string, unknown>;

    if (sectionForm.type === 'text' || sectionForm.type === 'video') {
        content = { body: sectionForm.body };
    } else if (sectionForm.type === 'features') {
        content = { items: sectionForm.body.split('\n').filter(Boolean) };
    } else if (sectionForm.type === 'steps') {
        content = {
            items: sectionForm.body
                .split('\n')
                .filter(Boolean)
                .map((line) => {
                    const [title, ...rest] = line.split('|');

                    return { title: title.trim(), body: rest.join('|').trim() };
                }),
        };
    } else {
        content = {
            items: sectionForm.body
                .split('\n')
                .filter(Boolean)
                .map((line) => {
                    const [question, ...rest] = line.split('|');

                    return {
                        question: question.trim(),
                        answer: rest.join('|').trim(),
                    };
                }),
        };
    }

    router.post(
        `/admin/products/${props.product.id}/content-sections`,
        // `content`'s shape varies by section type (body string vs. items
        // arrays) — it's genuine JSON-serializable data Inertia can send
        // fine, TypeScript just can't prove it against FormDataConvertible
        // without a much wider return type on submitSection() above.
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
    video: 'Describe el video (el archivo se sube en la Galería arriba).',
    faq: 'Una pregunta por línea: Pregunta | Respuesta',
};
</script>

<template>
    <Head :title="product.name" />

    <div class="p-4 md:p-8">
        <div class="mb-6 flex items-center gap-3">
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
                >Compatible con QR</Badge
            >
            <a
                :href="product.public_url"
                target="_blank"
                rel="noopener"
                class="ml-auto inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground hover:text-foreground"
            >
                Ver en la tienda
                <ExternalLink class="size-3.5" />
            </a>
        </div>
        <p class="mb-6 max-w-2xl text-sm text-muted-foreground">
            {{ product.description }}
        </p>

        <form
            class="fl-card mb-10 grid gap-5 p-5 md:grid-cols-2 md:p-6"
            @submit.prevent="saveDetails"
        >
            <h2 class="text-sm font-semibold md:col-span-2">
                Datos del producto
            </h2>
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
                    placeholder="Ej. Acero inoxidable · Tecnología NFC"
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
                <select
                    id="p-category"
                    v-model="detailsForm.category_id"
                    class="h-9 rounded-md border border-input bg-card px-3 text-sm"
                >
                    <option :value="null">Sin categoría</option>
                    <option
                        v-for="category in categories"
                        :key="category.id"
                        :value="category.id"
                    >
                        {{ category.name }}
                    </option>
                </select>
            </div>
            <div class="grid gap-1.5">
                <Label for="p-type">Tipo</Label>
                <select
                    id="p-type"
                    v-model="detailsForm.type"
                    class="h-9 rounded-md border border-input bg-card px-3 text-sm"
                >
                    <option
                        v-for="option in typeOptions"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>
            </div>
            <div class="grid gap-1.5">
                <Label for="p-status">Publicación</Label>
                <select
                    id="p-status"
                    v-model="detailsForm.status"
                    class="h-9 rounded-md border border-input bg-card px-3 text-sm"
                >
                    <option
                        v-for="option in statusOptions"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>
            </div>
            <div class="grid gap-1.5">
                <Label for="p-availability">Disponibilidad</Label>
                <select
                    id="p-availability"
                    v-model="detailsForm.availability"
                    class="h-9 rounded-md border border-input bg-card px-3 text-sm"
                >
                    <option
                        v-for="option in availabilityOptions"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>
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
            <div class="flex flex-wrap gap-x-6 gap-y-3 text-sm md:col-span-2">
                <label class="flex items-center gap-2">
                    <Checkbox
                        :model-value="detailsForm.active"
                        @update:model-value="(v) => (detailsForm.active = !!v)"
                    />
                    Activo
                </label>
                <label class="flex items-center gap-2">
                    <Checkbox
                        :model-value="detailsForm.qr_capable"
                        @update:model-value="
                            (v) => (detailsForm.qr_capable = !!v)
                        "
                    />
                    Compatible con Legacy Code
                </label>
                <label class="flex items-center gap-2">
                    <Checkbox
                        :model-value="detailsForm.requires_shipping"
                        @update:model-value="
                            (v) => (detailsForm.requires_shipping = !!v)
                        "
                    />
                    Requiere envío
                </label>
                <label class="flex items-center gap-2">
                    <Checkbox
                        :model-value="detailsForm.tracks_inventory"
                        @update:model-value="
                            (v) => (detailsForm.tracks_inventory = !!v)
                        "
                    />
                    Controla inventario
                </label>
            </div>
            <div class="flex justify-end md:col-span-2">
                <Button type="submit" :disabled="detailsForm.processing">
                    Guardar cambios
                </Button>
            </div>
        </form>

        <div class="mb-6 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-muted-foreground uppercase">
                Variantes
            </h2>
            <Button size="sm" @click="dialogOpen = true">
                <Plus class="size-3.5" />
                Nueva variante
            </Button>
        </div>

        <div class="overflow-x-auto rounded-xl border border-border">
            <table class="w-full text-sm">
                <thead>
                    <tr
                        class="border-b border-border bg-card/40 text-left text-xs text-muted-foreground uppercase"
                    >
                        <th class="px-4 py-3 font-medium">SKU</th>
                        <th class="px-4 py-3 font-medium">Nombre</th>
                        <th class="px-4 py-3 font-medium">Precio base</th>
                        <th
                            v-if="product.tracks_inventory"
                            class="px-4 py-3 font-medium"
                        >
                            Stock
                        </th>
                        <th class="px-4 py-3 font-medium">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="variant in variants"
                        :key="variant.id"
                        class="border-b border-border text-foreground last:border-0"
                    >
                        <td class="px-4 py-3 font-mono text-fl-gold-ink">
                            {{ variant.sku }}
                        </td>
                        <td class="px-4 py-3">{{ variant.name }}</td>
                        <td class="px-4 py-3">
                            <Money
                                :minor="variant.base_price_minor"
                                :currency="variant.currency"
                            />
                        </td>
                        <td v-if="product.tracks_inventory" class="px-4 py-3">
                            {{ variant.stock - variant.reserved }} disponibles
                            <span class="text-xs text-muted-foreground/80"
                                >({{ variant.reserved }} reservado)</span
                            >
                        </td>
                        <td class="px-4 py-3">
                            <Badge
                                variant="outline"
                                :class="
                                    variant.active
                                        ? 'border-emerald-500/30 text-emerald-700'
                                        : 'border-foreground/15 text-muted-foreground/80'
                                "
                            >
                                {{ variant.active ? 'Activa' : 'Inactiva' }}
                            </Badge>
                        </td>
                    </tr>
                    <tr v-if="!variants.length">
                        <td
                            colspan="5"
                            class="px-4 py-10 text-center text-muted-foreground/80"
                        >
                            Sin variantes todavía.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Galería -->
        <div class="mt-10 mb-4">
            <h2 class="text-sm font-semibold text-muted-foreground uppercase">
                Fotografías del producto
            </h2>
            <p class="mt-1 text-sm text-muted-foreground">
                La imagen principal se usa en la tienda y en las tarjetas; la
                marcada como hover aparece al pasar el cursor.
            </p>
        </div>
        <ProductGalleryManager
            :product-id="product.id"
            :product-name="product.name"
            :media="media"
        />

        <!-- Contenido -->
        <div class="mt-10 mb-4 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-muted-foreground uppercase">
                Contenido (cómo funciona, características, FAQ…)
            </h2>
            <Button size="sm" @click="sectionDialogOpen = true">
                <Plus class="size-3.5" />
                Nueva sección
            </Button>
        </div>
        <div class="space-y-3">
            <div
                v-for="section in contentSections"
                :key="section.id"
                class="flex items-start justify-between gap-3 rounded-xl border border-border bg-card/30 p-4"
            >
                <div>
                    <Badge
                        variant="outline"
                        :class="
                            statusClass(productContentSectionType, section.type)
                        "
                    >
                        {{
                            statusLabel(productContentSectionType, section.type)
                        }}
                    </Badge>
                    <p class="mt-1 font-medium text-foreground">
                        {{ section.title }}
                    </p>
                </div>
                <button
                    type="button"
                    class="shrink-0 text-red-700 hover:text-red-700"
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

        <Dialog v-model:open="dialogOpen">
            <DialogContent
                class="border-border bg-card text-foreground sm:max-w-md"
            >
                <DialogHeader>
                    <DialogTitle>Nueva variante</DialogTitle>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitVariant">
                    <div class="grid gap-2">
                        <Label>SKU</Label>
                        <Input
                            v-model="form.sku"
                            class="bg-background"
                            required
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label>Nombre (talla / color / etc.)</Label>
                        <Input
                            v-model="form.name"
                            class="bg-background"
                            required
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label>Precio base (centavos)</Label>
                        <Input
                            v-model.number="form.base_price_minor"
                            type="number"
                            class="bg-background"
                            placeholder="90000 = $900.00"
                            required
                        />
                    </div>
                    <label
                        class="flex items-center gap-2 text-sm text-muted-foreground"
                    >
                        <Checkbox
                            :model-value="form.active"
                            @update:model-value="(v) => (form.active = !!v)"
                        />
                        Activa
                    </label>
                    <DialogFooter>
                        <Button
                            type="submit"
                            class="w-full sm:w-auto"
                            :disabled="form.processing"
                        >
                            Crear variante
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="sectionDialogOpen">
            <DialogContent
                class="border-border bg-card text-foreground sm:max-w-lg"
            >
                <DialogHeader>
                    <DialogTitle>Nueva sección de contenido</DialogTitle>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitSection">
                    <div class="grid gap-2">
                        <Label>Tipo</Label>
                        <Select v-model="sectionForm.type">
                            <SelectTrigger
                                class="border-border bg-background text-foreground"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="text"
                                    >Texto (Qué es)</SelectItem
                                >
                                <SelectItem value="features"
                                    >Características</SelectItem
                                >
                                <SelectItem value="steps"
                                    >Cómo se usa (pasos)</SelectItem
                                >
                                <SelectItem value="video"
                                    >Cómo funciona (video)</SelectItem
                                >
                                <SelectItem value="faq"
                                    >Preguntas frecuentes</SelectItem
                                >
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid gap-2">
                        <Label>Título</Label>
                        <Input
                            v-model="sectionForm.title"
                            class="bg-background"
                            required
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label>Contenido</Label>
                        <p class="text-xs text-muted-foreground/80">
                            {{ sectionTypeHelp[sectionForm.type] }}
                        </p>
                        <Textarea
                            v-model="sectionForm.body"
                            rows="6"
                            class="border-border bg-background text-foreground"
                        />
                    </div>
                    <DialogFooter>
                        <Button type="submit" class="w-full sm:w-auto">
                            Guardar sección
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
