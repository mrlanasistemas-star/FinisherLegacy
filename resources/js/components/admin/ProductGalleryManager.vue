<script setup lang="ts">
/**
 * Product photos, managed entirely from admin (ProductMedia on the
 * product_media disk — never base64, never hardcoded in the storefront):
 * drag & drop with previews before uploading, primary + hover image,
 * alt text, replace, drag-to-reorder (arrows as keyboard fallback), a
 * large preview and delete. While no photo exists and the store shows a
 * conceptual render, it says so here (admin only — never to customers). The storefront picks these up
 * automatically (primary → card/hero, hover → card hover, the rest →
 * gallery).
 */
import { router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    Box,
    GripVertical,
    ImagePlus,
    Maximize2,
    MousePointer2,
    RefreshCw,
    Star,
    Trash2,
    UploadCloud,
    X,
} from '@lucide/vue';
import { computed, onBeforeUnmount, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { confirmAction } from '@/lib/swal';

export type AdminProductMedia = {
    id: number;
    type: 'image' | 'video' | 'model_3d';
    url: string;
    poster_url: string | null;
    is_primary: boolean;
    is_hover: boolean;
    alt_text: string | null;
    sort_order: number;
    mime?: string | null;
    size?: number | null;
};

const props = withDefaults(
    defineProps<{
        productId: number;
        productName: string;
        media: AdminProductMedia[];
        /** Conceptual renders the store shows while there is no photo. */
        conceptGallery?: { url: string; alt: string }[];
    }>(),
    { conceptGallery: () => [] },
);

const MAX_FILES = 10;
const MAX_BYTES = 50 * 1024 * 1024;
const ACCEPT = [
    'image/jpeg',
    'image/png',
    'image/webp',
    'video/mp4',
    'video/webm',
];

// --- Upload with previews --------------------------------------------------

type Pending = { file: File; url: string };
const pending = ref<Pending[]>([]);
const rejected = ref<string[]>([]);
const dragging = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);

const uploadForm = useForm<{ files: File[]; alt_text: string }>({
    files: [],
    alt_text: '',
});

function addFiles(list: FileList | File[]) {
    rejected.value = [];

    for (const file of Array.from(list)) {
        if (pending.value.length >= MAX_FILES) {
            rejected.value.push(`${file.name}: máximo ${MAX_FILES} por carga`);
            continue;
        }

        if (!ACCEPT.includes(file.type)) {
            rejected.value.push(`${file.name}: formato no permitido`);
            continue;
        }

        if (file.size > MAX_BYTES) {
            rejected.value.push(`${file.name}: supera 50 MB`);
            continue;
        }

        pending.value.push({ file, url: URL.createObjectURL(file) });
    }
}

function onPick(event: Event) {
    const input = event.target as HTMLInputElement;
    addFiles(input.files ?? []);
    input.value = '';
}

function onDrop(event: DragEvent) {
    dragging.value = false;
    addFiles(event.dataTransfer?.files ?? []);
}

function removePending(index: number) {
    URL.revokeObjectURL(pending.value[index].url);
    pending.value.splice(index, 1);
}

function clearPending() {
    pending.value.forEach((p) => URL.revokeObjectURL(p.url));
    pending.value = [];
    uploadForm.reset();
}

function upload() {
    uploadForm.files = pending.value.map((p) => p.file);
    uploadForm.post(`/admin/products/${props.productId}/media`, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => clearPending(),
    });
}

onBeforeUnmount(() => pending.value.forEach((p) => URL.revokeObjectURL(p.url)));

// --- Existing media ----------------------------------------------------------

const ordered = computed(() =>
    [...props.media].sort((a, b) => a.sort_order - b.sort_order),
);

function setPrimary(item: AdminProductMedia) {
    router.post(
        `/admin/products/media/${item.id}/primary`,
        {},
        { preserveScroll: true },
    );
}

function toggleHover(item: AdminProductMedia) {
    router.patch(
        `/admin/products/media/${item.id}`,
        { alt_text: item.alt_text, is_hover: !item.is_hover },
        { preserveScroll: true },
    );
}

const editingAlt = ref<number | null>(null);
const altDraft = ref('');

function editAlt(item: AdminProductMedia) {
    editingAlt.value = item.id;
    altDraft.value = item.alt_text ?? '';
}

function saveAlt(item: AdminProductMedia) {
    router.patch(
        `/admin/products/media/${item.id}`,
        { alt_text: altDraft.value || null },
        {
            preserveScroll: true,
            onSuccess: () => (editingAlt.value = null),
        },
    );
}

function replace(item: AdminProductMedia, event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = '';

    if (!file) {
        return;
    }

    router.post(
        `/admin/products/media/${item.id}/replace`,
        { file },
        { preserveScroll: true, forceFormData: true },
    );
}

function saveOrder(ids: number[]) {
    router.post(
        `/admin/products/${props.productId}/media/reorder`,
        { order: ids },
        { preserveScroll: true },
    );
}

function move(item: AdminProductMedia, direction: -1 | 1) {
    const ids = ordered.value.map((m) => m.id);
    const index = ids.indexOf(item.id);
    const target = index + direction;

    if (target < 0 || target >= ids.length) {
        return;
    }

    [ids[index], ids[target]] = [ids[target], ids[index]];
    saveOrder(ids);
}

// Drag to reorder: drop a card on another to take its place.
const draggingId = ref<number | null>(null);
const overId = ref<number | null>(null);

function onCardDragStart(item: AdminProductMedia, event: DragEvent) {
    draggingId.value = item.id;
    event.dataTransfer?.setData('text/plain', String(item.id));

    if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = 'move';
    }
}

function onCardDrop(target: AdminProductMedia) {
    const from = draggingId.value;
    draggingId.value = null;
    overId.value = null;

    if (from === null || from === target.id) {
        return;
    }

    const ids = ordered.value.map((m) => m.id).filter((id) => id !== from);
    ids.splice(
        ids.indexOf(target.id) +
            (orderIndex(from) < orderIndex(target.id) ? 1 : 0),
        0,
        from,
    );
    saveOrder(ids);
}

const orderIndex = (id: number) => ordered.value.findIndex((m) => m.id === id);

// Large preview
const previewItem = ref<AdminProductMedia | null>(null);

async function remove(item: AdminProductMedia) {
    const confirmed = await confirmAction({
        title: '¿Eliminar este archivo?',
        text: 'Se borrará de la galería y del almacenamiento.',
        confirmButtonText: 'Eliminar',
    });

    if (confirmed) {
        router.delete(`/admin/products/media/${item.id}`, {
            preserveScroll: true,
        });
    }
}

// --- 3D model (GLB/GLTF) + posters (spec §22-§23) ---------------------------

const MAX_MODEL_BYTES = 30 * 1024 * 1024;
const modelForm = useForm<{
    model: File | null;
    poster: File | null;
    alt_text: string;
}>({
    model: null,
    poster: null,
    alt_text: '',
});
const modelError = ref<string | null>(null);

function onModelPick(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;
    input.value = '';
    modelError.value = null;

    if (!file) {
        return;
    }

    if (!/\.(glb|gltf)$/i.test(file.name)) {
        modelError.value = 'El modelo debe ser .glb o .gltf.';

        return;
    }

    if (file.size > MAX_MODEL_BYTES) {
        modelError.value =
            'El modelo supera 30 MB; optimízalo antes de subirlo.';

        return;
    }

    modelForm.model = file;
}

function onModelPosterPick(event: Event) {
    const input = event.target as HTMLInputElement;
    modelForm.poster = input.files?.[0] ?? null;
    input.value = '';
}

function uploadModel() {
    modelForm.post(`/admin/products/${props.productId}/media`, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => modelForm.reset(),
    });
}

function setPoster(item: AdminProductMedia, event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = '';

    if (!file) {
        return;
    }

    router.post(
        `/admin/products/media/${item.id}/poster`,
        { poster: file },
        { preserveScroll: true, forceFormData: true },
    );
}

function formatSize(bytes?: number | null) {
    if (!bytes) {
        return '';
    }

    return bytes > 1024 * 1024
        ? `${(bytes / 1024 / 1024).toFixed(1)} MB`
        : `${Math.round(bytes / 1024)} KB`;
}
</script>

<template>
    <section class="space-y-5">
        <!-- Drop zone -->
        <div
            class="rounded-xl border-2 border-dashed p-6 text-center transition-colors"
            :class="
                dragging
                    ? 'border-fl-gold bg-fl-cream/60'
                    : 'border-foreground/15 bg-card'
            "
            @dragover.prevent="dragging = true"
            @dragleave.prevent="dragging = false"
            @drop.prevent="onDrop"
        >
            <UploadCloud class="mx-auto size-7 text-fl-gold-ink" />
            <p class="mt-3 text-sm font-medium">
                Arrastra fotos aquí o
                <button
                    type="button"
                    class="underline underline-offset-4"
                    @click="fileInput?.click()"
                >
                    selecciónalas
                </button>
            </p>
            <p class="mt-1 text-xs text-muted-foreground">
                JPG, JPEG, PNG o WEBP (también MP4/WEBM) · hasta {{ MAX_FILES }}
                por carga · recomendado ≥ 1600 px, formato vertical 4:5 o
                cuadrado.
            </p>
            <input
                ref="fileInput"
                type="file"
                :accept="ACCEPT.join(',')"
                multiple
                class="sr-only"
                aria-label="Seleccionar archivos para la galería"
                @change="onPick"
            />
        </div>

        <ul
            v-if="rejected.length"
            class="space-y-1 rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-700"
        >
            <li v-for="message in rejected" :key="message">{{ message }}</li>
        </ul>

        <!-- 3D model -->
        <div
            class="flex flex-col gap-3 rounded-xl border border-border bg-card p-4 sm:flex-row sm:items-center"
        >
            <Box class="size-6 shrink-0 text-fl-gold-ink" />
            <div class="min-w-0 flex-1">
                <p class="text-sm font-medium">Modelo 3D</p>
                <p class="text-xs text-muted-foreground">
                    GLB o GLTF, hasta 30 MB (ideal &lt; 5 MB). Agrega un póster:
                    es lo que se ve mientras el modelo carga.
                </p>
                <p v-if="modelForm.model" class="mt-1 truncate text-xs">
                    {{ modelForm.model.name }} ·
                    {{ formatSize(modelForm.model.size) }}
                    <template v-if="modelForm.poster">
                        · póster: {{ modelForm.poster.name }}</template
                    >
                </p>
                <p v-if="modelError" class="mt-1 text-xs text-red-700">
                    {{ modelError }}
                </p>
                <p
                    v-if="Object.keys(modelForm.errors).length"
                    class="mt-1 text-xs text-red-700"
                >
                    {{ Object.values(modelForm.errors)[0] }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <label
                    class="inline-flex h-10 cursor-pointer items-center gap-1.5 rounded-full border border-border px-4 text-sm font-medium hover:bg-muted"
                >
                    Elegir modelo
                    <input
                        type="file"
                        accept=".glb,.gltf,model/gltf-binary,model/gltf+json"
                        class="sr-only"
                        @change="onModelPick"
                    />
                </label>
                <label
                    v-if="modelForm.model"
                    class="inline-flex h-10 cursor-pointer items-center gap-1.5 rounded-full border border-border px-4 text-sm font-medium hover:bg-muted"
                >
                    Póster
                    <input
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        class="sr-only"
                        @change="onModelPosterPick"
                    />
                </label>
                <Button
                    v-if="modelForm.model"
                    class="h-10 rounded-full"
                    :disabled="modelForm.processing"
                    @click="uploadModel"
                >
                    {{
                        modelForm.processing
                            ? `Subiendo… ${modelForm.progress?.percentage ?? 0}%`
                            : 'Subir modelo'
                    }}
                </Button>
            </div>
        </div>

        <!-- Previews before upload -->
        <div
            v-if="pending.length"
            class="space-y-4 rounded-xl border border-border bg-card p-4"
        >
            <p class="text-sm font-semibold">
                {{ pending.length }} archivo{{
                    pending.length === 1 ? '' : 's'
                }}
                listo{{ pending.length === 1 ? '' : 's' }} para subir
            </p>
            <div class="grid grid-cols-3 gap-3 sm:grid-cols-5 lg:grid-cols-6">
                <div
                    v-for="(item, index) in pending"
                    :key="item.url"
                    class="relative aspect-square overflow-hidden rounded-lg bg-muted"
                >
                    <img
                        v-if="item.file.type.startsWith('image/')"
                        :src="item.url"
                        alt=""
                        class="size-full object-cover"
                    />
                    <video
                        v-else
                        :src="item.url"
                        muted
                        class="size-full object-cover"
                    />
                    <button
                        type="button"
                        class="absolute top-1 right-1 flex size-6 items-center justify-center rounded-full bg-black/60 text-white"
                        :aria-label="`Quitar ${item.file.name}`"
                        @click="removePending(index)"
                    >
                        <X class="size-3.5" />
                    </button>
                </div>
            </div>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="grid flex-1 gap-1.5">
                    <label for="batch-alt" class="text-xs font-medium"
                        >Texto alternativo (opcional, para todas)</label
                    >
                    <Input
                        id="batch-alt"
                        v-model="uploadForm.alt_text"
                        maxlength="150"
                        :placeholder="`${productName} — vista frontal`"
                    />
                </div>
                <div class="flex gap-2">
                    <Button variant="ghost" @click="clearPending"
                        >Cancelar</Button
                    >
                    <Button :disabled="uploadForm.processing" @click="upload">
                        <ImagePlus class="size-4" />
                        {{
                            uploadForm.processing
                                ? `Subiendo… ${uploadForm.progress?.percentage ?? 0}%`
                                : 'Subir a la galería'
                        }}
                    </Button>
                </div>
            </div>
            <p
                v-if="Object.keys(uploadForm.errors).length"
                class="text-xs text-red-700"
            >
                {{ Object.values(uploadForm.errors)[0] }}
            </p>
        </div>

        <!-- Conceptual render in use (admin only) -->
        <div
            v-if="!ordered.length && conceptGallery.length"
            class="flex flex-col gap-4 rounded-xl border border-violet-200 bg-violet-50/60 p-4 sm:flex-row sm:items-center"
        >
            <div class="flex shrink-0 gap-2">
                <img
                    v-for="concept in conceptGallery.slice(0, 3)"
                    :key="concept.url"
                    :src="concept.url"
                    :alt="concept.alt"
                    loading="lazy"
                    class="size-16 rounded-lg border border-white object-cover shadow-sm"
                />
            </div>
            <div>
                <p class="text-sm font-semibold text-violet-800">
                    Imagen conceptual
                </p>
                <p class="text-sm text-violet-900/80">
                    Sube una fotografía real para reemplazarla. Mientras tanto
                    la tienda muestra {{ conceptGallery.length }}
                    {{ conceptGallery.length === 1 ? 'render' : 'renders' }} de
                    producto.
                </p>
            </div>
        </div>

        <!-- Gallery -->
        <p
            v-if="ordered.length > 1"
            class="flex items-center gap-1.5 text-xs text-muted-foreground"
        >
            <GripVertical class="size-3.5" /> Arrastra las tarjetas para ordenar
            la galería. La primera imagen marcada como principal es la portada.
        </p>
        <div
            v-if="ordered.length"
            class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5"
        >
            <article
                v-for="(item, index) in ordered"
                :key="item.id"
                class="overflow-hidden rounded-xl border bg-card transition-[opacity,box-shadow,transform]"
                :class="[
                    item.is_primary ? 'border-fl-gold' : 'border-border',
                    draggingId === item.id ? 'opacity-40' : '',
                    overId === item.id && draggingId !== item.id
                        ? 'ring-2 ring-fl-gold ring-offset-2'
                        : '',
                ]"
                draggable="true"
                @dragstart="onCardDragStart(item, $event)"
                @dragend="
                    draggingId = null;
                    overId = null;
                "
                @dragover.prevent="overId = item.id"
                @drop.prevent="onCardDrop(item)"
            >
                <div class="group relative aspect-square cursor-grab bg-muted">
                    <img
                        v-if="item.type === 'image'"
                        :src="item.url"
                        :alt="item.alt_text ?? ''"
                        loading="lazy"
                        class="size-full object-cover"
                    />
                    <template v-else-if="item.type === 'model_3d'">
                        <img
                            v-if="item.poster_url"
                            :src="item.poster_url"
                            :alt="item.alt_text ?? ''"
                            loading="lazy"
                            class="size-full object-cover"
                        />
                        <div
                            v-else
                            class="flex size-full flex-col items-center justify-center gap-2 text-muted-foreground"
                        >
                            <Box class="size-10" />
                            <span class="text-xs">Sin póster</span>
                        </div>
                    </template>
                    <video
                        v-else
                        :src="item.url"
                        :poster="item.poster_url ?? undefined"
                        muted
                        preload="metadata"
                        class="size-full object-cover"
                    />
                    <div class="absolute top-2 left-2 flex flex-wrap gap-1">
                        <span
                            v-if="item.is_primary"
                            class="rounded-full bg-foreground px-2 py-0.5 text-[10px] font-semibold text-background"
                            >Principal</span
                        >
                        <span
                            v-if="item.is_hover"
                            class="rounded-full bg-white/95 px-2 py-0.5 text-[10px] font-semibold"
                            >Hover</span
                        >
                        <span
                            v-if="item.type === 'video'"
                            class="rounded-full bg-white/95 px-2 py-0.5 text-[10px] font-semibold"
                            >Video</span
                        >
                        <span
                            v-if="item.type === 'model_3d'"
                            class="rounded-full bg-white/95 px-2 py-0.5 text-[10px] font-semibold"
                            >3D</span
                        >
                    </div>
                    <span
                        class="legacy-numeric absolute right-2 bottom-2 rounded-full bg-white/90 px-2 py-0.5 text-[10px] font-medium"
                        >#{{ index + 1 }}</span
                    >
                    <button
                        type="button"
                        class="absolute top-2 right-2 flex size-8 items-center justify-center rounded-full bg-white/95 opacity-100 shadow-sm transition-opacity focus-visible:opacity-100 sm:opacity-0 sm:group-hover:opacity-100"
                        aria-label="Ver en grande"
                        @click="previewItem = item"
                    >
                        <Maximize2 class="size-3.5" />
                    </button>
                </div>

                <div class="space-y-3 p-3">
                    <div v-if="editingAlt === item.id" class="flex gap-2">
                        <Input
                            v-model="altDraft"
                            maxlength="150"
                            class="h-8 text-xs"
                            aria-label="Texto alternativo"
                            @keyup.enter="saveAlt(item)"
                        />
                        <Button size="sm" class="h-8" @click="saveAlt(item)"
                            >Guardar</Button
                        >
                    </div>
                    <button
                        v-else
                        type="button"
                        class="block w-full truncate text-left text-xs"
                        :class="
                            item.alt_text
                                ? 'text-foreground'
                                : 'text-muted-foreground italic'
                        "
                        @click="editAlt(item)"
                    >
                        {{ item.alt_text || 'Agregar texto alternativo…' }}
                    </button>
                    <p
                        v-if="item.size"
                        class="text-[11px] text-muted-foreground"
                    >
                        {{ item.mime }} · {{ formatSize(item.size) }}
                    </p>

                    <div class="flex flex-wrap items-center gap-1">
                        <Button
                            v-if="!item.is_primary && item.type === 'image'"
                            variant="outline"
                            size="sm"
                            class="h-7 px-2 text-xs"
                            @click="setPrimary(item)"
                        >
                            <Star class="size-3" />
                            Principal
                        </Button>
                        <Button
                            v-if="!item.is_primary && item.type === 'image'"
                            variant="outline"
                            size="sm"
                            class="h-7 px-2 text-xs"
                            :aria-pressed="item.is_hover"
                            @click="toggleHover(item)"
                        >
                            <MousePointer2 class="size-3" />
                            {{ item.is_hover ? 'Quitar hover' : 'Hover' }}
                        </Button>
                        <label
                            v-if="item.type !== 'image'"
                            class="inline-flex h-7 cursor-pointer items-center gap-1 rounded-md border border-border px-2 text-xs font-medium hover:bg-muted"
                        >
                            <ImagePlus class="size-3" />
                            Póster
                            <input
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                class="sr-only"
                                @change="setPoster(item, $event)"
                            />
                        </label>
                        <label
                            v-if="item.type !== 'model_3d'"
                            class="inline-flex h-7 cursor-pointer items-center gap-1 rounded-md border border-border px-2 text-xs font-medium hover:bg-muted"
                        >
                            <RefreshCw class="size-3" />
                            Reemplazar
                            <input
                                type="file"
                                :accept="ACCEPT.join(',')"
                                class="sr-only"
                                @change="replace(item, $event)"
                            />
                        </label>
                        <div class="ml-auto flex">
                            <button
                                type="button"
                                class="flex size-7 items-center justify-center rounded-md text-muted-foreground hover:bg-muted disabled:opacity-30"
                                :disabled="index === 0"
                                aria-label="Mover antes"
                                @click="move(item, -1)"
                            >
                                <ArrowLeft class="size-3.5" />
                            </button>
                            <button
                                type="button"
                                class="flex size-7 items-center justify-center rounded-md text-muted-foreground hover:bg-muted disabled:opacity-30"
                                :disabled="index === ordered.length - 1"
                                aria-label="Mover después"
                                @click="move(item, 1)"
                            >
                                <ArrowRight class="size-3.5" />
                            </button>
                            <button
                                type="button"
                                class="flex size-7 items-center justify-center rounded-md text-red-700 hover:bg-red-50"
                                aria-label="Eliminar"
                                @click="remove(item)"
                            >
                                <Trash2 class="size-3.5" />
                            </button>
                        </div>
                    </div>
                </div>
            </article>
        </div>
        <p
            v-else-if="!conceptGallery.length"
            class="rounded-xl border border-dashed border-border px-4 py-6 text-sm text-muted-foreground"
        >
            Sin fotografías todavía — la tienda muestra un placeholder de marca
            hasta que subas la primera.
        </p>

        <Dialog
            :open="previewItem !== null"
            @update:open="(v) => !v && (previewItem = null)"
        >
            <DialogContent class="max-w-4xl p-2 sm:p-3">
                <DialogTitle class="sr-only">Vista previa</DialogTitle>
                <template v-if="previewItem">
                    <img
                        v-if="previewItem.type === 'image'"
                        :src="previewItem.url"
                        :alt="previewItem.alt_text ?? productName"
                        class="max-h-[80vh] w-full rounded-lg object-contain"
                    />
                    <div
                        v-else-if="previewItem.type === 'model_3d'"
                        class="flex flex-col items-center gap-3 p-4"
                    >
                        <img
                            v-if="previewItem.poster_url"
                            :src="previewItem.poster_url"
                            :alt="previewItem.alt_text ?? productName"
                            class="max-h-[60vh] rounded-lg object-contain"
                        />
                        <a
                            :href="previewItem.url"
                            download
                            class="text-sm font-medium text-fl-gold-ink underline-offset-4 hover:underline"
                            >Descargar modelo 3D</a
                        >
                    </div>
                    <video
                        v-else
                        :src="previewItem.url"
                        :poster="previewItem.poster_url ?? undefined"
                        controls
                        class="max-h-[80vh] w-full rounded-lg"
                    />
                    <p class="px-2 pt-2 text-xs text-muted-foreground">
                        {{ previewItem.alt_text || 'Sin texto alternativo' }}
                        <template v-if="previewItem.size">
                            · {{ formatSize(previewItem.size) }}</template
                        >
                    </p>
                </template>
            </DialogContent>
        </Dialog>
    </section>
</template>
