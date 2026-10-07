<script setup lang="ts">
/**
 * The three Legacy Plate V3 layouts as visual cards — the FRONT design
 * (the only printed side) and the clip reference of the back — plus the
 * physical specification shared by the three (size, clip, depth,
 * materials), editable in case the supplier asks for an adjustment.
 */
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    Boxes,
    History,
    Nfc,
    Paperclip,
    PenTool,
    Plus,
    Printer,
    Ruler,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import SecondaryNav from '@/components/admin/SecondaryNav.vue';
import InputError from '@/components/InputError.vue';
import PlateClipBack from '@/components/plates/PlateClipBack.vue';
import PlatePrintFace from '@/components/plates/PlatePrintFace.vue';
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
import { LEGACY_PLATE_AREA_NAV } from '@/config/areaNav';
import type { LegacyPlateModelData, LegacyPlateSpec } from '@/types/plates';

type LayoutCard = {
    id: number;
    layout_slot: number | null;
    name: string;
    sku: string | null;
    description: string | null;
    width_mm: number;
    height_mm: number;
    active: boolean;
    plates_count: number;
    viewer: LegacyPlateModelData;
};

const props = defineProps<{
    models: LayoutCard[];
    maxLayouts: number;
    canCreate: boolean;
    spec: LegacyPlateSpec;
    producedWithOldSpec: number;
}>();

const emptySlots = computed(() =>
    Math.max(props.maxLayouts - props.models.length, 0),
);

const open = ref(false);
const form = useForm({
    name: '',
    width_mm: props.spec.width_mm,
    height_mm: props.spec.height_mm,
    active: true,
});

function create() {
    form.post('/admin/legacy-plate-models', {
        onSuccess: () => (open.value = false),
    });
}

const specForm = useForm({
    width_mm: props.spec.width_mm,
    height_mm: props.spec.height_mm,
    clip_length_mm: props.spec.clip_length_mm,
    clip_height_mm: props.spec.clip_height_mm,
    clip_thickness_mm: props.spec.clip_thickness_mm,
    total_depth_mm: props.spec.total_depth_mm,
    body_material: props.spec.body_material ?? 'Zamak niquelado',
    clip_material: props.spec.clip_material ?? 'Acero inoxidable estampado',
});

const specFields = [
    { key: 'width_mm', label: 'Ancho de placa' },
    { key: 'height_mm', label: 'Alto de placa' },
    { key: 'clip_length_mm', label: 'Largo del clip' },
    { key: 'clip_height_mm', label: 'Alto del clip' },
    { key: 'clip_thickness_mm', label: 'Espesor del clip' },
    { key: 'total_depth_mm', label: 'Grosor total (con clip)' },
] as const;

const previewSpec = computed<LegacyPlateModelData | null>(() =>
    props.models[0]
        ? {
              ...props.models[0].viewer,
              width_mm: Number(specForm.width_mm),
              height_mm: Number(specForm.height_mm),
              spec: {
                  ...props.spec,
                  width_mm: Number(specForm.width_mm),
                  height_mm: Number(specForm.height_mm),
                  clip_length_mm: Number(specForm.clip_length_mm),
                  clip_height_mm: Number(specForm.clip_height_mm),
                  clip_thickness_mm: Number(specForm.clip_thickness_mm),
                  total_depth_mm: Number(specForm.total_depth_mm),
              },
          }
        : null,
);

function saveSpec() {
    specForm.patch('/admin/legacy-plate-models/spec', { preserveScroll: true });
}
</script>

<template>
    <Head title="Layouts de Legacy Plate" />

    <div class="w-full p-4 md:p-8">
        <SecondaryNav :items="LEGACY_PLATE_AREA_NAV" />

        <div class="mb-8 flex flex-wrap items-center gap-3">
            <span
                class="flex size-11 items-center justify-center rounded-xl bg-fl-cream text-fl-gold-ink"
            >
                <Printer class="size-5" />
            </span>
            <div class="min-w-[14rem] flex-1">
                <h1 class="text-xl font-semibold">Layouts de Legacy Plate</h1>
                <p class="text-sm text-muted-foreground">
                    Tres diseños frontales. El reverso no se imprime: lleva el
                    clip de acero inoxidable que sujeta la placa al listón.
                </p>
            </div>
            <span
                class="inline-flex items-center gap-1.5 rounded-full bg-fl-cream px-3 py-1 text-xs font-semibold text-fl-gold-ink"
            >
                Legacy Plate {{ (spec.spec_version ?? 'v3').toUpperCase() }} ·
                {{ spec.width_mm }} × {{ spec.height_mm }} mm
            </span>
        </div>

        <div class="grid grid-cols-1 gap-6 md:grid-cols-2 2xl:grid-cols-3">
            <article
                v-for="layout in models"
                :key="layout.id"
                class="fl-card flex flex-col overflow-hidden"
            >
                <div
                    class="grid grid-cols-[1.4fr_1fr] items-end gap-4 bg-[radial-gradient(circle,rgb(23_23_20/0.07)_1px,transparent_1px)] [background-size:14px_14px] p-5 sm:p-6"
                >
                    <div>
                        <p
                            class="mb-1.5 text-[10px] font-semibold tracking-[0.18em] text-muted-foreground uppercase"
                        >
                            Diseño frontal
                        </p>
                        <div
                            class="drop-shadow-[0_12px_18px_rgb(23_23_20/0.18)]"
                        >
                            <PlatePrintFace
                                :model="layout.viewer"
                                face="front"
                            />
                        </div>
                    </div>
                    <div>
                        <p
                            class="mb-1.5 flex items-center gap-1 text-[10px] font-semibold tracking-[0.18em] text-muted-foreground uppercase"
                        >
                            <Paperclip class="size-3" /> Broche
                        </p>
                        <PlateClipBack :model="layout.viewer" />
                    </div>
                </div>
                <div class="flex flex-1 flex-col border-t border-border p-5">
                    <div class="flex items-center gap-2">
                        <span
                            class="flex size-7 items-center justify-center rounded-full bg-foreground text-xs font-semibold text-background"
                            >{{ layout.layout_slot ?? '·' }}</span
                        >
                        <h2 class="font-serif text-xl">{{ layout.name }}</h2>
                        <span
                            class="ml-auto rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase"
                            :class="
                                layout.active
                                    ? 'bg-emerald-50 text-emerald-700'
                                    : 'bg-muted text-muted-foreground'
                            "
                            >{{ layout.active ? 'Activo' : 'Pausado' }}</span
                        >
                    </div>
                    <p
                        v-if="layout.description"
                        class="mt-2 line-clamp-2 text-sm text-muted-foreground"
                    >
                        {{ layout.description }}
                    </p>
                    <div
                        class="mt-4 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground"
                    >
                        <span class="legacy-numeric"
                            >{{ layout.width_mm }} ×
                            {{ layout.height_mm }} mm</span
                        >
                        <span class="inline-flex items-center gap-1"
                            ><Boxes class="size-3.5" />
                            {{ layout.plates_count }} placas</span
                        >
                        <span class="inline-flex items-center gap-1"
                            ><Nfc class="size-3.5" /> NFC frontal</span
                        >
                    </div>
                    <Button as-child class="mt-5 rounded-full">
                        <Link :href="`/admin/legacy-plate-models/${layout.id}`">
                            <PenTool class="size-4" />
                            Editar diseño frontal
                        </Link>
                    </Button>
                </div>
            </article>

            <button
                v-for="slot in emptySlots"
                :key="`empty-${slot}`"
                type="button"
                class="flex min-h-[320px] flex-col items-center justify-center gap-3 rounded-xl border-2 border-dashed border-foreground/15 bg-card p-8 text-center transition-colors hover:border-fl-gold disabled:opacity-50"
                :disabled="!canCreate"
                @click="open = true"
            >
                <span
                    class="flex size-12 items-center justify-center rounded-full bg-fl-cream text-fl-gold-ink"
                >
                    <Plus class="size-5" />
                </span>
                <span class="font-semibold">Espacio para layout</span>
                <span class="text-sm text-muted-foreground"
                    >Quedan {{ emptySlots }} de {{ maxLayouts }}</span
                >
            </button>
        </div>

        <!-- Physical specification -->
        <section class="fl-card mt-8 overflow-hidden">
            <div
                class="flex flex-wrap items-center gap-3 border-b border-border px-5 py-4"
            >
                <Ruler class="size-5 text-fl-gold-ink" />
                <div class="min-w-0 flex-1">
                    <h2 class="font-semibold">Especificación física</h2>
                    <p class="text-sm text-muted-foreground">
                        La misma pieza para los tres layouts. Ajusta aquí si el
                        proveedor solicita un cambio.
                    </p>
                </div>
            </div>
            <form
                class="grid gap-8 p-5 lg:grid-cols-[1fr_1.1fr]"
                @submit.prevent="saveSpec"
            >
                <div class="space-y-5">
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                        <div
                            v-for="field in specFields"
                            :key="field.key"
                            class="grid gap-1.5"
                        >
                            <Label :for="`spec-${field.key}`"
                                >{{ field.label }}
                                <span class="text-muted-foreground"
                                    >(mm)</span
                                ></Label
                            >
                            <Input
                                :id="`spec-${field.key}`"
                                v-model.number="specForm[field.key]"
                                type="number"
                                step="0.1"
                                min="0"
                            />
                            <InputError :message="specForm.errors[field.key]" />
                        </div>
                    </div>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label for="spec-body">Cuerpo</Label>
                            <Input
                                id="spec-body"
                                v-model="specForm.body_material"
                            />
                            <InputError
                                :message="specForm.errors.body_material"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="spec-clip">Clip</Label>
                            <Input
                                id="spec-clip"
                                v-model="specForm.clip_material"
                            />
                            <InputError
                                :message="specForm.errors.clip_material"
                            />
                        </div>
                    </div>
                    <ul
                        class="grid gap-2 rounded-xl bg-muted/50 p-4 text-sm text-muted-foreground"
                    >
                        <li>
                            <span class="font-medium text-foreground"
                                >Frente:</span
                            >
                            diseño deportivo con resina protectora; inlay NFC
                            sobre ferrita anti-metal bajo el panel FL.
                        </li>
                        <li>
                            <span class="font-medium text-foreground"
                                >Reverso:</span
                            >
                            metal limpio + clip tipo money clip con 2–3 puntos
                            de presión. Sin impresión, sin datos, sin QR.
                        </li>
                    </ul>
                    <p
                        v-if="producedWithOldSpec > 0"
                        class="flex items-start gap-2 rounded-xl border border-border px-4 py-3 text-xs text-muted-foreground"
                    >
                        <History class="mt-0.5 size-4 shrink-0" />
                        {{ producedWithOldSpec }}
                        {{
                            producedWithOldSpec === 1
                                ? 'placa producida conserva'
                                : 'placas producidas conservan'
                        }}
                        el diseño histórico con el que se fabricaron; estos
                        cambios solo aplican a pedidos nuevos.
                    </p>
                    <Button
                        type="submit"
                        class="rounded-full"
                        :disabled="specForm.processing || !specForm.isDirty"
                    >
                        Guardar especificación
                    </Button>
                </div>
                <div
                    v-if="previewSpec"
                    class="rounded-2xl bg-[radial-gradient(circle,rgb(23_23_20/0.07)_1px,transparent_1px)] [background-size:14px_14px] p-5"
                >
                    <PlateClipBack :model="previewSpec" with-profile />
                </div>
            </form>
        </section>

        <Dialog v-model:open="open">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Nuevo layout</DialogTitle>
                    <DialogDescription>
                        Después lo diseñas visualmente: campos, colores y arte
                        del frente.
                    </DialogDescription>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="create">
                    <div class="grid gap-1.5">
                        <Label for="nl-name">Nombre</Label>
                        <Input id="nl-name" v-model="form.name" required />
                        <InputError :message="form.errors.name" />
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Usa la especificación actual:
                        {{ form.width_mm }} × {{ form.height_mm }} mm.
                    </p>
                    <DialogFooter>
                        <Button
                            type="submit"
                            class="rounded-full"
                            :disabled="form.processing"
                        >
                            Crear y diseñar
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
