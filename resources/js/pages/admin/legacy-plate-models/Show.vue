<script setup lang="ts">
/**
 * "Personalización Legacy Plate" (brief §13-§15): no Figma-style editor —
 * the model's real photo is the base, the engraving_area is a fixed safe
 * zone, and each of the 5 whitelisted fields only gets numeric position
 * inputs. Nothing here ever adds decoration/background.
 */
import { Head, useForm } from '@inertiajs/vue3';
import { LayoutTemplate, Save } from '@lucide/vue';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type FieldRow = {
    id: number;
    field_key: string;
    x: number;
    y: number;
    width: number;
    height: number;
    font_size: number | null | undefined;
    alignment: string;
    max_chars: number | null | undefined;
    required: boolean;
    visible: boolean;
};

const props = defineProps<{
    model: {
        id: number;
        name: string;
        sku: string | null;
        description: string | null;
        width_mm: number;
        height_mm: number;
        engraving_area: { x: number; y: number; width: number; height: number };
        active: boolean;
        preview_image_url: string | null;
    };
    fields: FieldRow[];
}>();

const fieldLabels: Record<string, string> = {
    athlete_name: 'Nombre del atleta',
    race_label: 'Carrera / Distancia',
    official_time: 'Tiempo oficial',
    pace: 'Ritmo',
    qr: 'Código QR',
};

const form = useForm({
    fields: props.fields.map((f) => ({
        ...f,
        font_size: f.font_size ?? undefined,
        max_chars: f.max_chars ?? undefined,
    })),
});

function pct(value: number, total: number): string {
    return `${(value / total) * 100}%`;
}

const areaStyle = computed(() => ({
    left: pct(props.model.engraving_area.x, props.model.width_mm),
    top: pct(props.model.engraving_area.y, props.model.height_mm),
    width: pct(props.model.engraving_area.width, props.model.width_mm),
    height: pct(props.model.engraving_area.height, props.model.height_mm),
}));

function fieldStyle(field: FieldRow) {
    return {
        left: pct(field.x, props.model.width_mm),
        top: pct(field.y, props.model.height_mm),
        width: pct(field.width, props.model.width_mm),
        height: pct(field.height, props.model.height_mm),
    };
}

function submit() {
    form.patch(`/admin/legacy-plate-models/${props.model.id}/fields`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="model.name" />

    <div class="p-4 md:p-8">
        <div class="mb-6 flex items-center gap-3">
            <h1 class="flex items-center gap-2 text-xl font-bold text-white">
                <LayoutTemplate class="size-5 text-fl-gold" />
                {{ model.name }}
            </h1>
            <Badge variant="outline" class="border-white/15 text-white/50">{{
                model.sku ?? 'Sin SKU'
            }}</Badge>
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

        <div class="grid gap-6 lg:grid-cols-[1fr_1.2fr]">
            <div>
                <p class="mb-2 text-xs text-white/40 uppercase">
                    Vista previa · zona de grabado (línea punteada)
                </p>
                <div
                    class="relative aspect-[5/2] w-full overflow-hidden rounded-xl border border-white/10 bg-fl-black/60"
                >
                    <img
                        v-if="model.preview_image_url"
                        :src="model.preview_image_url"
                        alt=""
                        class="absolute inset-0 size-full object-cover"
                    />
                    <LayoutTemplate
                        v-else
                        class="absolute inset-0 m-auto size-10 text-white/10"
                    />

                    <div
                        class="absolute border border-dashed border-fl-gold/60"
                        :style="areaStyle"
                    />

                    <div
                        v-for="field in form.fields"
                        :key="field.id"
                        class="absolute flex items-center justify-center border border-fl-gold-soft/70 bg-fl-gold-soft/10 text-[8px] text-fl-gold-soft uppercase"
                        :style="fieldStyle(field)"
                    >
                        {{ fieldLabels[field.field_key] }}
                    </div>
                </div>
                <p class="mt-3 text-xs text-white/40">
                    {{ model.description }}
                </p>
            </div>

            <div class="space-y-4">
                <p class="text-xs text-white/40 uppercase">
                    Campos dinámicos — solo estos 5, nunca decoración libre
                </p>
                <div
                    v-for="field in form.fields"
                    :key="field.id"
                    class="rounded-lg border border-white/10 bg-fl-graphite/30 p-4"
                >
                    <div class="mb-3 flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-white">
                            {{ fieldLabels[field.field_key] }}
                        </h3>
                        <label
                            class="flex items-center gap-2 text-xs text-white/50"
                        >
                            <Checkbox
                                :model-value="field.visible"
                                @update:model-value="
                                    (v) => (field.visible = !!v)
                                "
                            />
                            Visible
                        </label>
                    </div>
                    <div class="grid grid-cols-4 gap-2">
                        <div class="grid gap-1">
                            <Label class="text-[10px] text-white/40"
                                >X (mm)</Label
                            >
                            <Input
                                v-model.number="field.x"
                                type="number"
                                step="0.1"
                                class="h-8 bg-fl-black text-xs"
                            />
                        </div>
                        <div class="grid gap-1">
                            <Label class="text-[10px] text-white/40"
                                >Y (mm)</Label
                            >
                            <Input
                                v-model.number="field.y"
                                type="number"
                                step="0.1"
                                class="h-8 bg-fl-black text-xs"
                            />
                        </div>
                        <div class="grid gap-1">
                            <Label class="text-[10px] text-white/40"
                                >Ancho</Label
                            >
                            <Input
                                v-model.number="field.width"
                                type="number"
                                step="0.1"
                                class="h-8 bg-fl-black text-xs"
                            />
                        </div>
                        <div class="grid gap-1">
                            <Label class="text-[10px] text-white/40"
                                >Alto</Label
                            >
                            <Input
                                v-model.number="field.height"
                                type="number"
                                step="0.1"
                                class="h-8 bg-fl-black text-xs"
                            />
                        </div>
                    </div>
                    <div class="mt-2 grid grid-cols-3 gap-2">
                        <div class="grid gap-1">
                            <Label class="text-[10px] text-white/40"
                                >Tamaño de fuente</Label
                            >
                            <Input
                                v-model.number="field.font_size"
                                type="number"
                                step="0.1"
                                class="h-8 bg-fl-black text-xs"
                            />
                        </div>
                        <div class="grid gap-1">
                            <Label class="text-[10px] text-white/40"
                                >Alineación</Label
                            >
                            <Select v-model="field.alignment">
                                <SelectTrigger
                                    class="h-8 border-white/10 bg-fl-black text-xs text-white"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="left"
                                        >Izquierda</SelectItem
                                    >
                                    <SelectItem value="center"
                                        >Centro</SelectItem
                                    >
                                    <SelectItem value="right"
                                        >Derecha</SelectItem
                                    >
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="grid gap-1">
                            <Label class="text-[10px] text-white/40"
                                >Máx. caracteres</Label
                            >
                            <Input
                                v-model.number="field.max_chars"
                                type="number"
                                class="h-8 bg-fl-black text-xs"
                            />
                        </div>
                    </div>
                </div>

                <Button
                    class="bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                    :disabled="form.processing"
                    @click="submit"
                >
                    <Save class="size-4" />
                    Guardar zona de grabado
                </Button>
            </div>
        </div>
    </div>
</template>
