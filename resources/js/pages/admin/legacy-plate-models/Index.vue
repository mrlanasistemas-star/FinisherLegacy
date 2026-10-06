<script setup lang="ts">
/**
 * The three Legacy Plate layouts as visual cards — front and back
 * previews of the real print, with an empty slot to create one while
 * fewer than three exist (the backend enforces the limit too).
 */
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Boxes, Nfc, PenTool, Plus, Printer } from '@lucide/vue';
import { computed, ref } from 'vue';
import SecondaryNav from '@/components/admin/SecondaryNav.vue';
import InputError from '@/components/InputError.vue';
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
import type { LegacyPlateModelData } from '@/types/plates';

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
}>();

const emptySlots = computed(() =>
    Math.max(props.maxLayouts - props.models.length, 0),
);

const open = ref(false);
const form = useForm({
    name: '',
    width_mm: 90,
    height_mm: 36,
    active: true,
});

function create() {
    form.post('/admin/legacy-plate-models', {
        onSuccess: () => (open.value = false),
    });
}
</script>

<template>
    <Head title="Layouts de Legacy Plate" />

    <div class="mx-auto w-full max-w-[1400px] p-4 md:p-8">
        <SecondaryNav :items="LEGACY_PLATE_AREA_NAV" />

        <div class="mb-8 flex items-center gap-3">
            <span
                class="flex size-11 items-center justify-center rounded-xl bg-fl-cream text-fl-gold-ink"
            >
                <Printer class="size-5" />
            </span>
            <div>
                <h1 class="text-xl font-semibold">Layouts de Legacy Plate</h1>
                <p class="text-sm text-muted-foreground">
                    Tres diseños, impresos al frente y al reverso. Cada placa
                    lleva un chip NFC con su Legacy Code.
                </p>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <article
                v-for="layout in models"
                :key="layout.id"
                class="fl-card flex flex-col overflow-hidden"
            >
                <div
                    class="space-y-4 bg-[radial-gradient(circle,rgb(23_23_20/0.07)_1px,transparent_1px)] [background-size:14px_14px] p-6"
                >
                    <div>
                        <p
                            class="mb-1.5 text-[10px] font-semibold tracking-[0.18em] text-muted-foreground uppercase"
                        >
                            Frente
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
                            class="mb-1.5 text-[10px] font-semibold tracking-[0.18em] text-muted-foreground uppercase"
                        >
                            Reverso
                        </p>
                        <div
                            class="drop-shadow-[0_12px_18px_rgb(23_23_20/0.18)]"
                        >
                            <PlatePrintFace
                                :model="layout.viewer"
                                face="back"
                            />
                        </div>
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
                            >{{ layout.width_mm }}×{{
                                layout.height_mm
                            }}
                            mm</span
                        >
                        <span class="inline-flex items-center gap-1"
                            ><Boxes class="size-3.5" />
                            {{ layout.plates_count }} placas</span
                        >
                        <span class="inline-flex items-center gap-1"
                            ><Nfc class="size-3.5" /> NFC</span
                        >
                    </div>
                    <Button as-child class="mt-5 rounded-full">
                        <Link :href="`/admin/legacy-plate-models/${layout.id}`">
                            <PenTool class="size-4" />
                            Abrir editor de impresión
                        </Link>
                    </Button>
                </div>
            </article>

            <button
                v-for="slot in emptySlots"
                :key="`empty-${slot}`"
                type="button"
                class="flex min-h-[420px] flex-col items-center justify-center gap-3 rounded-xl border-2 border-dashed border-foreground/15 bg-card p-8 text-center transition-colors hover:border-fl-gold disabled:opacity-50"
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

        <Dialog v-model:open="open">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Nuevo layout</DialogTitle>
                    <DialogDescription>
                        Después lo diseñas visualmente: frente, reverso, campos,
                        colores y arte.
                    </DialogDescription>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="create">
                    <div class="grid gap-1.5">
                        <Label for="nl-name">Nombre</Label>
                        <Input id="nl-name" v-model="form.name" required />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="grid gap-1.5">
                            <Label for="nl-w">Ancho (mm)</Label>
                            <Input
                                id="nl-w"
                                v-model.number="form.width_mm"
                                type="number"
                                step="0.5"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="nl-h">Alto (mm)</Label>
                            <Input
                                id="nl-h"
                                v-model.number="form.height_mm"
                                type="number"
                                step="0.5"
                            />
                        </div>
                    </div>
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
