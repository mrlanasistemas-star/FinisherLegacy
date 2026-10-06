<script setup lang="ts">
/**
 * Product photos, managed entirely from admin (ProductMedia on the
 * product_media disk — never base64, never hardcoded in the storefront):
 * drag & drop with previews before uploading, primary + hover image,
 * alt text, replace, reorder and delete. The storefront picks these up
 * automatically (primary → card/hero, hover → card hover, the rest →
 * gallery).
 */
import { router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    ImagePlus,
    MousePointer2,
    RefreshCw,
    Star,
    Trash2,
    UploadCloud,
    X,
} from '@lucide/vue';
import { computed, onBeforeUnmount, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { confirmAction } from '@/lib/swal';

export type AdminProductMedia = {
    id: number;
    type: 'image' | 'video';
    url: string;
    poster_url: string | null;
    is_primary: boolean;
    is_hover: boolean;
    alt_text: string | null;
    sort_order: number;
    mime?: string | null;
    size?: number | null;
};

const props = defineProps<{
    productId: number;
    productName: string;
    media: AdminProductMedia[];
}>();

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

function move(item: AdminProductMedia, direction: -1 | 1) {
    const ids = ordered.value.map((m) => m.id);
    const index = ids.indexOf(item.id);
    const target = index + direction;

    if (target < 0 || target >= ids.length) {
        return;
    }

    [ids[index], ids[target]] = [ids[target], ids[index]];
    router.post(
        `/admin/products/${props.productId}/media/reorder`,
        { order: ids },
        { preserveScroll: true },
    );
}

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

        <!-- Gallery -->
        <div
            v-if="ordered.length"
            class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
        >
            <article
                v-for="(item, index) in ordered"
                :key="item.id"
                class="overflow-hidden rounded-xl border bg-card"
                :class="item.is_primary ? 'border-fl-gold' : 'border-border'"
            >
                <div class="relative aspect-square bg-muted">
                    <img
                        v-if="item.type === 'image'"
                        :src="item.url"
                        :alt="item.alt_text ?? ''"
                        loading="lazy"
                        class="size-full object-cover"
                    />
                    <video
                        v-else
                        :src="item.url"
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
                    </div>
                    <span
                        class="legacy-numeric absolute right-2 bottom-2 rounded-full bg-white/90 px-2 py-0.5 text-[10px] font-medium"
                        >#{{ index + 1 }}</span
                    >
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
            v-else
            class="rounded-xl border border-dashed border-border px-4 py-6 text-sm text-muted-foreground"
        >
            Sin fotografías todavía — la tienda muestra un placeholder de marca
            hasta que subas la primera.
        </p>
    </section>
</template>
