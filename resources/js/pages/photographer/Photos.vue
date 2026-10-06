<script setup lang="ts">
/**
 * Portal del fotógrafo — sus fotos en venta. Batch upload (drag & drop
 * with previews) for one event, one price and the bib numbers that
 * appear; then a visual grid to edit price/bibs, filter by status and
 * delete unsold photos.
 */
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    CheckCircle2,
    Clock,
    Flag,
    Hash,
    ImagePlus,
    Pencil,
    Tag,
    Trash2,
    UploadCloud,
    X,
    XCircle,
} from '@lucide/vue';
import { computed, onBeforeUnmount, ref } from 'vue';
import FancySelect from '@/components/forms/FancySelect.vue';
import InputError from '@/components/InputError.vue';
import FeeBreakdown from '@/components/photos/FeeBreakdown.vue';
import Pagination from '@/components/public/Pagination.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { PhotoFeeRules } from '@/lib/photoFees';
import { confirmAction } from '@/lib/swal';

type Photo = {
    uuid: string;
    thumb_url: string;
    preview_url: string;
    event: string | null;
    price_minor: number;
    currency: string;
    status: 'review' | 'published' | 'rejected';
    rejection_reason: string | null;
    bib_numbers: string[];
    sales_count: number;
    net_minor: number;
};

const props = defineProps<{
    profile: {
        status: string;
        default_price_minor: number;
        display_name: string;
    };
    photos: {
        data: Photo[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    events: { id: number; label: string; date: string | null }[];
    filters: { evento: number | null; estado: string | null };
    fees: PhotoFeeRules;
    limits: { max_files: number; max_file_kb: number };
}>();

const money = (minor: number) =>
    new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
    }).format(minor / 100);

const approved = computed(() => props.profile.status === 'approved');

// --- Upload --------------------------------------------------------------

const eventOptions = computed(() =>
    props.events.map((e) => ({
        value: e.id,
        label: e.label,
        description: e.date ?? undefined,
        icon: Flag,
    })),
);

const price = ref(props.profile.default_price_minor / 100);
const form = useForm({
    event_edition_id: (props.filters.evento ?? null) as string | number | null,
    price_minor: props.profile.default_price_minor,
    bibs: '',
    files: [] as File[],
});
const previews = ref<string[]>([]);
const dragging = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);

function addFiles(list: FileList | File[]) {
    const room = props.limits.max_files - form.files.length;
    const accepted = Array.from(list)
        .filter((f) =>
            ['image/jpeg', 'image/png', 'image/webp'].includes(f.type),
        )
        .slice(0, Math.max(room, 0));
    form.files = [...form.files, ...accepted];
    previews.value = [
        ...previews.value,
        ...accepted.map((f) => URL.createObjectURL(f)),
    ];
}

function removeFile(index: number) {
    URL.revokeObjectURL(previews.value[index]);
    form.files = form.files.filter((_, i) => i !== index);
    previews.value = previews.value.filter((_, i) => i !== index);
}

function clearFiles() {
    previews.value.forEach((u) => URL.revokeObjectURL(u));
    previews.value = [];
    form.files = [];
}

onBeforeUnmount(clearFiles);

function upload() {
    form.price_minor = Math.round((Number(price.value) || 0) * 100);
    form.post('/fotografo/fotos', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            clearFiles();
            form.reset('bibs');
        },
    });
}

// --- Filters -------------------------------------------------------------

const statusTabs = [
    { value: null, label: 'Todas', icon: ImagePlus },
    { value: 'review', label: 'Por revisar', icon: Clock },
    { value: 'published', label: 'Publicadas', icon: CheckCircle2 },
    { value: 'rejected', label: 'Rechazadas', icon: XCircle },
];

function filter(next: {
    evento?: string | number | null;
    estado?: string | null;
}) {
    const params = {
        evento: props.filters.evento,
        estado: props.filters.estado,
        ...next,
    };
    router.get(
        '/fotografo/fotos',
        Object.fromEntries(
            Object.entries(params).filter(
                ([, v]) => v !== null && v !== undefined && v !== '',
            ),
        ),
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

const statusChip: Record<string, string> = {
    review: 'bg-amber-50 text-amber-700 border-amber-200',
    published: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    rejected: 'bg-red-50 text-red-700 border-red-200',
};
const statusLabel: Record<string, string> = {
    review: 'Por revisar',
    published: 'Publicada',
    rejected: 'Rechazada',
};

// --- Edit ------------------------------------------------------------------

const editing = ref<Photo | null>(null);
const editPrice = ref(0);
const editBibs = ref('');

function openEdit(photo: Photo) {
    editing.value = photo;
    editPrice.value = photo.price_minor / 100;
    editBibs.value = photo.bib_numbers.join(', ');
}

function saveEdit() {
    if (!editing.value) {
return;
}

    router.patch(
        `/fotografo/fotos/${editing.value.uuid}`,
        {
            price_minor: Math.round((Number(editPrice.value) || 0) * 100),
            bib_numbers: editBibs.value.split(/[\s,;]+/).filter(Boolean),
        },
        { preserveScroll: true, onSuccess: () => (editing.value = null) },
    );
}

async function remove(photo: Photo) {
    if (
        await confirmAction({
            title: '¿Eliminar esta foto?',
            text: 'Se quitará de la venta. Solo puedes eliminar fotos que no se han vendido.',
            confirmButtonText: 'Eliminar',
        })
    ) {
        router.delete(`/fotografo/fotos/${photo.uuid}`, {
            preserveScroll: true,
        });
    }
}
</script>

<template>
    <Head title="Mis fotos en venta" />

    <div class="mx-auto w-full max-w-[1400px] space-y-8 p-4 md:p-8">
        <div class="flex items-center gap-3">
            <span
                class="flex size-11 items-center justify-center rounded-xl bg-fl-cream text-fl-gold-ink"
            >
                <ImagePlus class="size-5" />
            </span>
            <div>
                <h1 class="text-xl font-semibold">Mis fotos en venta</h1>
                <p class="text-sm text-muted-foreground">
                    Sube, pon precio y etiqueta los números de corredor.
                </p>
            </div>
        </div>

        <!-- Upload panel -->
        <section
            v-if="approved"
            class="fl-card grid gap-6 p-5 lg:grid-cols-5 lg:p-6"
        >
            <div class="space-y-4 lg:col-span-2">
                <div class="grid gap-1.5">
                    <Label>Evento</Label>
                    <FancySelect
                        v-model="form.event_edition_id"
                        :options="eventOptions"
                        placeholder="Selecciona el evento"
                    />
                    <InputError :message="form.errors.event_edition_id" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="up-price">Precio por foto (MXN)</Label>
                    <div class="relative">
                        <span
                            class="absolute top-1/2 left-3.5 -translate-y-1/2 text-muted-foreground"
                            >$</span
                        >
                        <Input
                            id="up-price"
                            v-model.number="price"
                            type="number"
                            min="0"
                            step="1"
                            class="pl-7"
                        />
                    </div>
                    <InputError :message="form.errors.price_minor" />
                </div>
                <FeeBreakdown
                    :rules="fees"
                    :price-minor="Math.round((Number(price) || 0) * 100)"
                    compact
                />
                <div class="grid gap-1.5">
                    <Label for="up-bibs" class="flex items-center gap-1.5"
                        ><Hash class="size-3.5" /> Números de corredor en estas
                        fotos</Label
                    >
                    <Input
                        id="up-bibs"
                        v-model="form.bibs"
                        placeholder="Ej. 482, 1203, 77"
                    />
                    <p class="text-xs text-muted-foreground">
                        Se aplican a todo el lote; luego puedes ajustar cada
                        foto.
                    </p>
                </div>
            </div>

            <div class="flex flex-col gap-4 lg:col-span-3">
                <div
                    class="flex min-h-48 flex-1 flex-col items-center justify-center rounded-2xl border-2 border-dashed p-6 text-center transition-colors"
                    :class="
                        dragging
                            ? 'border-fl-gold bg-fl-cream/60'
                            : 'border-foreground/15 bg-muted/30'
                    "
                    @dragover.prevent="dragging = true"
                    @dragleave.prevent="dragging = false"
                    @drop.prevent="
                        dragging = false;
                        addFiles($event.dataTransfer?.files ?? []);
                    "
                >
                    <UploadCloud class="size-8 text-fl-gold-ink" />
                    <p class="mt-3 font-medium">
                        Arrastra tus fotos o
                        <button
                            type="button"
                            class="underline underline-offset-4"
                            @click="fileInput?.click()"
                        >
                            selecciónalas
                        </button>
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        JPG, PNG o WEBP · hasta {{ limits.max_files }} por lote
                        · máx. {{ Math.round(limits.max_file_kb / 1024) }} MB
                        c/u. Publicamos una versión con marca de agua; el
                        original solo lo recibe quien compra.
                    </p>
                    <input
                        ref="fileInput"
                        type="file"
                        multiple
                        accept="image/jpeg,image/png,image/webp"
                        class="sr-only"
                        @change="
                            addFiles(
                                ($event.target as HTMLInputElement).files ?? [],
                            );
                            ($event.target as HTMLInputElement).value = '';
                        "
                    />
                </div>
                <div
                    v-if="previews.length"
                    class="grid grid-cols-4 gap-2 sm:grid-cols-6"
                >
                    <div
                        v-for="(src, index) in previews"
                        :key="src"
                        class="relative aspect-square overflow-hidden rounded-lg bg-muted"
                    >
                        <img :src="src" alt="" class="size-full object-cover" />
                        <button
                            type="button"
                            class="absolute top-1 right-1 flex size-6 items-center justify-center rounded-full bg-black/60 text-white"
                            :aria-label="`Quitar foto ${index + 1}`"
                            @click="removeFile(index)"
                        >
                            <X class="size-3.5" />
                        </button>
                    </div>
                </div>
                <InputError
                    :message="
                        form.errors.files ||
                        (form.errors as Record<string, string>)['files.0']
                    "
                />
                <div class="flex items-center justify-between gap-3">
                    <span class="text-sm text-muted-foreground"
                        >{{ form.files.length }} foto{{
                            form.files.length === 1 ? '' : 's'
                        }}
                        listas</span
                    >
                    <Button
                        class="h-11 rounded-full px-6"
                        :disabled="
                            !form.files.length ||
                            !form.event_edition_id ||
                            form.processing
                        "
                        @click="upload"
                    >
                        <UploadCloud class="size-4" />
                        {{
                            form.processing
                                ? `Subiendo… ${form.progress?.percentage ?? 0}%`
                                : 'Enviar a revisión'
                        }}
                    </Button>
                </div>
            </div>
        </section>
        <div
            v-else
            class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"
        >
            Podrás subir fotos en cuanto aprobemos tu perfil.
        </div>

        <!-- Filters -->
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="tab in statusTabs"
                    :key="tab.label"
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-full border px-3.5 py-1.5 text-sm transition-colors"
                    :class="
                        filters.estado === tab.value
                            ? 'border-foreground bg-foreground text-background'
                            : 'border-border hover:border-foreground/30'
                    "
                    @click="filter({ estado: tab.value })"
                >
                    <component :is="tab.icon" class="size-3.5" />
                    {{ tab.label }}
                </button>
            </div>
            <FancySelect
                :model-value="filters.evento"
                :options="[
                    { value: null, label: 'Todos los eventos', icon: Flag },
                    ...eventOptions,
                ]"
                class="sm:w-72"
                aria-label="Filtrar por evento"
                @update:model-value="(v) => filter({ evento: v })"
            />
        </div>

        <!-- Grid -->
        <div
            v-if="photos.data.length"
            class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5"
        >
            <article
                v-for="photo in photos.data"
                :key="photo.uuid"
                class="group fl-card overflow-hidden"
            >
                <div class="relative aspect-[4/5] bg-muted">
                    <img
                        :src="photo.thumb_url"
                        :alt="`Foto en ${photo.event}`"
                        loading="lazy"
                        class="size-full object-cover"
                    />
                    <span
                        class="absolute top-2 left-2 rounded-full border px-2 py-0.5 text-[10px] font-semibold"
                        :class="statusChip[photo.status]"
                        >{{ statusLabel[photo.status] }}</span
                    >
                    <span
                        v-if="photo.sales_count"
                        class="absolute top-2 right-2 rounded-full bg-foreground px-2 py-0.5 text-[10px] font-semibold text-background"
                        >{{ photo.sales_count }} vendida{{
                            photo.sales_count === 1 ? '' : 's'
                        }}</span
                    >
                </div>
                <div class="space-y-2 p-3">
                    <p class="truncate text-xs text-muted-foreground">
                        {{ photo.event }}
                    </p>
                    <p class="flex items-baseline justify-between gap-2">
                        <span
                            class="inline-flex items-center gap-1 text-sm font-semibold"
                            ><Tag class="size-3.5 text-fl-gold-ink" />{{
                                money(photo.price_minor)
                            }}</span
                        >
                        <span class="text-[11px] text-emerald-700"
                            >recibes {{ money(photo.net_minor) }}</span
                        >
                    </p>
                    <p class="flex flex-wrap gap-1">
                        <span
                            v-for="bib in photo.bib_numbers"
                            :key="bib"
                            class="rounded-full bg-muted px-1.5 py-0.5 font-mono text-[10px]"
                            >#{{ bib }}</span
                        >
                        <span
                            v-if="!photo.bib_numbers.length"
                            class="text-[11px] text-amber-700"
                            >Sin números</span
                        >
                    </p>
                    <p
                        v-if="photo.rejection_reason"
                        class="text-[11px] text-red-700"
                    >
                        {{ photo.rejection_reason }}
                    </p>
                    <div class="flex gap-1 pt-1">
                        <Button
                            size="sm"
                            variant="outline"
                            class="h-8 flex-1 rounded-full text-xs"
                            @click="openEdit(photo)"
                            ><Pencil class="size-3" /> Editar</Button
                        >
                        <Button
                            size="icon"
                            variant="ghost"
                            class="size-8 text-red-700"
                            :disabled="photo.sales_count > 0"
                            aria-label="Eliminar"
                            @click="remove(photo)"
                            ><Trash2 class="size-3.5"
                        /></Button>
                    </div>
                </div>
            </article>
        </div>
        <div
            v-else
            class="fl-card flex flex-col items-center gap-3 p-12 text-center"
        >
            <ImagePlus class="size-8 text-fl-gold-ink" />
            <p class="font-semibold">Aquí aparecerán tus fotos</p>
        </div>

        <Pagination :links="photos.links" />

        <Dialog :open="!!editing" @update:open="(v) => !v && (editing = null)">
            <DialogContent class="sm:max-w-md">
                <DialogHeader
                    ><DialogTitle>Editar foto</DialogTitle></DialogHeader
                >
                <div v-if="editing" class="space-y-4">
                    <img
                        :src="editing.thumb_url"
                        alt=""
                        class="aspect-video w-full rounded-lg object-cover"
                    />
                    <div class="grid gap-1.5">
                        <Label for="ed-price">Precio (MXN)</Label>
                        <Input
                            id="ed-price"
                            v-model.number="editPrice"
                            type="number"
                            min="0"
                        />
                    </div>
                    <FeeBreakdown
                        :rules="fees"
                        :price-minor="
                            Math.round((Number(editPrice) || 0) * 100)
                        "
                        compact
                    />
                    <div class="grid gap-1.5">
                        <Label for="ed-bibs">Números de corredor</Label>
                        <Input
                            id="ed-bibs"
                            v-model="editBibs"
                            placeholder="482, 1203"
                        />
                    </div>
                </div>
                <DialogFooter
                    ><Button class="rounded-full" @click="saveEdit"
                        >Guardar</Button
                    ></DialogFooter
                >
            </DialogContent>
        </Dialog>
    </div>
</template>
