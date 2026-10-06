<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import {
    createVersion as createVersionAction,
    preview as previewAction,
    publish as publishAction,
    testExport as testExportAction,
    updateVersion as updateVersionAction,
} from '@/actions/App/Http/Controllers/Admin/PlateStudioController';
import ElementSidebar from '@/components/plate-studio/ElementSidebar.vue';
import PropertiesPanel from '@/components/plate-studio/PropertiesPanel.vue';
import StudioCanvas from '@/components/plate-studio/StudioCanvas.vue';
import StudioHelpSheet from '@/components/plate-studio/StudioHelpSheet.vue';
import StudioTopbar from '@/components/plate-studio/StudioTopbar.vue';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import type {
    PlateElement,
    PlateFace,
    PlateRenderMode,
} from '@/types/plate-studio';

const props = defineProps<{
    template: {
        id: number;
        name: string;
        slug: string;
        width_mm: number;
        height_mm: number;
        safe_margin_mm: number;
        orientation: string;
        material: string | null;
        back_transform: 'none' | 'mirror_x' | 'mirror_y' | 'rotate_180';
    };
    version: {
        id: number;
        version: number;
        status: 'draft' | 'published' | 'archived';
        editable: boolean;
        front_configuration: { elements: PlateElement[] };
        back_configuration: { elements: PlateElement[] };
    };
    fieldsCatalog: Record<string, { label: string; group: string }>;
}>();

function clone<T>(value: T): T {
    return JSON.parse(JSON.stringify(value));
}

const backTransformLabels: Record<string, string> = {
    none: 'Normal',
    mirror_x: 'Espejo horizontal',
    mirror_y: 'Espejo vertical',
    rotate_180: 'Rotado 180°',
};

const elementsFront = ref<PlateElement[]>(
    clone(props.version.front_configuration.elements ?? []),
);
const elementsBack = ref<PlateElement[]>(
    clone(props.version.back_configuration.elements ?? []),
);
const face = ref<PlateFace>('front');
const mode = ref<PlateRenderMode>('product');
const selectedId = ref<string | null>(null);
const zoom = ref(100);
const saving = ref(false);
const previewSvg = ref('');
const previewWarnings = ref<string[]>([]);
const previewLoading = ref(false);

const currentElements = computed<PlateElement[]>({
    get: () =>
        face.value === 'front' ? elementsFront.value : elementsBack.value,
    set: (value) => {
        if (face.value === 'front') {
            elementsFront.value = value;
        } else {
            elementsBack.value = value;
        }
    },
});
const selectedElement = computed(
    () =>
        currentElements.value.find((el) => el.id === selectedId.value) ?? null,
);

function csrfToken(): string {
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

async function fetchPreview() {
    // See admin/plates/Show.vue — a relative-URL fetch() (and the
    // document.querySelector() csrfToken() needs) can't run during Inertia
    // SSR, which has no browser location or DOM to resolve against.
    if (import.meta.env.SSR) {
        return;
    }

    previewLoading.value = true;

    try {
        const response = await fetch(previewAction.url(), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({
                width_mm: props.template.width_mm,
                height_mm: props.template.height_mm,
                safe_margin_mm: props.template.safe_margin_mm,
                face: face.value,
                mode: mode.value,
                elements: currentElements.value,
            }),
        });

        const json = await response.json();
        previewSvg.value = json.svg ?? '';
        previewWarnings.value = json.warnings ?? [];
    } finally {
        previewLoading.value = false;
    }
}

const debouncedPreview = useDebounceFn(fetchPreview, 200);

watch([face, mode, elementsFront, elementsBack], () => debouncedPreview(), {
    deep: true,
    immediate: true,
});
watch(face, () => (selectedId.value = null));

function addElement(element: PlateElement) {
    currentElements.value = [...currentElements.value, element];
    selectedId.value = element.id;
}

function updateElement(id: string, patch: Partial<PlateElement>) {
    currentElements.value = currentElements.value.map((el) =>
        el.id === id ? { ...el, ...patch } : el,
    );
}

function deleteSelected() {
    if (!selectedId.value) {
        return;
    }

    currentElements.value = currentElements.value.filter(
        (el) => el.id !== selectedId.value,
    );
    selectedId.value = null;
}

function duplicateSelected() {
    const el = selectedElement.value;

    if (!el) {
        return;
    }

    const copy: PlateElement = {
        ...clone(el),
        id: crypto.randomUUID().slice(0, 8),
        x_mm: Math.min(props.template.width_mm - el.width_mm, el.x_mm + 2),
        y_mm: Math.min(props.template.height_mm - el.height_mm, el.y_mm + 2),
    };
    currentElements.value = [...currentElements.value, copy];
    selectedId.value = copy.id;
}

function save() {
    if (!props.version.editable || saving.value) {
        return;
    }

    saving.value = true;
    router.patch(
        updateVersionAction.url(props.version.id),
        {
            front_configuration: { elements: elementsFront.value },
            back_configuration: { elements: elementsBack.value },
        },
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => (saving.value = false),
        },
    );
}

function publish() {
    router.patch(
        updateVersionAction.url(props.version.id),
        {
            front_configuration: { elements: elementsFront.value },
            back_configuration: { elements: elementsBack.value },
        },
        {
            preserveScroll: true,
            onSuccess: () =>
                router.post(
                    publishAction.url(props.version.id),
                    {},
                    { preserveScroll: true },
                ),
        },
    );
}

function newVersion() {
    router.post(createVersionAction.url(props.template.id));
}

function downloadTestExport() {
    window.open(testExportAction.url([props.version.id, face.value]), '_blank');
}

function onKeydown(e: KeyboardEvent) {
    const target = e.target as HTMLElement;

    if (['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName)) {
        return;
    }

    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
        e.preventDefault();
        save();
    } else if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'd') {
        e.preventDefault();
        duplicateSelected();
    } else if (e.key === 'Delete' || e.key === 'Backspace') {
        if (selectedId.value) {
            e.preventDefault();
            deleteSelected();
        }
    } else if (
        ['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(e.key) &&
        selectedId.value
    ) {
        e.preventDefault();
        const step = e.shiftKey ? 5 : 1;
        const el = selectedElement.value;

        if (!el) {
            return;
        }

        const delta: Record<string, [number, number]> = {
            ArrowUp: [0, -step],
            ArrowDown: [0, step],
            ArrowLeft: [-step, 0],
            ArrowRight: [step, 0],
        };
        const [dx, dy] = delta[e.key];
        updateElement(el.id, {
            x_mm: Math.round((el.x_mm + dx) * 10) / 10,
            y_mm: Math.round((el.y_mm + dy) * 10) / 10,
        });
    }
}

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <Head :title="`Plate Studio — ${template.name}`" />

    <div class="flex h-svh flex-col bg-background">
        <StudioTopbar
            :template-name="template.name"
            :version="version.version"
            :status="version.status"
            :editable="version.editable"
            :saving="saving"
            v-model:zoom="zoom"
            @save="save"
            @publish="publish"
            @new-version="newVersion"
        />

        <div class="flex items-center gap-3 border-b border-border px-4 py-2">
            <Tabs v-model="face">
                <TabsList>
                    <TabsTrigger value="front">Frente</TabsTrigger>
                    <TabsTrigger value="back">Reverso</TabsTrigger>
                </TabsList>
            </Tabs>

            <Tabs v-model="mode">
                <TabsList>
                    <TabsTrigger value="product">Vista producto</TabsTrigger>
                    <TabsTrigger value="production">Vista grabado</TabsTrigger>
                </TabsList>
            </Tabs>

            <Badge
                v-if="
                    face === 'back' &&
                    mode === 'production' &&
                    template.back_transform !== 'none'
                "
                variant="outline"
                class="border-fl-gold/30 text-fl-gold-ink"
                title="Orientación aplicada solo al archivo de producción — el diseño original no cambia"
            >
                {{ backTransformLabels[template.back_transform] }}
            </Badge>
            <Badge
                v-else-if="face === 'back'"
                variant="outline"
                class="border-border text-muted-foreground/80"
            >
                {{ mode === 'production' ? 'Normal' : 'Diseño original' }}
            </Badge>

            <StudioHelpSheet />

            <Badge
                v-if="previewWarnings.length"
                variant="outline"
                class="ml-auto border-amber-500/30 text-amber-700"
            >
                {{ previewWarnings.length }} advertencia{{
                    previewWarnings.length === 1 ? '' : 's'
                }}
            </Badge>
            <Badge
                v-else
                variant="outline"
                class="ml-auto border-emerald-500/30 text-emerald-700"
            >
                Listo para producción
            </Badge>

            <button
                class="text-xs text-muted-foreground underline hover:text-foreground"
                @click="downloadTestExport"
            >
                Descargar prueba de grabado
            </button>
        </div>

        <div class="grid min-h-0 flex-1 grid-cols-[220px_1fr_260px]">
            <aside class="overflow-y-auto border-r border-border">
                <ElementSidebar
                    :fields-catalog="fieldsCatalog"
                    :width-mm="template.width_mm"
                    :height-mm="template.height_mm"
                    @add="addElement"
                />
            </aside>

            <main
                class="flex min-w-0 flex-col items-center justify-center gap-4 overflow-auto bg-[radial-gradient(circle,rgba(255,255,255,0.04)_1px,transparent_1px)] bg-[size:16px_16px] p-8"
            >
                <StudioCanvas
                    :width-mm="template.width_mm"
                    :height-mm="template.height_mm"
                    :elements="currentElements"
                    :selected-id="selectedId"
                    :preview-svg="previewSvg"
                    :preview-loading="previewLoading"
                    :zoom="zoom"
                    @select="(id) => (selectedId = id)"
                    @update:element="updateElement"
                />

                <ul
                    v-if="previewWarnings.length"
                    class="w-full max-w-md space-y-1 text-xs text-amber-700"
                >
                    <li v-for="(warning, i) in previewWarnings" :key="i">
                        • {{ warning }}
                    </li>
                </ul>
            </main>

            <aside class="overflow-y-auto border-l border-border">
                <PropertiesPanel
                    :element="selectedElement"
                    :fields-catalog="fieldsCatalog"
                    @update="
                        (patch) =>
                            selectedElement &&
                            updateElement(selectedElement.id, patch)
                    "
                    @delete="deleteSelected"
                    @duplicate="duplicateSelected"
                />
            </aside>
        </div>
    </div>
</template>
