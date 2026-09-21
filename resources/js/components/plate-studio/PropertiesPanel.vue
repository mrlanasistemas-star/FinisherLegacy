<script setup lang="ts">
import { Copy, Trash2 } from '@lucide/vue';
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
import { isTextElement } from '@/types/plate-studio';
import type { PlateElement } from '@/types/plate-studio';

defineProps<{
    element: PlateElement | null;
    fieldsCatalog: Record<string, { label: string; group: string }>;
}>();

const emit = defineEmits<{
    update: [patch: Partial<PlateElement>];
    delete: [];
    duplicate: [];
}>();

function num(value: string): number {
    const parsed = Number(value);

    return Number.isFinite(parsed) ? parsed : 0;
}

const elementTypeLabels: Record<string, string> = {
    static_text: 'Texto fijo',
    dynamic_text: 'Texto dinámico',
    qr: 'Código QR',
    serial: 'Número de serie',
    line: 'Línea',
    rect: 'Rectángulo',
    image: 'Imagen',
    logo: 'Logo',
};
</script>

<template>
    <div class="space-y-4 p-3">
        <p v-if="!element" class="px-1 text-sm text-white/40">
            Selecciona un elemento para editar sus propiedades.
        </p>

        <template v-else>
            <div class="flex items-center justify-between">
                <p
                    class="text-xs font-medium tracking-wide text-white/40 uppercase"
                >
                    {{ elementTypeLabels[element.type] ?? element.type }}
                </p>
                <div class="flex gap-1">
                    <Button
                        variant="ghost"
                        size="icon"
                        class="size-7 text-white/60 hover:text-white"
                        title="Duplicar"
                        @click="emit('duplicate')"
                    >
                        <Copy class="size-3.5" />
                    </Button>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="size-7 text-red-400 hover:text-red-300"
                        title="Eliminar"
                        @click="emit('delete')"
                    >
                        <Trash2 class="size-3.5" />
                    </Button>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div class="grid gap-1">
                    <Label class="text-xs text-white/50">X (mm)</Label>
                    <Input
                        type="number"
                        step="0.1"
                        class="border-white/10 bg-fl-black text-white"
                        :model-value="element.x_mm"
                        @update:model-value="
                            (v) => emit('update', { x_mm: num(String(v)) })
                        "
                    />
                </div>
                <div class="grid gap-1">
                    <Label class="text-xs text-white/50">Y (mm)</Label>
                    <Input
                        type="number"
                        step="0.1"
                        class="border-white/10 bg-fl-black text-white"
                        :model-value="element.y_mm"
                        @update:model-value="
                            (v) => emit('update', { y_mm: num(String(v)) })
                        "
                    />
                </div>
                <div class="grid gap-1">
                    <Label class="text-xs text-white/50">Ancho (mm)</Label>
                    <Input
                        type="number"
                        step="0.1"
                        class="border-white/10 bg-fl-black text-white"
                        :model-value="element.width_mm"
                        @update:model-value="
                            (v) => emit('update', { width_mm: num(String(v)) })
                        "
                    />
                </div>
                <div class="grid gap-1">
                    <Label class="text-xs text-white/50">Alto (mm)</Label>
                    <Input
                        type="number"
                        step="0.1"
                        class="border-white/10 bg-fl-black text-white"
                        :model-value="element.height_mm"
                        @update:model-value="
                            (v) => emit('update', { height_mm: num(String(v)) })
                        "
                    />
                </div>
            </div>

            <template v-if="isTextElement(element.type)">
                <div v-if="element.type === 'dynamic_text'" class="grid gap-1">
                    <Label class="text-xs text-white/50">Campo</Label>
                    <Select
                        :model-value="element.field"
                        @update:model-value="
                            (v) =>
                                emit('update', {
                                    field: String(v),
                                    text: `{{${v}}}`,
                                })
                        "
                    >
                        <SelectTrigger
                            class="w-full border-white/10 bg-fl-black text-white"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="(meta, key) in fieldsCatalog"
                                :key="key"
                                :value="key"
                            >
                                {{ meta.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div v-else class="grid gap-1">
                    <Label class="text-xs text-white/50">
                        Texto (puedes usar &#123;&#123;campo&#125;&#125;)
                    </Label>
                    <Input
                        class="border-white/10 bg-fl-black text-white"
                        :model-value="element.text"
                        @update:model-value="
                            (v) => emit('update', { text: String(v) })
                        "
                    />
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div class="grid gap-1">
                        <Label class="text-xs text-white/50">Tamaño (pt)</Label>
                        <Input
                            type="number"
                            step="0.5"
                            class="border-white/10 bg-fl-black text-white"
                            :model-value="element.font_size_pt"
                            @update:model-value="
                                (v) =>
                                    emit('update', {
                                        font_size_pt: num(String(v)),
                                    })
                            "
                        />
                    </div>
                    <div class="grid gap-1">
                        <Label class="text-xs text-white/50">Grosor</Label>
                        <Select
                            :model-value="String(element.font_weight ?? 400)"
                            @update:model-value="
                                (v) =>
                                    emit('update', { font_weight: Number(v) })
                            "
                        >
                            <SelectTrigger
                                class="w-full border-white/10 bg-fl-black text-white"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="400">Normal</SelectItem>
                                <SelectItem value="600"
                                    >Semi-negrita</SelectItem
                                >
                                <SelectItem value="700">Negrita</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div class="grid gap-1">
                        <Label class="text-xs text-white/50">Alineación</Label>
                        <Select
                            :model-value="element.text_align ?? 'left'"
                            @update:model-value="
                                (v) =>
                                    emit('update', {
                                        text_align: v as
                                            'left' | 'center' | 'right',
                                    })
                            "
                        >
                            <SelectTrigger
                                class="w-full border-white/10 bg-fl-black text-white"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="left">Izquierda</SelectItem>
                                <SelectItem value="center">Centro</SelectItem>
                                <SelectItem value="right">Derecha</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid gap-1">
                        <Label class="text-xs text-white/50">Color</Label>
                        <Input
                            type="color"
                            class="h-9 border-white/10 bg-fl-black p-1"
                            :model-value="element.color ?? '#0a090c'"
                            @update:model-value="
                                (v) => emit('update', { color: String(v) })
                            "
                        />
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <Checkbox
                        :model-value="element.auto_fit ?? false"
                        @update:model-value="
                            (v) => emit('update', { auto_fit: !!v })
                        "
                    />
                    <Label class="text-xs text-white/70"
                        >Ajuste automático si no cabe</Label
                    >
                </div>
                <div v-if="element.auto_fit" class="grid gap-1">
                    <Label class="text-xs text-white/50"
                        >Tamaño mínimo (pt)</Label
                    >
                    <Input
                        type="number"
                        step="0.5"
                        class="border-white/10 bg-fl-black text-white"
                        :model-value="element.min_font_size_pt"
                        @update:model-value="
                            (v) =>
                                emit('update', {
                                    min_font_size_pt: num(String(v)),
                                })
                        "
                    />
                </div>
                <div class="flex items-center gap-2">
                    <Checkbox
                        :model-value="element.required ?? false"
                        @update:model-value="
                            (v) => emit('update', { required: !!v })
                        "
                    />
                    <Label class="text-xs text-white/70"
                        >Advertir si queda vacío</Label
                    >
                </div>
            </template>

            <template v-else-if="element.type === 'qr'">
                <div class="grid gap-1">
                    <Label class="text-xs text-white/50"
                        >Corrección de errores</Label
                    >
                    <Select
                        :model-value="element.error_correction ?? 'H'"
                        @update:model-value="
                            (v) =>
                                emit('update', {
                                    error_correction: v as
                                        'L' | 'M' | 'Q' | 'H',
                                })
                        "
                    >
                        <SelectTrigger
                            class="w-full border-white/10 bg-fl-black text-white"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="L">L — básica</SelectItem>
                            <SelectItem value="M">M — media</SelectItem>
                            <SelectItem value="Q">Q — alta</SelectItem>
                            <SelectItem value="H"
                                >H — máxima (recomendada)</SelectItem
                            >
                        </SelectContent>
                    </Select>
                </div>
                <p class="text-xs text-amber-400/80">
                    Valida este tamaño con una muestra de grabado antes de
                    producción masiva. Respeta la zona de silencio: no coloques
                    texto pegado al QR.
                </p>
            </template>

            <template
                v-else-if="element.type === 'line' || element.type === 'rect'"
            >
                <div class="grid grid-cols-2 gap-2">
                    <div class="grid gap-1">
                        <Label class="text-xs text-white/50"
                            >Color de línea</Label
                        >
                        <Input
                            type="color"
                            class="h-9 border-white/10 bg-fl-black p-1"
                            :model-value="element.stroke ?? '#0a090c'"
                            @update:model-value="
                                (v) => emit('update', { stroke: String(v) })
                            "
                        />
                    </div>
                    <div class="grid gap-1">
                        <Label class="text-xs text-white/50">Grosor (mm)</Label>
                        <Input
                            type="number"
                            step="0.1"
                            class="border-white/10 bg-fl-black text-white"
                            :model-value="element.stroke_width_mm ?? 0.2"
                            @update:model-value="
                                (v) =>
                                    emit('update', {
                                        stroke_width_mm: num(String(v)),
                                    })
                            "
                        />
                    </div>
                </div>
                <div v-if="element.type === 'rect'" class="grid gap-1">
                    <Label class="text-xs text-white/50"
                        >Relleno (vacío = sin relleno)</Label
                    >
                    <Input
                        class="border-white/10 bg-fl-black text-white"
                        placeholder="none"
                        :model-value="element.fill"
                        @update:model-value="
                            (v) => emit('update', { fill: String(v) || 'none' })
                        "
                    />
                </div>
            </template>

            <template
                v-else-if="element.type === 'image' || element.type === 'logo'"
            >
                <div class="grid gap-1">
                    <Label class="text-xs text-white/50"
                        >URL de la imagen</Label
                    >
                    <Input
                        class="border-white/10 bg-fl-black text-white"
                        placeholder="https://…"
                        :model-value="element.src"
                        @update:model-value="
                            (v) => emit('update', { src: String(v) })
                        "
                    />
                </div>
            </template>
        </template>
    </div>
</template>
