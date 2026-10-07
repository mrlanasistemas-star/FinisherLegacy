<script setup lang="ts">
/**
 * Admin product catalog as visual cards: the primary photo, a strip with
 * the rest of the gallery, price, stock, availability and status at a
 * glance. Everything comes from ProductMedia / variants — no table.
 */
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    Boxes,
    Camera,
    Eye,
    EyeOff,
    ImagePlus,
    Layers,
    Nfc,
    Package,
    Plus,
    Search,
    Tag,
} from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { ref } from 'vue';
import SecondaryNav from '@/components/admin/SecondaryNav.vue';
import FancySelect from '@/components/forms/FancySelect.vue';
import InputError from '@/components/InputError.vue';
import Pagination from '@/components/public/Pagination.vue';
import Money from '@/components/shared/Money.vue';
import ProductImagePlaceholder from '@/components/shared/ProductImagePlaceholder.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { CATALOG_AREA_NAV } from '@/config/areaNav';
import { productStatus, statusLabel } from '@/lib/statusLabels';

type ProductRow = {
    id: number;
    name: string;
    slug: string;
    tagline: string | null;
    type: string;
    category: string;
    status: string;
    availability: string;
    qr_capable: boolean;
    tracks_inventory: boolean;
    active: boolean;
    variants_count: number;
    gallery: { id: number; url: string; is_primary: boolean }[];
    media_count: number;
    concept_image_url: string | null;
    from_price_minor: number | null;
    currency: string;
    stock: number | null;
};

const props = defineProps<{
    products: {
        data: ProductRow[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: { q: string };
    categories: { id: number; name: string }[];
}>();

const query = ref(props.filters.q ?? '');
const search = useDebounceFn(() => {
    router.get(
        '/admin/products',
        { q: query.value || undefined },
        { preserveState: true, replace: true },
    );
}, 350);

const typeLabels: Record<string, string> = {
    legacy_plate: 'Legacy Plate',
    apparel: 'Textil',
    accessory: 'Accesorio',
    equipment: 'Equipo',
};

const availabilityChip: Record<string, { label: string; class: string }> = {
    available: {
        label: 'Disponible',
        class: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    },
    coming_soon: {
        label: 'Próximamente',
        class: 'bg-sky-50 text-sky-700 border-sky-200',
    },
    concept: {
        label: 'Concepto',
        class: 'bg-violet-50 text-violet-700 border-violet-200',
    },
};

const statusChip: Record<string, string> = {
    active: 'bg-foreground text-background',
    draft: 'bg-muted text-muted-foreground',
    archived: 'bg-muted text-muted-foreground line-through',
};

// --- Create ------------------------------------------------------------------

const dialogOpen = ref(false);
const preview = ref<string | null>(null);
const form = useForm({
    name: '',
    description: '',
    type: 'apparel' as string | number | null,
    category_id: null as string | number | null,
    status: 'active' as string | number | null,
    availability: 'available' as string | number | null,
    qr_capable: false,
    tracks_inventory: true,
    active: true,
    image: null as File | null,
});

function pickImage(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    form.image = file;
    preview.value = file ? URL.createObjectURL(file) : null;
}

function submit() {
    form.post('/admin/products', {
        forceFormData: true,
        onSuccess: () => (dialogOpen.value = false),
    });
}

const typeOptions = [
    { value: 'legacy_plate', label: 'Legacy Plate', icon: Nfc },
    { value: 'apparel', label: 'Textil', icon: Layers },
    { value: 'accessory', label: 'Accesorio', icon: Tag },
    { value: 'equipment', label: 'Equipo', icon: Boxes },
];
</script>

<template>
    <Head title="Productos" />

    <div class="w-full p-4 md:p-8">
        <SecondaryNav :items="CATALOG_AREA_NAV" />

        <div
            class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-3">
                <span
                    class="flex size-11 items-center justify-center rounded-xl bg-fl-cream text-fl-gold-ink"
                >
                    <Package class="size-5" />
                </span>
                <div>
                    <h1 class="text-xl font-semibold">Productos</h1>
                    <p class="text-sm text-muted-foreground">
                        Tu catálogo tal como se ve en la tienda.
                    </p>
                </div>
            </div>
            <div class="flex gap-2">
                <div class="relative">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <input
                        v-model="query"
                        type="search"
                        placeholder="Buscar producto…"
                        aria-label="Buscar producto"
                        class="h-10 w-56 rounded-full border border-input bg-card pr-4 pl-10 text-sm outline-none hover:border-foreground/25 focus:border-fl-gold focus:ring-4 focus:ring-fl-gold/15"
                        @input="search"
                    />
                </div>
                <Button class="rounded-full" @click="dialogOpen = true">
                    <Plus class="size-4" />
                    Nuevo producto
                </Button>
            </div>
        </div>

        <div
            v-if="products.data.length"
            class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4"
        >
            <Link
                v-for="product in products.data"
                :key="product.id"
                :href="`/admin/products/${product.id}`"
                class="group fl-card flex flex-col overflow-hidden transition-[border-color,box-shadow,transform] duration-200 hover:-translate-y-0.5 hover:border-foreground/20 hover:shadow-[0_22px_44px_-28px_rgb(23_23_20/0.4)]"
            >
                <div class="relative aspect-[4/3] overflow-hidden bg-fl-cream">
                    <img
                        v-if="product.gallery[0]"
                        :src="product.gallery[0].url"
                        :alt="product.name"
                        loading="lazy"
                        class="size-full object-cover transition-transform duration-500 group-hover:scale-[1.04]"
                    />
                    <template v-else-if="product.concept_image_url">
                        <img
                            :src="product.concept_image_url"
                            :alt="product.name"
                            loading="lazy"
                            class="size-full object-cover opacity-90 transition-transform duration-500 group-hover:scale-[1.04]"
                        />
                        <span
                            class="absolute inset-x-3 bottom-3 rounded-lg bg-white/95 px-2.5 py-1.5 text-[11px] leading-tight shadow-sm"
                        >
                            <span class="block font-semibold text-violet-700"
                                >Imagen conceptual</span
                            >
                            <span class="text-muted-foreground"
                                >Sube una fotografía real para
                                reemplazarla.</span
                            >
                        </span>
                    </template>
                    <ProductImagePlaceholder
                        v-else
                        :name="product.name"
                        label="Sin fotos — agrega la primera"
                    />
                    <div class="absolute top-3 left-3 flex flex-wrap gap-1.5">
                        <span
                            class="rounded-full px-2.5 py-1 text-[10px] font-semibold tracking-wide uppercase"
                            :class="
                                statusChip[product.status] ?? statusChip.draft
                            "
                            >{{
                                statusLabel(productStatus, product.status)
                            }}</span
                        >
                        <span
                            class="rounded-full border px-2.5 py-1 text-[10px] font-semibold tracking-wide uppercase"
                            :class="
                                (
                                    availabilityChip[product.availability] ??
                                    availabilityChip.available
                                ).class
                            "
                            >{{
                                (
                                    availabilityChip[product.availability] ??
                                    availabilityChip.available
                                ).label
                            }}</span
                        >
                    </div>
                    <span
                        class="absolute top-3 right-3 inline-flex items-center gap-1 rounded-full bg-white/95 px-2 py-1 text-[11px] font-semibold"
                    >
                        <Camera class="size-3" /> {{ product.media_count }}
                    </span>
                </div>

                <!-- Gallery strip -->
                <div
                    v-if="product.gallery.length > 1"
                    class="flex gap-1.5 border-b border-border bg-card p-2"
                >
                    <img
                        v-for="photo in product.gallery.slice(1, 5)"
                        :key="photo.id"
                        :src="photo.url"
                        alt=""
                        loading="lazy"
                        class="size-12 rounded-md object-cover"
                    />
                    <span
                        v-if="product.media_count > 5"
                        class="flex size-12 items-center justify-center rounded-md bg-muted text-xs font-semibold text-muted-foreground"
                        >+{{ product.media_count - 5 }}</span
                    >
                </div>

                <div class="flex flex-1 flex-col p-4">
                    <p class="fl-eyebrow">
                        {{ typeLabels[product.type] ?? product.type }}
                        <template v-if="product.category !== '—'">
                            · {{ product.category }}</template
                        >
                    </p>
                    <h2 class="mt-1 leading-snug font-semibold">
                        {{ product.name }}
                    </h2>
                    <p
                        v-if="product.tagline"
                        class="mt-0.5 line-clamp-1 text-sm text-muted-foreground"
                    >
                        {{ product.tagline }}
                    </p>

                    <div
                        class="mt-auto grid grid-cols-3 gap-2 border-t border-border pt-3 text-center"
                    >
                        <div>
                            <p
                                class="text-[10px] text-muted-foreground uppercase"
                            >
                                Desde
                            </p>
                            <p class="text-sm font-semibold">
                                <Money
                                    v-if="product.from_price_minor !== null"
                                    :minor="product.from_price_minor"
                                    :currency="product.currency"
                                />
                                <span v-else>—</span>
                            </p>
                        </div>
                        <div>
                            <p
                                class="text-[10px] text-muted-foreground uppercase"
                            >
                                Variantes
                            </p>
                            <p class="legacy-numeric text-sm font-semibold">
                                {{ product.variants_count }}
                            </p>
                        </div>
                        <div>
                            <p
                                class="text-[10px] text-muted-foreground uppercase"
                            >
                                Stock
                            </p>
                            <p
                                class="legacy-numeric text-sm font-semibold"
                                :class="
                                    product.stock !== null && product.stock <= 5
                                        ? 'text-red-700'
                                        : ''
                                "
                            >
                                {{ product.stock ?? '∞' }}
                            </p>
                        </div>
                    </div>
                    <p
                        class="mt-3 flex items-center gap-1.5 text-xs text-muted-foreground"
                    >
                        <component
                            :is="product.active ? Eye : EyeOff"
                            class="size-3.5"
                        />
                        {{ product.active ? 'Visible' : 'Oculto' }}
                        <template v-if="product.qr_capable">
                            · <Nfc class="size-3.5" /> Legacy Code
                        </template>
                    </p>
                </div>
            </Link>
        </div>

        <div
            v-else
            class="fl-card flex flex-col items-center gap-3 p-14 text-center"
        >
            <Package class="size-8 text-fl-gold-ink" />
            <p class="font-semibold">Aún no hay productos</p>
            <Button class="rounded-full" @click="dialogOpen = true">
                <Plus class="size-4" /> Crear el primero
            </Button>
        </div>

        <div class="mt-8">
            <Pagination :links="products.links" />
        </div>

        <Dialog v-model:open="dialogOpen">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <Package class="size-5 text-fl-gold-ink" />
                        Nuevo producto
                    </DialogTitle>
                    <DialogDescription>
                        Después podrás subir toda su galería de fotos y sus
                        variantes.
                    </DialogDescription>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submit">
                    <div class="grid gap-1.5">
                        <Label for="np-name">Nombre</Label>
                        <Input id="np-name" v-model="form.name" required />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-1.5">
                            <Label>Tipo</Label>
                            <FancySelect
                                v-model="form.type"
                                :options="typeOptions"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Categoría</Label>
                            <FancySelect
                                v-model="form.category_id"
                                :options="[
                                    { value: null, label: 'Sin categoría' },
                                    ...categories.map((c) => ({
                                        value: c.id,
                                        label: c.name,
                                    })),
                                ]"
                            />
                        </div>
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Disponibilidad</Label>
                        <FancySelect
                            v-model="form.availability"
                            :options="[
                                { value: 'available', label: 'Disponible' },
                                { value: 'coming_soon', label: 'Próximamente' },
                                { value: 'concept', label: 'Concepto' },
                            ]"
                        />
                    </div>
                    <label
                        class="flex cursor-pointer items-center gap-4 rounded-xl border-2 border-dashed border-foreground/15 p-4 transition-colors hover:border-fl-gold"
                    >
                        <img
                            v-if="preview"
                            :src="preview"
                            alt=""
                            class="size-16 rounded-lg object-cover"
                        />
                        <span
                            v-else
                            class="flex size-16 items-center justify-center rounded-lg bg-fl-cream text-fl-gold-ink"
                        >
                            <ImagePlus class="size-6" />
                        </span>
                        <span class="text-sm">
                            <span class="block font-medium"
                                >Foto principal (opcional)</span
                            >
                            <span class="text-xs text-muted-foreground"
                                >JPG, PNG o WEBP</span
                            >
                        </span>
                        <input
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            class="sr-only"
                            @change="pickImage"
                        />
                    </label>
                    <DialogFooter>
                        <Button
                            type="submit"
                            class="rounded-full"
                            :disabled="form.processing"
                        >
                            Crear producto
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
