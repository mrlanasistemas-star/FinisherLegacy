<script setup lang="ts">
import { ChevronDown, Download } from '@lucide/vue';
import { ref } from 'vue';
import {
    exportMethod as exportFace,
    exportPackage,
} from '@/actions/App/Http/Controllers/Admin/PlateController';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

const props = defineProps<{ plateId: number }>();
const open = defineModel<boolean>('open', { default: false });

const format = ref<'svg' | 'png' | 'pdf' | 'zip'>('svg');
const dpi = ref('300');
const face = ref<'front' | 'back'>('front');
const textAsPaths = ref(false);
const guideOpen = ref(false);

const guideSteps = [
    'Descarga el SVG del frente.',
    'Abre LightBurn.',
    'Importa front.svg.',
    'Confirma 60 × 40 mm — no dejes que el software reescale.',
    'Coloca la placa en el jig.',
    'Configura potencia y velocidad según tu máquina y material (se calibra físicamente, no viene predefinido).',
    'Ejecuta Frame/Preview antes de grabar.',
    'Graba el frente.',
    'Voltea la placa.',
    'Importa back.svg.',
    'Colócala nuevamente en el jig.',
    'Aplica la transformación del reverso si el perfil de máquina la requiere (espejo/rotación).',
    'Ejecuta Frame otra vez.',
    'Graba el reverso.',
    'Lee el NFC físicamente para validarlo.',
    'Marca la placa como lista.',
];

function download() {
    if (format.value === 'zip') {
        window.open(exportPackage.url(props.plateId), '_blank');
    } else {
        const query: Record<string, string> = {};

        if (format.value === 'png') {
            query.dpi = dpi.value;
        }

        if (format.value === 'svg' && textAsPaths.value) {
            query.text_as_paths = '1';
        }

        window.open(
            exportFace.url([props.plateId, face.value, format.value], {
                query,
            }),
            '_blank',
        );
    }

    open.value = false;
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="border-border bg-card text-foreground">
            <DialogHeader>
                <DialogTitle>Descargar archivos de producción</DialogTitle>
            </DialogHeader>

            <div class="space-y-4">
                <div class="grid gap-2">
                    <Label>Formato</Label>
                    <Select v-model="format">
                        <SelectTrigger
                            class="w-full border-border bg-background text-foreground"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="svg"
                                >SVG (vector, recomendado para
                                producción)</SelectItem
                            >
                            <SelectItem value="png">PNG (imagen)</SelectItem>
                            <SelectItem value="pdf"
                                >PDF (tamaño físico exacto)</SelectItem
                            >
                            <SelectItem value="zip"
                                >Ambas caras — paquete (ZIP)</SelectItem
                            >
                        </SelectContent>
                    </Select>
                </div>

                <div v-if="format !== 'zip'" class="grid gap-2">
                    <Label>Cara</Label>
                    <Select v-model="face">
                        <SelectTrigger
                            class="w-full border-border bg-background text-foreground"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="front">Frente</SelectItem>
                            <SelectItem value="back">Reverso</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div v-if="format === 'png'" class="grid gap-2">
                    <Label>Resolución</Label>
                    <Select v-model="dpi">
                        <SelectTrigger
                            class="w-full border-border bg-background text-foreground"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="300">300 DPI</SelectItem>
                            <SelectItem value="600">600 DPI</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <label
                    v-if="format === 'svg'"
                    class="flex items-start gap-2.5 text-sm text-foreground"
                >
                    <Checkbox
                        :model-value="textAsPaths"
                        class="mt-0.5"
                        @update:model-value="(v) => (textAsPaths = !!v)"
                    />
                    <span>
                        Convertir texto a trazos (paths)
                        <span class="block text-xs text-muted-foreground/80">
                            El archivo deja de depender de tener una fuente
                            instalada en la PC de producción — cada letra se
                            convierte a su contorno vectorial exacto, aunque el
                            trazo puede diferir ligeramente de la fuente Inter
                            que ves en pantalla.
                        </span>
                    </span>
                </label>

                <p
                    v-if="format === 'zip'"
                    class="text-xs text-muted-foreground"
                >
                    Incluye frente y reverso como archivos SVG, PNG y PDF por
                    separado (nunca mezclados en un solo archivo), más qr.svg y
                    production.json con serial, Legacy Code y evento.
                </p>

                <div class="rounded-lg border border-border">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between px-3 py-2 text-left text-xs font-medium text-muted-foreground hover:text-fl-gold-ink"
                        @click="guideOpen = !guideOpen"
                    >
                        ¿Cómo grabar esta placa?
                        <ChevronDown
                            class="size-3.5 transition-transform"
                            :class="{ 'rotate-180': guideOpen }"
                        />
                    </button>
                    <ol
                        v-if="guideOpen"
                        class="list-inside list-decimal space-y-1 px-3 pb-3 text-xs text-muted-foreground"
                    >
                        <li v-for="step in guideSteps" :key="step">
                            {{ step }}
                        </li>
                    </ol>
                </div>
            </div>

            <DialogFooter>
                <Button
                    class="w-full bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                    @click="download"
                >
                    <Download class="size-4" />
                    Descargar
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
