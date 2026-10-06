<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Plus, Trash2, Upload } from '@lucide/vue';
import { ref } from 'vue';
import HelpPopover from '@/components/HelpPopover.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { store, upload } from '@/routes/imports';

const { editions } = defineProps<{
    editions: { id: number; name: string }[];
}>();

type Header = { index: number; label: string };

const step = ref<'upload' | 'map'>('upload');
const uploading = ref(false);
const submitting = ref(false);
const eventEditionId = ref('');
const tempPath = ref('');
const originalFilename = ref('');
const headers = ref<Header[]>([]);

const fields: { key: string; label: string; required: boolean }[] = [
    { key: 'bib_number', label: 'Número de corredor', required: true },
    { key: 'first_name', label: 'Nombre', required: true },
    { key: 'last_name', label: 'Apellidos', required: true },
    {
        key: 'race_name',
        label: 'Distancia (debe coincidir con el nombre exacto de la carrera)',
        required: true,
    },
    { key: 'email', label: 'Correo electrónico', required: false },
    { key: 'phone', label: 'Teléfono', required: false },
    {
        key: 'official_time',
        label: 'Tiempo oficial (opcional)',
        required: false,
    },
    { key: 'pace', label: 'Ritmo (opcional)', required: false },
];

const mapping = ref<Record<string, string>>({});

type SplitRow = { label: string; column: string };
const splitRows = ref<SplitRow[]>([]);

function addSplitRow() {
    splitRows.value.push({ label: '', column: '' });
}

function removeSplitRow(index: number) {
    splitRows.value.splice(index, 1);
}

function onSplitColumnChange(row: SplitRow) {
    if (row.label.trim() !== '') {
        return;
    }

    const header = headers.value.find((h) => String(h.index) === row.column);
    row.label = header?.label ?? '';
}

async function onFileChange(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0];

    if (!file || !eventEditionId.value) {
        return;
    }

    uploading.value = true;
    const formData = new FormData();
    formData.append('file', file);

    const response = await fetch(upload().url, {
        method: 'POST',
        body: formData,
        headers: {
            'X-CSRF-TOKEN':
                document
                    .querySelector('meta[name="csrf-token"]')
                    ?.getAttribute('content') ?? '',
            Accept: 'application/json',
        },
    });

    const json = await response.json();
    uploading.value = false;

    if (!response.ok) {
        return;
    }

    tempPath.value = json.data.temp_path;
    originalFilename.value = json.data.original_filename;
    headers.value = json.data.headers;
    step.value = 'map';
}

function submit() {
    submitting.value = true;

    const splits = splitRows.value
        .filter((row) => row.label.trim() !== '' && row.column !== '')
        .map((row) => ({
            label: row.label.trim(),
            column: Number(row.column),
        }));

    router.post(
        store().url,
        {
            event_edition_id: eventEditionId.value,
            temp_path: tempPath.value,
            original_filename: originalFilename.value,
            mapping: {
                ...Object.fromEntries(
                    Object.entries(mapping.value).map(([key, value]) => [
                        key,
                        value === '' ? null : Number(value),
                    ]),
                ),
                ...(splits.length ? { splits } : {}),
            },
        },
        { onFinish: () => (submitting.value = false) },
    );
}
</script>

<template>
    <Head title="Nueva importación" />

    <div class="mx-auto max-w-2xl space-y-6 p-4 md:p-6">
        <h1 class="text-xl font-bold text-foreground">
            Importar participantes
        </h1>

        <div class="grid gap-2">
            <Label>Evento</Label>
            <Select v-model="eventEditionId">
                <SelectTrigger class="w-full">
                    <SelectValue placeholder="Selecciona el evento" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="edition in editions"
                        :key="edition.id"
                        :value="String(edition.id)"
                    >
                        {{ edition.name }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>

        <div v-if="step === 'upload'" class="grid gap-2">
            <Label>Archivo (CSV o XLSX)</Label>
            <div
                class="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-border p-10 text-center"
            >
                <Upload class="size-6 text-muted-foreground/80" />
                <p class="text-sm text-muted-foreground">
                    Debe incluir una fila de encabezados
                </p>
                <input
                    type="file"
                    accept=".csv,.txt,.xlsx,.xls"
                    :disabled="!eventEditionId || uploading"
                    class="mt-2 text-sm text-muted-foreground"
                    @change="onFileChange"
                />
                <Spinner v-if="uploading" />
            </div>
        </div>

        <div v-else class="space-y-5">
            <p class="text-sm text-muted-foreground">
                {{ originalFilename }} — asigna cada columna de tu archivo:
            </p>

            <div v-for="field in fields" :key="field.key" class="grid gap-2">
                <Label
                    >{{ field.label }}
                    <span v-if="field.required" class="text-fl-gold-ink"
                        >*</span
                    ></Label
                >
                <Select v-model="mapping[field.key]">
                    <SelectTrigger class="w-full">
                        <SelectValue placeholder="Sin asignar" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="header in headers"
                            :key="header.index"
                            :value="String(header.index)"
                        >
                            {{ header.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="grid gap-2">
                <Label class="flex items-center gap-1.5"
                    >Parciales (opcional)
                    <HelpPopover
                        title="Parciales"
                        text="Solo si tu archivo trae columnas de tiempos intermedios (5K, Swim, T1...). La etiqueta se toma del encabezado de la columna y puedes editarla. No es obligatorio."
                    />
                </Label>
                <div
                    v-for="(row, index) in splitRows"
                    :key="index"
                    class="flex items-center gap-2"
                >
                    <Select
                        v-model="row.column"
                        @update:model-value="onSplitColumnChange(row)"
                    >
                        <SelectTrigger class="w-full">
                            <SelectValue placeholder="Columna" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="header in headers"
                                :key="header.index"
                                :value="String(header.index)"
                            >
                                {{ header.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <Input
                        v-model="row.label"
                        placeholder="Etiqueta (ej. 5K)"
                        class="w-40 shrink-0"
                    />
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="shrink-0 text-muted-foreground/80 hover:text-red-700"
                        @click="removeSplitRow(index)"
                    >
                        <Trash2 class="size-4" />
                    </Button>
                </div>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    class="border-border text-foreground hover:bg-foreground/5"
                    @click="addSplitRow"
                >
                    <Plus class="size-4" />
                    Agregar parcial
                </Button>
            </div>

            <Button
                class="w-full bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                :disabled="submitting"
                @click="submit"
            >
                <Spinner v-if="submitting" />
                Iniciar importación
            </Button>
        </div>
    </div>
</template>
