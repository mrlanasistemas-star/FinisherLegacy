<script setup lang="ts">
import {
    AlignLeft,
    Braces,
    Image,
    Minus,
    QrCode,
    Square,
    Type,
} from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type { PlateElement } from '@/types/plate-studio';

const props = defineProps<{
    fieldsCatalog: Record<string, { label: string; group: string }>;
    widthMm: number;
    heightMm: number;
}>();

const emit = defineEmits<{ add: [element: PlateElement] }>();

function newId(): string {
    return crypto.randomUUID().slice(0, 8);
}

function center(widthMm: number, heightMm: number) {
    return {
        x_mm: Math.max(
            0,
            Math.round(((props.widthMm - widthMm) / 2) * 10) / 10,
        ),
        y_mm: Math.max(
            0,
            Math.round(((props.heightMm - heightMm) / 2) * 10) / 10,
        ),
    };
}

function addStaticText() {
    emit('add', {
        id: newId(),
        type: 'static_text',
        text: 'Texto',
        ...center(30, 5),
        width_mm: 30,
        height_mm: 5,
        font_family: 'Inter',
        font_size_pt: 6,
        font_weight: 400,
        text_align: 'left',
        color: '#0a090c',
    });
}

function addDynamicField(field: string) {
    if (!field) {
        return;
    }

    emit('add', {
        id: newId(),
        type: 'dynamic_text',
        field,
        text: `{{${field}}}`,
        ...center(30, 5),
        width_mm: 30,
        height_mm: 5,
        font_family: 'Inter',
        font_size_pt: 6,
        font_weight: 400,
        text_align: 'left',
        color: '#0a090c',
        auto_fit: true,
        min_font_size_pt: 4,
    });
}

function addQr() {
    emit('add', {
        id: newId(),
        type: 'qr',
        ...center(11, 11),
        width_mm: 11,
        height_mm: 11,
        error_correction: 'H',
    });
}

function addSerial() {
    emit('add', {
        id: newId(),
        type: 'serial',
        text: '{{plate_serial}}',
        ...center(25, 2.5),
        width_mm: 25,
        height_mm: 2.5,
        font_family: 'Inter',
        font_size_pt: 2.6,
        font_weight: 400,
        text_align: 'left',
        color: '#8a8a8a',
    });
}

function addLine() {
    emit('add', {
        id: newId(),
        type: 'line',
        ...center(20, 0),
        width_mm: 20,
        height_mm: 0,
        stroke: '#0a090c',
        stroke_width_mm: 0.2,
    });
}

function addRect() {
    emit('add', {
        id: newId(),
        type: 'rect',
        ...center(20, 10),
        width_mm: 20,
        height_mm: 10,
        stroke: '#0a090c',
        fill: 'none',
        stroke_width_mm: 0.2,
    });
}

function addImage(type: 'image' | 'logo') {
    emit('add', {
        id: newId(),
        type,
        ...center(10, 10),
        width_mm: 10,
        height_mm: 10,
        src: '',
    });
}
</script>

<template>
    <div class="space-y-4 p-3">
        <div>
            <p
                class="mb-2 px-1 text-xs font-medium tracking-wide text-muted-foreground/80 uppercase"
            >
                Texto
            </p>
            <div class="grid grid-cols-2 gap-2">
                <Tooltip>
                    <TooltipTrigger as-child>
                        <Button
                            variant="outline"
                            size="sm"
                            class="border-border text-foreground hover:bg-foreground/5"
                            @click="addStaticText"
                        >
                            <Type class="size-4" />
                            Estático
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent
                        >Texto fijo, igual en todas las placas.</TooltipContent
                    >
                </Tooltip>
                <Tooltip>
                    <TooltipTrigger as-child>
                        <Button
                            variant="outline"
                            size="sm"
                            class="border-border text-foreground hover:bg-foreground/5"
                            @click="addSerial"
                        >
                            <AlignLeft class="size-4" />
                            Serial
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent
                        >Muestra el serial único de cada placa.</TooltipContent
                    >
                </Tooltip>
            </div>

            <div class="mt-2 grid gap-1.5">
                <Select @update:model-value="(v) => addDynamicField(String(v))">
                    <SelectTrigger
                        class="w-full border-border bg-background text-foreground"
                    >
                        <SelectValue placeholder="+ Campo dinámico…" />
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
        </div>

        <div>
            <p
                class="mb-2 px-1 text-xs font-medium tracking-wide text-muted-foreground/80 uppercase"
            >
                Código
            </p>
            <Tooltip>
                <TooltipTrigger as-child>
                    <Button
                        variant="outline"
                        size="sm"
                        class="w-full border-border text-foreground hover:bg-foreground/5"
                        @click="addQr"
                    >
                        <QrCode class="size-4" />
                        QR
                    </Button>
                </TooltipTrigger>
                <TooltipContent
                    >Este código es permanente. No se genera uno nuevo al
                    reimprimir la placa.</TooltipContent
                >
            </Tooltip>
        </div>

        <div>
            <p
                class="mb-2 px-1 text-xs font-medium tracking-wide text-muted-foreground/80 uppercase"
            >
                Formas
            </p>
            <div class="grid grid-cols-2 gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    class="border-border text-foreground hover:bg-foreground/5"
                    @click="addLine"
                >
                    <Minus class="size-4" />
                    Línea
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    class="border-border text-foreground hover:bg-foreground/5"
                    @click="addRect"
                >
                    <Square class="size-4" />
                    Rectángulo
                </Button>
            </div>
        </div>

        <div>
            <p
                class="mb-2 px-1 text-xs font-medium tracking-wide text-muted-foreground/80 uppercase"
            >
                Imagen
            </p>
            <div class="grid grid-cols-2 gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    class="border-border text-foreground hover:bg-foreground/5"
                    @click="addImage('logo')"
                >
                    <Image class="size-4" />
                    Logo
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    class="border-border text-foreground hover:bg-foreground/5"
                    @click="addImage('image')"
                >
                    <Braces class="size-4" />
                    Imagen
                </Button>
            </div>
        </div>
    </div>
</template>
