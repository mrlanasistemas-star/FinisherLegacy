<script setup lang="ts">
/**
 * Diseños extra del tag de una colección (`collection_designs`), dentro de
 * la edición de la colección: lista, alta, edición, orden (flechas),
 * archivar/restaurar y eliminar (solo si ningún producto lo usa). El arte
 * del frente se sube igual que `tag_art` (JPG/PNG/WEBP, máx. 5 MB) y la
 * vista previa frente/reverso usa TagVisualizer/TagFlipCard con las líneas
 * de la colección (nombre, disciplina, reverso).
 */
import { router, useForm } from '@inertiajs/vue3';
import {
    Archive,
    ArchiveRestore,
    ArrowDown,
    ArrowUp,
    ImagePlus,
    Pencil,
    Plus,
    Trash2,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import {
    archive,
    destroy,
    reorder,
    store,
    update,
} from '@/actions/App/Http/Controllers/Admin/Catalog/CollectionDesignController';
import ActionDialog from '@/components/admin/catalog/ActionDialog.vue';
import InputError from '@/components/InputError.vue';
import TagFlipCard from '@/components/tag/TagFlipCard.vue';
import TagVisualizer from '@/components/tag/TagVisualizer.vue';
import type { TagArt } from '@/components/three/tagStageContext';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

export type CollectionDesignRow = {
    id: number;
    slug: string;
    name: string;
    accent: string | null;
    sort_order: number;
    front_art_url: string | null;
    archived: boolean;
    products_count: number;
};

const props = defineProps<{
    collectionId: number;
    designs: CollectionDesignRow[];
    /** The collection's primary design (lines shared by every front). */
    base: TagArt;
}>();

const editing = ref<CollectionDesignRow | 'new' | null>(null);
const artPreview = ref<string | null>(null);

const form = useForm({
    name: '',
    slug: '',
    accent: '',
    art: null as File | null,
    remove_art: false,
});

function openNew() {
    form.reset();
    form.clearErrors();
    artPreview.value = null;
    editing.value = 'new';
}

function openEdit(design: CollectionDesignRow) {
    form.clearErrors();
    form.name = design.name;
    form.slug = design.slug;
    form.accent = design.accent ?? '';
    form.art = null;
    form.remove_art = false;
    artPreview.value = design.front_art_url;
    editing.value = design;
}

function close() {
    editing.value = null;
}

function pickArt(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    form.art = file;
    form.remove_art = false;
    artPreview.value = file ? URL.createObjectURL(file) : null;
}

function clearArt() {
    form.art = null;
    form.remove_art = true;
    artPreview.value = null;
}

function save() {
    const target = editing.value;

    if (target === null) {
        return;
    }

    const url =
        target === 'new'
            ? store(props.collectionId).url
            : update({ collection: props.collectionId, design: target.id }).url;

    form.post(url, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => close(),
    });
}

function artFor(design: {
    name: string;
    accent: string | null;
    front_art_url: string | null;
}): TagArt {
    return {
        ...props.base,
        accent: design.accent || props.base.accent,
        frontArt: design.front_art_url || props.base.frontArt,
    };
}

const editorPreview = computed(() =>
    artFor({
        name: form.name,
        accent: form.accent || null,
        front_art_url: artPreview.value,
    }),
);

// Reorder with arrows: persists the full id list.
const busy = ref(false);

function move(index: number, delta: -1 | 1) {
    const ids = props.designs.map((d) => d.id);
    const target = index + delta;

    if (target < 0 || target >= ids.length) {
        return;
    }

    [ids[index], ids[target]] = [ids[target], ids[index]];
    router.post(
        reorder(props.collectionId).url,
        { ids },
        {
            preserveScroll: true,
            onStart: () => (busy.value = true),
            onFinish: () => (busy.value = false),
        },
    );
}

function toggleArchive(design: CollectionDesignRow) {
    router.post(
        archive({ collection: props.collectionId, design: design.id }).url,
        { archived: !design.archived },
        { preserveScroll: true },
    );
}

const deleting = ref<CollectionDesignRow | null>(null);
const deleteOpen = computed({
    get: () => deleting.value !== null,
    set: (open: boolean) => {
        if (!open) {
            deleting.value = null;
        }
    },
});
const deleteProcessing = ref(false);

function confirmDelete() {
    const design = deleting.value;

    if (design === null) {
        return;
    }

    router.delete(
        destroy({ collection: props.collectionId, design: design.id }).url,
        {
            preserveScroll: true,
            onStart: () => (deleteProcessing.value = true),
            onFinish: () => {
                deleteProcessing.value = false;
                deleting.value = null;
            },
        },
    );
}
</script>

<template>
    <section class="fl-card grid gap-5 p-5 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2
                    class="text-sm font-semibold text-muted-foreground uppercase"
                >
                    Diseños extra del tag
                </h2>
                <p class="text-xs text-muted-foreground">
                    Frentes alternativos de esta colección. El diseño principal
                    es el de arriba; cada producto tag puede elegir uno de estos
                    desde su ficha.
                </p>
            </div>
            <Button
                type="button"
                variant="outline"
                class="rounded-full"
                @click="openNew"
            >
                <Plus class="size-4" />
                Nuevo diseño
            </Button>
        </div>

        <p
            v-if="designs.length === 0 && editing === null"
            class="rounded-xl border border-dashed border-border p-4 text-sm text-muted-foreground"
        >
            Esta colección solo tiene su diseño principal.
        </p>

        <ul v-if="designs.length" class="grid gap-3">
            <li
                v-for="(design, i) in designs"
                :key="design.id"
                class="flex flex-wrap items-center gap-4 rounded-xl border border-border p-3"
                :class="{ 'opacity-60': design.archived }"
            >
                <div class="w-16 shrink-0">
                    <TagFlipCard
                        :art="artFor(design)"
                        :button="false"
                        :hover="true"
                    />
                </div>
                <div class="min-w-0 flex-1">
                    <p class="flex items-center gap-2 font-medium">
                        <span
                            v-if="design.accent"
                            class="size-3 rounded-full border border-border"
                            :style="{ backgroundColor: design.accent }"
                            aria-hidden="true"
                        />
                        {{ design.name }}
                        <span
                            v-if="design.archived"
                            class="rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground"
                            >Archivado</span
                        >
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ design.slug }} ·
                        {{
                            design.products_count === 1
                                ? '1 producto'
                                : `${design.products_count} productos`
                        }}
                    </p>
                </div>
                <div class="flex items-center gap-1">
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        :disabled="busy || i === 0"
                        :aria-label="`Subir ${design.name}`"
                        @click="move(i, -1)"
                    >
                        <ArrowUp class="size-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        :disabled="busy || i === designs.length - 1"
                        :aria-label="`Bajar ${design.name}`"
                        @click="move(i, 1)"
                    >
                        <ArrowDown class="size-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        :aria-label="`Editar ${design.name}`"
                        @click="openEdit(design)"
                    >
                        <Pencil class="size-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        :aria-label="
                            design.archived
                                ? `Restaurar ${design.name}`
                                : `Archivar ${design.name}`
                        "
                        @click="toggleArchive(design)"
                    >
                        <ArchiveRestore v-if="design.archived" class="size-4" />
                        <Archive v-else class="size-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        :disabled="design.products_count > 0"
                        :title="
                            design.products_count > 0
                                ? 'Hay productos que lo usan: archívalo'
                                : undefined
                        "
                        :aria-label="`Eliminar ${design.name}`"
                        @click="deleting = design"
                    >
                        <Trash2 class="size-4" />
                    </Button>
                </div>
            </li>
        </ul>

        <form
            v-if="editing !== null"
            class="grid gap-5 rounded-xl border border-border bg-fl-cream/30 p-4 md:grid-cols-[1fr_auto]"
            @submit.prevent="save"
        >
            <div class="grid content-start gap-4 md:grid-cols-2">
                <h3 class="font-semibold md:col-span-2">
                    {{
                        editing === 'new'
                            ? 'Nuevo diseño'
                            : `Editar ${editing.name}`
                    }}
                </h3>
                <div class="grid gap-1.5">
                    <Label for="d-name">Nombre *</Label>
                    <Input
                        id="d-name"
                        v-model="form.name"
                        required
                        maxlength="120"
                        placeholder="Arrecife, mapache…"
                    />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="d-slug">Slug</Label>
                    <Input
                        id="d-slug"
                        v-model="form.slug"
                        maxlength="80"
                        placeholder="se-genera-del-nombre"
                    />
                    <InputError :message="form.errors.slug" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="d-accent">Acento</Label>
                    <div class="flex items-center gap-2">
                        <input
                            type="color"
                            :value="form.accent || '#c9ae8b'"
                            aria-label="Elegir acento del diseño"
                            class="size-10 shrink-0 cursor-pointer rounded-md border border-input bg-card p-1"
                            @input="
                                form.accent = (
                                    $event.target as HTMLInputElement
                                ).value
                            "
                        />
                        <Input
                            id="d-accent"
                            v-model="form.accent"
                            placeholder="Usa el de la colección"
                            maxlength="7"
                        />
                    </div>
                    <InputError :message="form.errors.accent" />
                </div>
                <div class="grid gap-1.5 md:col-span-2">
                    <Label>Arte del frente · JPG, PNG o WEBP · máx. 5 MB</Label>
                    <div
                        class="relative flex h-36 items-center justify-center overflow-hidden rounded-xl border border-dashed border-border bg-card"
                    >
                        <img
                            v-if="artPreview"
                            :src="artPreview"
                            alt=""
                            class="h-full object-contain"
                        />
                        <label
                            v-else
                            class="flex cursor-pointer flex-col items-center gap-2 text-sm text-muted-foreground"
                        >
                            <ImagePlus class="size-6 text-fl-gold-ink" />
                            Ilustración (vertical, sin texto)
                            <input
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                class="sr-only"
                                @change="pickArt"
                            />
                        </label>
                        <button
                            v-if="artPreview"
                            type="button"
                            class="absolute top-2 right-2 flex size-9 items-center justify-center rounded-full bg-card/90 shadow"
                            aria-label="Quitar arte del diseño"
                            @click="clearArt"
                        >
                            <X class="size-4" />
                        </button>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Sin arte propio se usa el de la colección.
                    </p>
                    <InputError :message="form.errors.art" />
                </div>
                <div class="flex justify-end gap-2 md:col-span-2">
                    <Button
                        type="button"
                        variant="outline"
                        class="rounded-full"
                        @click="close"
                        >Cancelar</Button
                    >
                    <Button
                        type="submit"
                        class="rounded-full"
                        :disabled="form.processing"
                    >
                        <Spinner v-if="form.processing" class="size-4" />
                        {{
                            editing === 'new'
                                ? 'Agregar diseño'
                                : 'Guardar diseño'
                        }}
                    </Button>
                </div>
            </div>
            <div
                class="flex justify-center gap-3 md:w-64"
                aria-label="Vista previa del diseño"
            >
                <TagVisualizer
                    v-bind="editorPreview"
                    class="w-1/2"
                    :tilt="false"
                />
                <TagVisualizer
                    v-bind="editorPreview"
                    face="back"
                    class="w-1/2"
                    :tilt="false"
                />
            </div>
        </form>

        <ActionDialog
            v-model:open="deleteOpen"
            title="Eliminar diseño"
            :description="`Se eliminará «${deleting?.name ?? ''}» junto con su arte. Esta acción no se puede deshacer.`"
            confirm-label="Eliminar"
            destructive
            :processing="deleteProcessing"
            @confirm="confirmDelete"
        />
    </section>
</template>
