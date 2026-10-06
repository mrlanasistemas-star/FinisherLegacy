<script setup lang="ts">
/**
 * Visual print editor for one of the three Legacy Plate layouts. Both
 * faces (front/back) are printed — no laser — and the plate carries an
 * NFC chip instead of a printed QR. Drag fields to move them, the corner
 * handle to resize, arrow keys to nudge 0.5 mm; "Vista de impresión"
 * hides every guide to show exactly what gets printed.
 */
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    AlignCenter,
    AlignLeft,
    AlignRight,
    ArrowLeft,
    Eye,
    EyeOff,
    FlipHorizontal2,
    ImagePlus,
    Layers,
    Nfc,
    Palette,
    Printer,
    Save,
    Type,
    UserRound,
    ZoomIn,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import SecondaryNav from '@/components/admin/SecondaryNav.vue';
import InputError from '@/components/InputError.vue';
import PlatePrintFace from '@/components/plates/PlatePrintFace.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { LEGACY_PLATE_AREA_NAV } from '@/config/areaNav';
import type {
    LegacyPlateFace,
    LegacyPlateModelData,
    LegacyPlateModelField,
    LegacyPlatePersonalization,
} from '@/types/plates';

type EditorField = LegacyPlateModelField & { id: number; label: string };

const props = defineProps<{
    model: LegacyPlateModelData & {
        id: number;
        layout_slot: number | null;
        sku: string | null;
        description: string | null;
        active: boolean;
    };
    fields: EditorField[];
}>();

// --- Draft state ---------------------------------------------------------

const draft = ref<EditorField[]>(props.fields.map((f) => ({ ...f })));
const dirty = ref(false);
watch(
    () => props.fields,
    (next) => {
        draft.value = next.map((f) => ({ ...f }));
        dirty.value = false;
    },
);

const face = ref<LegacyPlateFace>('front');
const selectedKey = ref<string | null>(null);
const printView = ref(false);
const zoom = ref(1);
const panel = ref<'campos' | 'diseno' | 'muestra'>('campos');

const selected = computed(
    () => draft.value.find((f) => f.field_key === selectedKey.value) ?? null,
);

const sample = ref<LegacyPlatePersonalization>({});

// Live model the canvas renders (colors/artwork follow the design form).
const designForm = useForm({
    _method: 'patch',
    name: props.model.name,
    sku: props.model.sku ?? '',
    description: props.model.description ?? '',
    width_mm: props.model.width_mm,
    height_mm: props.model.height_mm,
    active: props.model.active,
    front_background: props.model.front_background ?? '#F3F2EE',
    back_background: props.model.back_background ?? '#171714',
    front_text_color: props.model.front_text_color ?? '#171714',
    back_text_color: props.model.back_text_color ?? '#F4EEDF',
    front_artwork: null as File | null,
    back_artwork: null as File | null,
    remove_front_artwork: false,
    remove_back_artwork: false,
});

const frontArtPreview = ref<string | null>(
    props.model.front_artwork_url ?? null,
);
const backArtPreview = ref<string | null>(props.model.back_artwork_url ?? null);

const liveModel = computed<LegacyPlateModelData>(() => ({
    ...props.model,
    width_mm: Number(designForm.width_mm) || props.model.width_mm,
    height_mm: Number(designForm.height_mm) || props.model.height_mm,
    front_background: designForm.front_background,
    back_background: designForm.back_background,
    front_text_color: designForm.front_text_color,
    back_text_color: designForm.back_text_color,
    front_artwork_url: designForm.remove_front_artwork
        ? null
        : frontArtPreview.value,
    back_artwork_url: designForm.remove_back_artwork
        ? null
        : backArtPreview.value,
    fields: draft.value,
}));

function pickArtwork(target: 'front' | 'back', event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;

    if (!file) {
return;
}

    if (target === 'front') {
        designForm.front_artwork = file;
        designForm.remove_front_artwork = false;
        frontArtPreview.value = URL.createObjectURL(file);
    } else {
        designForm.back_artwork = file;
        designForm.remove_back_artwork = false;
        backArtPreview.value = URL.createObjectURL(file);
    }
}

function removeArtwork(target: 'front' | 'back') {
    if (target === 'front') {
        designForm.front_artwork = null;
        designForm.remove_front_artwork = true;
    } else {
        designForm.back_artwork = null;
        designForm.remove_back_artwork = true;
    }
}

function saveDesign() {
    designForm
        .transform((data) => ({
            ...data,
            active: data.active ? 1 : 0,
            remove_front_artwork: data.remove_front_artwork ? 1 : 0,
            remove_back_artwork: data.remove_back_artwork ? 1 : 0,
        }))
        .post(`/admin/legacy-plate-models/${props.model.id}`, {
            preserveScroll: true,
            forceFormData: true,
        });
}

// --- Fields ----------------------------------------------------------------

const savingFields = ref(false);

function saveFields() {
    savingFields.value = true;
    router.patch(
        `/admin/legacy-plate-models/${props.model.id}/fields`,
        {
            fields: draft.value.map((f) => ({
                id: f.id,
                face: f.face ?? 'front',
                x: round(f.x),
                y: round(f.y),
                width: round(f.width),
                height: round(f.height),
                font_size: f.font_size,
                alignment: f.alignment,
                max_chars: f.max_chars ?? null,
                required: !!f.required,
                visible: f.visible !== false,
            })),
        },
        {
            preserveScroll: true,
            onFinish: () => (savingFields.value = false),
        },
    );
}

function saveAll() {
    saveFields();

    if (designForm.isDirty) {
        saveDesign();
    }
}

function patchSelected(patch: Partial<EditorField>) {
    if (!selected.value) {
return;
}

    Object.assign(selected.value, patch);
    dirty.value = true;
}

function toggleVisible(field: EditorField) {
    field.visible = field.visible === false;
    dirty.value = true;

    if (field.visible) {
        face.value = field.face ?? 'front';
        selectedKey.value = field.field_key;
    }
}

function selectField(field: EditorField) {
    selectedKey.value = field.field_key;
    face.value = field.face ?? 'front';
}

const round = (n: number) => Math.round(n * 2) / 2;
const clamp = (n: number, min: number, max: number) =>
    Math.min(Math.max(n, min), max);

// --- Drag & resize -----------------------------------------------------------

const canvasRef = ref<HTMLElement | null>(null);
let drag: {
    field: EditorField;
    handle: 'move' | 'resize';
    startX: number;
    startY: number;
    orig: { x: number; y: number; width: number; height: number };
    scale: number;
} | null = null;

function onFieldPointerDown(
    event: PointerEvent,
    field: LegacyPlateModelField,
    handle: 'move' | 'resize',
) {
    if (printView.value || !canvasRef.value) {
return;
}

    const target = draft.value.find((f) => f.field_key === field.field_key);

    if (!target) {
return;
}

    selectedKey.value = target.field_key;
    const rect = canvasRef.value.getBoundingClientRect();
    drag = {
        field: target,
        handle,
        startX: event.clientX,
        startY: event.clientY,
        orig: {
            x: target.x,
            y: target.y,
            width: target.width,
            height: target.height,
        },
        scale: liveModel.value.width_mm / rect.width,
    };
    window.addEventListener('pointermove', onPointerMove);
    window.addEventListener('pointerup', onPointerUp, { once: true });
}

function onPointerMove(event: PointerEvent) {
    if (!drag) {
return;
}

    const dx = (event.clientX - drag.startX) * drag.scale;
    const dy = (event.clientY - drag.startY) * drag.scale;
    const { width_mm: W, height_mm: H } = liveModel.value;

    if (drag.handle === 'move') {
        drag.field.x = round(clamp(drag.orig.x + dx, 0, W - drag.field.width));
        drag.field.y = round(clamp(drag.orig.y + dy, 0, H - drag.field.height));
    } else {
        drag.field.width = round(
            clamp(drag.orig.width + dx, 2, W - drag.field.x),
        );
        drag.field.height = round(
            clamp(drag.orig.height + dy, 1.5, H - drag.field.y),
        );
    }

    dirty.value = true;
}

function onPointerUp() {
    drag = null;
    window.removeEventListener('pointermove', onPointerMove);
}

function onKey(event: KeyboardEvent) {
    const tag = (event.target as HTMLElement)?.tagName;

    if (
        !selected.value ||
        printView.value ||
        ['INPUT', 'TEXTAREA', 'SELECT'].includes(tag)
    ) {
return;
}

    const step = event.shiftKey ? 2 : 0.5;
    const moves: Record<string, [number, number]> = {
        ArrowLeft: [-step, 0],
        ArrowRight: [step, 0],
        ArrowUp: [0, -step],
        ArrowDown: [0, step],
    };
    const move = moves[event.key];

    if (!move) {
return;
}

    event.preventDefault();
    patchSelected({
        x: clamp(
            selected.value.x + move[0],
            0,
            liveModel.value.width_mm - selected.value.width,
        ),
        y: clamp(
            selected.value.y + move[1],
            0,
            liveModel.value.height_mm - selected.value.height,
        ),
    });
}

onMounted(() => window.addEventListener('keydown', onKey));
onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKey);
    window.removeEventListener('pointermove', onPointerMove);
});

const fieldsOnFace = (target: LegacyPlateFace) =>
    draft.value.filter((f) => (f.face ?? 'front') === target);

const sampleKeys = computed(() =>
    draft.value.filter((f) => f.visible !== false),
);
</script>

<template>
    <Head :title="`Layout · ${model.name}`" />

    <div class="mx-auto w-full max-w-[1500px] p-4 md:p-8">
        <SecondaryNav :items="LEGACY_PLATE_AREA_NAV" />

        <!-- Top bar -->
        <div
            class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
        >
            <div class="flex items-center gap-3">
                <Link
                    href="/admin/legacy-plate-models"
                    class="flex size-10 items-center justify-center rounded-full border border-border bg-card hover:border-foreground/25"
                    aria-label="Volver a layouts"
                >
                    <ArrowLeft class="size-4" />
                </Link>
                <div>
                    <p class="fl-eyebrow">
                        Layout {{ model.layout_slot ?? '—' }} de 3 · impresión
                        frente y reverso
                    </p>
                    <h1 class="font-serif text-2xl">{{ designForm.name }}</h1>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    class="inline-flex h-10 items-center gap-2 rounded-full border px-4 text-sm font-medium transition-colors"
                    :class="
                        printView
                            ? 'border-foreground bg-foreground text-background'
                            : 'border-border bg-card hover:border-foreground/25'
                    "
                    :aria-pressed="printView"
                    @click="printView = !printView"
                >
                    <Printer class="size-4" />
                    Vista de impresión
                </button>
                <Button
                    class="h-10 rounded-full px-5"
                    :disabled="savingFields || designForm.processing"
                    @click="saveAll"
                >
                    <Save class="size-4" />
                    Guardar
                    <span
                        v-if="dirty || designForm.isDirty"
                        class="size-2 rounded-full bg-fl-gold"
                        aria-label="Cambios sin guardar"
                    />
                </Button>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-[1fr_380px]">
            <!-- Canvas -->
            <section class="fl-card overflow-hidden">
                <div
                    class="flex flex-wrap items-center justify-between gap-3 border-b border-border px-4 py-3"
                >
                    <div
                        class="inline-flex rounded-full border border-border bg-muted p-1"
                        role="tablist"
                        aria-label="Cara"
                    >
                        <button
                            v-for="option in [
                                { key: 'front', label: 'Frente' },
                                { key: 'back', label: 'Reverso' },
                            ] as const"
                            :key="option.key"
                            type="button"
                            role="tab"
                            :aria-selected="face === option.key"
                            class="rounded-full px-5 py-1.5 text-sm font-semibold transition-colors"
                            :class="
                                face === option.key
                                    ? 'bg-card shadow-sm'
                                    : 'text-muted-foreground'
                            "
                            @click="face = option.key"
                        >
                            {{ option.label }}
                            <span class="ml-1 text-xs text-muted-foreground">{{
                                fieldsOnFace(option.key).filter(
                                    (f) => f.visible !== false,
                                ).length
                            }}</span>
                        </button>
                    </div>
                    <label
                        class="flex items-center gap-2 text-xs text-muted-foreground"
                    >
                        <ZoomIn class="size-4" />
                        <input
                            v-model.number="zoom"
                            type="range"
                            min="0.6"
                            max="1.6"
                            step="0.05"
                            class="w-32"
                            aria-label="Zoom"
                        />
                    </label>
                </div>

                <div
                    class="flex min-h-[420px] items-center justify-center overflow-auto p-6 sm:p-10"
                    :class="
                        printView
                            ? 'bg-white'
                            : 'bg-[radial-gradient(circle,rgb(23_23_20/0.08)_1px,transparent_1px)] [background-size:16px_16px]'
                    "
                >
                    <div
                        ref="canvasRef"
                        class="w-full max-w-3xl transition-[width]"
                        :style="{ width: `${zoom * 100}%` }"
                    >
                        <PlatePrintFace
                            :model="liveModel"
                            :face="face"
                            :fields="draft"
                            :personalization="sample"
                            :show-guides="!printView"
                            :selected-key="selectedKey"
                            @field-pointer-down="onFieldPointerDown"
                            @background-pointer-down="selectedKey = null"
                        />
                        <p
                            class="mt-4 flex flex-wrap items-center justify-center gap-x-4 gap-y-1 text-xs text-muted-foreground"
                        >
                            <span class="legacy-numeric"
                                >{{ liveModel.width_mm }} ×
                                {{ liveModel.height_mm }} mm</span
                            >
                            <span
                                v-if="!printView"
                                class="inline-flex items-center gap-1"
                            >
                                <span
                                    class="inline-block h-px w-4 border-t border-dashed border-fl-gold"
                                />
                                Área imprimible
                            </span>
                            <span class="inline-flex items-center gap-1">
                                <Nfc class="size-3.5 text-fl-gold-ink" />
                                Chip NFC (no se imprime)
                            </span>
                        </p>
                    </div>
                </div>

                <!-- Selected field quick bar -->
                <div
                    v-if="selected && !printView"
                    class="flex flex-wrap items-center gap-3 border-t border-border bg-card px-4 py-3"
                >
                    <span
                        class="inline-flex items-center gap-2 text-sm font-semibold"
                    >
                        <Type class="size-4 text-fl-gold-ink" />
                        {{ selected.label }}
                    </span>
                    <div
                        class="inline-flex rounded-lg border border-border p-0.5"
                    >
                        <button
                            v-for="option in [
                                { value: 'left', icon: AlignLeft },
                                { value: 'center', icon: AlignCenter },
                                { value: 'right', icon: AlignRight },
                            ] as const"
                            :key="option.value"
                            type="button"
                            class="flex size-8 items-center justify-center rounded-md"
                            :class="
                                selected.alignment === option.value
                                    ? 'bg-foreground text-background'
                                    : 'text-muted-foreground hover:bg-muted'
                            "
                            :aria-label="`Alinear ${option.value}`"
                            @click="patchSelected({ alignment: option.value })"
                        >
                            <component :is="option.icon" class="size-4" />
                        </button>
                    </div>
                    <label
                        class="flex items-center gap-2 text-xs text-muted-foreground"
                    >
                        Tamaño
                        <input
                            type="range"
                            min="1.5"
                            max="12"
                            step="0.1"
                            :value="selected.font_size ?? 3.5"
                            class="w-28"
                            aria-label="Tamaño de letra"
                            @input="
                                patchSelected({
                                    font_size: Number(
                                        ($event.target as HTMLInputElement)
                                            .value,
                                    ),
                                })
                            "
                        />
                        <span class="legacy-numeric w-10 text-foreground">{{
                            (selected.font_size ?? 3.5).toFixed(1)
                        }}</span>
                    </label>
                    <button
                        type="button"
                        class="ml-auto inline-flex items-center gap-1.5 rounded-full border border-border px-3 py-1.5 text-xs font-medium hover:border-foreground/25"
                        @click="
                            patchSelected({
                                face:
                                    selected.face === 'back' ? 'front' : 'back',
                            });
                            face = selected.face ?? 'front';
                        "
                    >
                        <FlipHorizontal2 class="size-3.5" />
                        Mover al
                        {{ selected.face === 'back' ? 'frente' : 'reverso' }}
                    </button>
                </div>
            </section>

            <!-- Side panel -->
            <aside class="fl-card flex flex-col overflow-hidden">
                <div
                    class="grid grid-cols-3 border-b border-border"
                    role="tablist"
                >
                    <button
                        v-for="tab in [
                            { key: 'campos', label: 'Campos', icon: Layers },
                            { key: 'diseno', label: 'Diseño', icon: Palette },
                            {
                                key: 'muestra',
                                label: 'Muestra',
                                icon: UserRound,
                            },
                        ] as const"
                        :key="tab.key"
                        type="button"
                        role="tab"
                        :aria-selected="panel === tab.key"
                        class="flex flex-col items-center gap-1 border-b-2 py-3 text-xs font-semibold transition-colors"
                        :class="
                            panel === tab.key
                                ? 'border-fl-gold text-foreground'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        "
                        @click="panel = tab.key"
                    >
                        <component :is="tab.icon" class="size-4" />
                        {{ tab.label }}
                    </button>
                </div>

                <!-- Campos -->
                <div
                    v-if="panel === 'campos'"
                    class="space-y-5 overflow-y-auto p-4"
                >
                    <div
                        v-for="target in ['front', 'back'] as const"
                        :key="target"
                    >
                        <p class="fl-eyebrow mb-2">
                            {{ target === 'front' ? 'Frente' : 'Reverso' }}
                        </p>
                        <ul class="space-y-1.5">
                            <li
                                v-for="field in fieldsOnFace(target)"
                                :key="field.field_key"
                                class="flex items-center gap-2 rounded-xl border px-3 py-2 transition-colors"
                                :class="
                                    selectedKey === field.field_key
                                        ? 'border-fl-gold bg-fl-cream/60'
                                        : 'border-border hover:border-foreground/20'
                                "
                            >
                                <button
                                    type="button"
                                    class="flex min-w-0 flex-1 items-center gap-2 text-left text-sm"
                                    :class="
                                        field.visible === false
                                            ? 'text-muted-foreground line-through'
                                            : ''
                                    "
                                    @click="selectField(field)"
                                >
                                    <Type
                                        class="size-3.5 shrink-0 text-fl-gold-ink"
                                    />
                                    <span class="truncate">{{
                                        field.label
                                    }}</span>
                                </button>
                                <button
                                    type="button"
                                    class="flex size-8 items-center justify-center rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground"
                                    :aria-label="
                                        field.visible === false
                                            ? `Mostrar ${field.label}`
                                            : `Ocultar ${field.label}`
                                    "
                                    @click="toggleVisible(field)"
                                >
                                    <EyeOff
                                        v-if="field.visible === false"
                                        class="size-4"
                                    />
                                    <Eye v-else class="size-4" />
                                </button>
                            </li>
                        </ul>
                    </div>

                    <div
                        v-if="selected"
                        class="grid grid-cols-2 gap-3 rounded-xl border border-border bg-muted/40 p-3"
                    >
                        <p class="col-span-2 text-xs font-semibold">
                            Posición exacta (mm)
                        </p>
                        <label
                            v-for="key in [
                                'x',
                                'y',
                                'width',
                                'height',
                            ] as const"
                            :key="key"
                            class="grid gap-1 text-xs text-muted-foreground"
                        >
                            {{
                                {
                                    x: 'X',
                                    y: 'Y',
                                    width: 'Ancho',
                                    height: 'Alto',
                                }[key]
                            }}
                            <Input
                                type="number"
                                step="0.5"
                                min="0"
                                class="h-9"
                                :model-value="selected[key]"
                                @update:model-value="
                                    (v) => patchSelected({ [key]: Number(v) })
                                "
                            />
                        </label>
                        <label
                            class="col-span-2 grid gap-1 text-xs text-muted-foreground"
                        >
                            Máximo de caracteres
                            <Input
                                type="number"
                                min="1"
                                class="h-9"
                                :model-value="selected.max_chars ?? ''"
                                @update:model-value="
                                    (v) =>
                                        patchSelected({
                                            max_chars:
                                                v === '' ? null : Number(v),
                                        })
                                "
                            />
                        </label>
                    </div>
                    <p v-else class="text-xs text-muted-foreground">
                        Toca un campo en la placa o en la lista para editarlo.
                        Usa las flechas del teclado para moverlo 0.5 mm (Shift =
                        2 mm).
                    </p>
                </div>

                <!-- Diseño -->
                <form
                    v-else-if="panel === 'diseno'"
                    class="space-y-5 overflow-y-auto p-4"
                    @submit.prevent="saveDesign"
                >
                    <div class="grid gap-1.5">
                        <Label for="l-name">Nombre del layout</Label>
                        <Input id="l-name" v-model="designForm.name" />
                        <InputError :message="designForm.errors.name" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="grid gap-1.5">
                            <Label for="l-w">Ancho (mm)</Label>
                            <Input
                                id="l-w"
                                v-model.number="designForm.width_mm"
                                type="number"
                                step="0.5"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="l-h">Alto (mm)</Label>
                            <Input
                                id="l-h"
                                v-model.number="designForm.height_mm"
                                type="number"
                                step="0.5"
                            />
                        </div>
                    </div>

                    <div
                        v-for="target in ['front', 'back'] as const"
                        :key="target"
                        class="space-y-3 rounded-xl border border-border p-3"
                    >
                        <p class="text-sm font-semibold">
                            {{ target === 'front' ? 'Frente' : 'Reverso' }}
                        </p>
                        <div class="grid grid-cols-2 gap-3">
                            <label
                                class="grid gap-1 text-xs text-muted-foreground"
                            >
                                Fondo
                                <span
                                    class="flex h-10 items-center gap-2 rounded-lg border border-input bg-card px-2"
                                >
                                    <input
                                        v-model="
                                            designForm[`${target}_background`]
                                        "
                                        type="color"
                                        class="size-7 cursor-pointer rounded border-0 bg-transparent p-0"
                                        :aria-label="`Color de fondo ${target}`"
                                    />
                                    <span class="font-mono text-xs uppercase">{{
                                        designForm[`${target}_background`]
                                    }}</span>
                                </span>
                            </label>
                            <label
                                class="grid gap-1 text-xs text-muted-foreground"
                            >
                                Texto
                                <span
                                    class="flex h-10 items-center gap-2 rounded-lg border border-input bg-card px-2"
                                >
                                    <input
                                        v-model="
                                            designForm[`${target}_text_color`]
                                        "
                                        type="color"
                                        class="size-7 cursor-pointer rounded border-0 bg-transparent p-0"
                                        :aria-label="`Color de texto ${target}`"
                                    />
                                    <span class="font-mono text-xs uppercase">{{
                                        designForm[`${target}_text_color`]
                                    }}</span>
                                </span>
                            </label>
                        </div>
                        <label
                            class="flex cursor-pointer items-center gap-3 rounded-lg border-2 border-dashed border-foreground/15 p-3 hover:border-fl-gold"
                        >
                            <img
                                v-if="
                                    (target === 'front'
                                        ? frontArtPreview
                                        : backArtPreview) &&
                                    !designForm[`remove_${target}_artwork`]
                                "
                                :src="
                                    (target === 'front'
                                        ? frontArtPreview
                                        : backArtPreview) ?? ''
                                "
                                alt=""
                                class="h-10 w-16 rounded object-cover"
                            />
                            <ImagePlus v-else class="size-5 text-fl-gold-ink" />
                            <span class="text-xs">
                                <span class="block font-medium"
                                    >Arte de impresión</span
                                >
                                <span class="text-muted-foreground"
                                    >PNG/JPG/WEBP a tamaño real (300 dpi)</span
                                >
                            </span>
                            <input
                                type="file"
                                accept="image/png,image/jpeg,image/webp"
                                class="sr-only"
                                @change="pickArtwork(target, $event)"
                            />
                        </label>
                        <button
                            v-if="
                                target === 'front'
                                    ? frontArtPreview
                                    : backArtPreview
                            "
                            type="button"
                            class="text-xs text-red-700 hover:underline"
                            @click="removeArtwork(target)"
                        >
                            Quitar arte
                        </button>
                    </div>

                    <div class="grid gap-1.5">
                        <Label for="l-desc">Descripción</Label>
                        <Textarea
                            id="l-desc"
                            v-model="designForm.description"
                            rows="2"
                        />
                    </div>
                    <label class="flex items-center gap-2 text-sm">
                        <Checkbox
                            :model-value="designForm.active"
                            @update:model-value="
                                (v) => (designForm.active = !!v)
                            "
                        />
                        Disponible para venta
                    </label>
                    <Button
                        type="submit"
                        class="w-full rounded-full"
                        :disabled="designForm.processing"
                    >
                        Guardar diseño
                    </Button>
                </form>

                <!-- Muestra -->
                <div v-else class="space-y-3 overflow-y-auto p-4">
                    <p class="text-xs text-muted-foreground">
                        Escribe datos reales para ver cómo se imprimiría para un
                        atleta. No se guarda nada.
                    </p>
                    <label
                        v-for="field in sampleKeys"
                        :key="field.field_key"
                        class="grid gap-1 text-xs text-muted-foreground"
                    >
                        {{ field.label }}
                        <Input
                            :model-value="sample[field.field_key] ?? ''"
                            @update:model-value="
                                (v) => (sample[field.field_key] = String(v))
                            "
                            class="h-9"
                            :maxlength="field.max_chars ?? undefined"
                        />
                    </label>
                </div>
            </aside>
        </div>
    </div>
</template>
