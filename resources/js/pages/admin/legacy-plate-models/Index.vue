<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { LayoutTemplate, Plus } from '@lucide/vue';
import { ref } from 'vue';
import SecondaryNav from '@/components/admin/SecondaryNav.vue';
import { Badge } from '@/components/ui/badge';
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
import { Textarea } from '@/components/ui/textarea';
import { LEGACY_PLATE_AREA_NAV } from '@/config/areaNav';

type ModelRow = {
    id: number;
    name: string;
    sku: string | null;
    description: string | null;
    width_mm: number;
    height_mm: number;
    active: boolean;
    preview_image_url: string | null;
    plates_count: number;
};

defineProps<{ models: ModelRow[] }>();

const dialogOpen = ref(false);
const form = useForm({
    name: '',
    sku: '',
    description: '',
    width_mm: 90,
    height_mm: 36,
    engraving_x: 8,
    engraving_y: 6,
    engraving_width: 74,
    engraving_height: 24,
    active: true,
    preview_image: null as File | null,
});

function submit() {
    form.post('/admin/legacy-plate-models', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            dialogOpen.value = false;
            form.reset();
        },
    });
}
</script>

<template>
    <Head title="Modelos de Legacy Plate" />

    <div class="p-4 md:p-8">
        <SecondaryNav :items="LEGACY_PLATE_AREA_NAV" />

        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1
                    class="flex items-center gap-2 text-xl font-bold text-white"
                >
                    <LayoutTemplate class="size-5 text-fl-gold" />
                    Modelos de Legacy Plate
                </h1>
                <p class="mt-1 text-sm text-white/50">
                    La pieza física ya viene fabricada — aquí solo se define
                    dónde va el grabado dinámico.
                </p>
            </div>
            <Button
                class="bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                @click="dialogOpen = true"
            >
                <Plus class="size-4" />
                Nuevo modelo
            </Button>
        </div>

        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            <Link
                v-for="model in models"
                :key="model.id"
                :href="`/admin/legacy-plate-models/${model.id}`"
                class="group overflow-hidden rounded-xl border border-white/10 bg-fl-graphite/30 transition hover:border-fl-gold/40"
            >
                <div
                    class="flex aspect-[5/2] items-center justify-center bg-fl-black/60"
                >
                    <img
                        v-if="model.preview_image_url"
                        :src="model.preview_image_url"
                        alt=""
                        class="size-full object-cover"
                    />
                    <LayoutTemplate v-else class="size-8 text-white/15" />
                </div>
                <div class="p-4">
                    <div class="flex items-center justify-between">
                        <h3
                            class="font-semibold text-white group-hover:text-fl-gold"
                        >
                            {{ model.name }}
                        </h3>
                        <Badge
                            variant="outline"
                            :class="
                                model.active
                                    ? 'border-emerald-500/30 text-emerald-400'
                                    : 'border-white/20 text-white/40'
                            "
                        >
                            {{ model.active ? 'Activo' : 'Inactivo' }}
                        </Badge>
                    </div>
                    <p class="mt-1 text-xs text-white/40">
                        {{ model.sku ?? 'Sin SKU' }} · {{ model.width_mm }}×{{
                            model.height_mm
                        }}mm
                    </p>
                    <p class="mt-2 text-xs text-white/30">
                        {{ model.plates_count }} Legacy Plates producidas
                    </p>
                </div>
            </Link>
        </div>

        <Dialog v-model:open="dialogOpen">
            <DialogContent
                class="dark border-white/10 bg-fl-graphite text-white sm:max-w-lg"
            >
                <DialogHeader>
                    <DialogTitle>Nuevo modelo</DialogTitle>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label>Nombre</Label>
                        <Input
                            v-model="form.name"
                            class="bg-fl-black"
                            required
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label>SKU (opcional)</Label>
                        <Input v-model="form.sku" class="bg-fl-black" />
                    </div>
                    <div class="grid gap-2">
                        <Label>Descripción</Label>
                        <Textarea
                            v-model="form.description"
                            class="bg-fl-black"
                            rows="2"
                        />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label class="text-xs">Ancho (mm)</Label>
                            <Input
                                v-model.number="form.width_mm"
                                type="number"
                                step="0.1"
                                class="bg-fl-black"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label class="text-xs">Alto (mm)</Label>
                            <Input
                                v-model.number="form.height_mm"
                                type="number"
                                step="0.1"
                                class="bg-fl-black"
                            />
                        </div>
                    </div>
                    <p class="text-xs text-white/40">
                        Zona de grabado (mm, relativa a la esquina superior
                        izquierda) — se puede ajustar campo por campo después de
                        crear el modelo.
                    </p>
                    <div class="grid grid-cols-4 gap-3">
                        <div class="grid gap-2">
                            <Label class="text-xs">X</Label>
                            <Input
                                v-model.number="form.engraving_x"
                                type="number"
                                step="0.1"
                                class="bg-fl-black"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label class="text-xs">Y</Label>
                            <Input
                                v-model.number="form.engraving_y"
                                type="number"
                                step="0.1"
                                class="bg-fl-black"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label class="text-xs">Ancho</Label>
                            <Input
                                v-model.number="form.engraving_width"
                                type="number"
                                step="0.1"
                                class="bg-fl-black"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label class="text-xs">Alto</Label>
                            <Input
                                v-model.number="form.engraving_height"
                                type="number"
                                step="0.1"
                                class="bg-fl-black"
                            />
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label>Foto del modelo (opcional)</Label>
                        <input
                            type="file"
                            accept="image/*"
                            class="text-sm text-white/60"
                            @change="
                                form.preview_image =
                                    ($event.target as HTMLInputElement)
                                        .files?.[0] ?? null
                            "
                        />
                    </div>
                    <DialogFooter>
                        <Button
                            type="submit"
                            class="w-full bg-fl-gold text-fl-black hover:bg-fl-gold-soft sm:w-auto"
                            :disabled="form.processing"
                        >
                            Crear modelo
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
