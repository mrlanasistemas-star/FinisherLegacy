<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Image, Package, Plus, Star, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
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
        description: string | null;
        type: string;
        status: string;
        qr_capable: boolean;
        requires_shipping: boolean;
        tracks_inventory: boolean;
        active: boolean;
    };
    variants: Variant[];
    categories: { id: number; name: string }[];
    media: {
        id: number;
        type: 'image' | 'video';
        url: string;
        poster_url: string | null;
        is_primary: boolean;
        alt_text: string | null;
        sort_order: number;
    }[];
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

// --- Gallery ---------------------------------------------------------------

const MAX_FILES_PER_UPLOAD = 10;

const mediaForm = useForm<{ files: File[]; alt_text: string }>({
    files: [],
    alt_text: '',
});

function uploadMedia(event: Event) {
    const input = event.target as HTMLInputElement;
    const files = Array.from(input.files ?? []).slice(0, MAX_FILES_PER_UPLOAD);

    if (!files.length) {
        return;
    }

    mediaForm.files = files;
    mediaForm.post(`/admin/products/${props.product.id}/media`, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => mediaForm.reset(),
        onFinish: () => {
            input.value = '';
        },
    });
}

function setPrimaryMedia(id: number) {
    router.post(
        `/admin/products/media/${id}/primary`,
        {},
        { preserveScroll: true },
    );
}

function deleteMedia(id: number) {
    router.delete(`/admin/products/media/${id}`, { preserveScroll: true });
}

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
            <h1 class="flex items-center gap-2 text-xl font-bold text-white">
                <Package class="size-5 text-fl-gold" />
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
                class="border-fl-gold/30 text-fl-gold-soft"
                >Compatible con QR</Badge
            >
        </div>
        <p class="mb-6 max-w-2xl text-sm text-white/50">
            {{ product.description }}
        </p>

        <div class="mb-6 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-white/70 uppercase">
                Variantes
            </h2>
            <Button
                size="sm"
                class="bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                @click="dialogOpen = true"
            >
                <Plus class="size-3.5" />
                Nueva variante
            </Button>
        </div>

        <div class="overflow-x-auto rounded-xl border border-white/10">
            <table class="w-full text-sm">
                <thead>
                    <tr
                        class="border-b border-white/10 bg-fl-graphite/40 text-left text-xs text-white/50 uppercase"
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
                        class="border-b border-white/5 text-white/80 last:border-0"
                    >
                        <td class="px-4 py-3 font-mono text-fl-gold-soft">
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
                            <span class="text-xs text-white/30"
                                >({{ variant.reserved }} reservado)</span
                            >
                        </td>
                        <td class="px-4 py-3">
                            <Badge
                                variant="outline"
                                :class="
                                    variant.active
                                        ? 'border-emerald-500/30 text-emerald-400'
                                        : 'border-white/20 text-white/40'
                                "
                            >
                                {{ variant.active ? 'Activa' : 'Inactiva' }}
                            </Badge>
                        </td>
                    </tr>
                    <tr v-if="!variants.length">
                        <td
                            colspan="5"
                            class="px-4 py-10 text-center text-white/30"
                        >
                            Sin variantes todavía.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Galería -->
        <div class="mt-10 mb-4 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-white/70 uppercase">
                Galería
            </h2>
            <label>
                <Button
                    as="span"
                    size="sm"
                    class="cursor-pointer bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                >
                    <Plus class="size-3.5" />
                    Subir imágenes o videos
                </Button>
                <input
                    type="file"
                    accept="image/*,video/*"
                    multiple
                    class="hidden"
                    @change="uploadMedia"
                />
            </label>
        </div>
        <div class="grid grid-cols-3 gap-3 sm:grid-cols-4 lg:grid-cols-6">
            <div
                v-for="item in media"
                :key="item.id"
                class="group relative aspect-square overflow-hidden rounded-xl border border-white/10 bg-fl-black"
            >
                <img
                    v-if="item.type === 'image'"
                    :src="item.url"
                    class="size-full object-cover"
                />
                <video
                    v-else
                    :src="item.url"
                    class="size-full object-cover"
                    muted
                />
                <Badge
                    v-if="item.is_primary"
                    variant="outline"
                    class="absolute top-1.5 left-1.5 border-fl-gold/40 bg-fl-black/80 text-fl-gold-soft"
                >
                    Principal
                </Badge>
                <div
                    class="absolute inset-x-0 bottom-0 flex items-center justify-end gap-1 bg-fl-black/80 p-1.5 opacity-0 transition-opacity group-hover:opacity-100"
                >
                    <button
                        v-if="!item.is_primary"
                        type="button"
                        class="text-white/60 hover:text-fl-gold"
                        title="Hacer principal"
                        @click="setPrimaryMedia(item.id)"
                    >
                        <Star class="size-3.5" />
                    </button>
                    <button
                        type="button"
                        class="text-red-400 hover:text-red-300"
                        title="Eliminar"
                        @click="deleteMedia(item.id)"
                    >
                        <Trash2 class="size-3.5" />
                    </button>
                </div>
            </div>
            <p
                v-if="!media.length"
                class="col-span-full flex items-center gap-2 rounded-xl border border-dashed border-white/10 px-4 py-8 text-sm text-white/30"
            >
                <Image class="size-4" />
                Sin imágenes o video todavía — se usa Product.image_path como
                respaldo en la tienda.
            </p>
        </div>

        <!-- Contenido -->
        <div class="mt-10 mb-4 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-white/70 uppercase">
                Contenido (cómo funciona, características, FAQ…)
            </h2>
            <Button
                size="sm"
                class="bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
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
                class="flex items-start justify-between gap-3 rounded-xl border border-white/10 bg-fl-graphite/30 p-4"
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
                    <p class="mt-1 font-medium text-white">
                        {{ section.title }}
                    </p>
                </div>
                <button
                    type="button"
                    class="shrink-0 text-red-400 hover:text-red-300"
                    @click="deleteSection(section.id)"
                >
                    <Trash2 class="size-4" />
                </button>
            </div>
            <p
                v-if="!contentSections.length"
                class="rounded-xl border border-dashed border-white/10 px-4 py-8 text-center text-sm text-white/30"
            >
                Sin secciones todavía.
            </p>
        </div>

        <Dialog v-model:open="dialogOpen">
            <DialogContent
                class="dark border-white/10 bg-fl-graphite text-white sm:max-w-md"
            >
                <DialogHeader>
                    <DialogTitle>Nueva variante</DialogTitle>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitVariant">
                    <div class="grid gap-2">
                        <Label>SKU</Label>
                        <Input
                            v-model="form.sku"
                            class="bg-fl-black"
                            required
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label>Nombre (talla / color / etc.)</Label>
                        <Input
                            v-model="form.name"
                            class="bg-fl-black"
                            required
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label>Precio base (centavos)</Label>
                        <Input
                            v-model.number="form.base_price_minor"
                            type="number"
                            class="bg-fl-black"
                            placeholder="90000 = $900.00"
                            required
                        />
                    </div>
                    <label
                        class="flex items-center gap-2 text-sm text-white/70"
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
                            class="w-full bg-fl-gold text-fl-black hover:bg-fl-gold-soft sm:w-auto"
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
                class="dark border-white/10 bg-fl-graphite text-white sm:max-w-lg"
            >
                <DialogHeader>
                    <DialogTitle>Nueva sección de contenido</DialogTitle>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitSection">
                    <div class="grid gap-2">
                        <Label>Tipo</Label>
                        <Select v-model="sectionForm.type">
                            <SelectTrigger
                                class="border-white/10 bg-fl-black text-white"
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
                            class="bg-fl-black"
                            required
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label>Contenido</Label>
                        <p class="text-xs text-white/40">
                            {{ sectionTypeHelp[sectionForm.type] }}
                        </p>
                        <Textarea
                            v-model="sectionForm.body"
                            rows="6"
                            class="border-white/10 bg-fl-black text-white"
                        />
                    </div>
                    <DialogFooter>
                        <Button
                            type="submit"
                            class="w-full bg-fl-gold text-fl-black hover:bg-fl-gold-soft sm:w-auto"
                        >
                            Guardar sección
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
