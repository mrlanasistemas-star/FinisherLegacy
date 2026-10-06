<script setup lang="ts">
/**
 * Reusable image upload control: drag & drop on desktop, tap-to-browse on
 * mobile, live preview, computed file size, replace/remove. Emits the raw
 * File (or null) via v-model — callers keep using it exactly like a file
 * input's value.
 */
import { ImagePlus, Upload, X } from '@lucide/vue';
import { useDropZone } from '@vueuse/core';
import { computed, ref, useTemplateRef, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        modelValue: File | null;
        initialUrl?: string | null;
        label: string;
        helpText?: string;
        maxSizeMb?: number;
        aspect?: 'square' | 'wide';
        accept?: string;
        error?: string;
    }>(),
    {
        initialUrl: null,
        helpText: '',
        maxSizeMb: 4,
        aspect: 'square',
        accept: 'image/jpeg,image/png,image/webp',
        error: undefined,
    },
);

const emit = defineEmits<{
    'update:modelValue': [File | null];
}>();

const dropZoneRef = useTemplateRef<HTMLElement>('dropZone');
const inputRef = useTemplateRef<HTMLInputElement>('input');
const localError = ref<string | null>(null);
const isDragging = ref(false);

let objectUrl: string | null = null;
const previewUrl = ref<string | null>(props.initialUrl);

watch(
    () => props.modelValue,
    (file) => {
        if (objectUrl) {
            URL.revokeObjectURL(objectUrl);
            objectUrl = null;
        }

        if (file) {
            objectUrl = URL.createObjectURL(file);
            previewUrl.value = objectUrl;
        } else {
            previewUrl.value = props.initialUrl;
        }
    },
);

const fileSizeLabel = computed(() => {
    const file = props.modelValue;

    if (!file) {
        return null;
    }

    const kb = file.size / 1024;

    return kb < 1024 ? `${kb.toFixed(0)} KB` : `${(kb / 1024).toFixed(1)} MB`;
});

const acceptedTypes = computed(() =>
    props.accept.split(',').map((type) => type.trim()),
);

function validate(file: File): string | null {
    if (!acceptedTypes.value.includes(file.type)) {
        return 'Formato no permitido. Usa JPG, PNG o WEBP.';
    }

    const maxBytes = props.maxSizeMb * 1024 * 1024;

    if (file.size > maxBytes) {
        return `La imagen pesa demasiado. Máximo ${props.maxSizeMb} MB.`;
    }

    return null;
}

function handleFiles(files: File[] | FileList | null) {
    const file = files?.[0] ?? null;

    if (!file) {
        return;
    }

    const validationError = validate(file);

    if (validationError) {
        localError.value = validationError;

        return;
    }

    localError.value = null;
    emit('update:modelValue', file);
}

function onInputChange(event: Event) {
    const target = event.target as HTMLInputElement;
    handleFiles(target.files);
    target.value = '';
}

function remove() {
    localError.value = null;
    emit('update:modelValue', null);
    previewUrl.value = props.initialUrl;
}

function openBrowser() {
    inputRef.value?.click();
}

useDropZone(dropZoneRef, {
    onDrop: (files) => {
        isDragging.value = false;
        handleFiles(files);
    },
    onEnter: () => {
        isDragging.value = true;
    },
    onLeave: () => {
        isDragging.value = false;
    },
    dataTypes: acceptedTypes.value,
});

const displayError = computed(() => props.error ?? localError.value);
</script>

<template>
    <div class="grid gap-2">
        <span class="text-sm font-medium text-foreground">{{ label }}</span>

        <div
            ref="dropZone"
            role="button"
            tabindex="0"
            :class="[
                'fl-focus-glow group relative flex cursor-pointer flex-col items-center justify-center overflow-hidden border border-dashed border-border bg-background/40 transition-colors duration-300',
                aspect === 'square'
                    ? 'aspect-square rounded-2xl'
                    : 'aspect-[3/1] rounded-2xl',
                isDragging
                    ? 'border-fl-gold/70 bg-fl-gold/5'
                    : 'hover:border-fl-gold/40',
            ]"
            @click="openBrowser"
            @keydown.enter="openBrowser"
            @keydown.space.prevent="openBrowser"
        >
            <input
                ref="input"
                type="file"
                :accept="accept"
                class="sr-only"
                @change="onInputChange"
            />

            <template v-if="previewUrl">
                <img
                    :src="previewUrl"
                    :alt="label"
                    class="absolute inset-0 size-full object-cover transition-transform duration-500 group-hover:scale-105"
                />
                <div
                    class="absolute inset-0 flex flex-col items-center justify-end gap-2 bg-gradient-to-t from-background/90 via-background/10 to-transparent p-3 opacity-0 transition-opacity duration-300 group-hover:opacity-100"
                >
                    <div
                        class="flex items-center gap-2 text-xs font-medium text-foreground"
                    >
                        <Upload class="size-3.5" />
                        <span>Cambiar imagen</span>
                    </div>
                </div>
                <button
                    type="button"
                    class="fl-focus-glow absolute top-2 right-2 flex size-7 items-center justify-center rounded-full bg-background/80 text-muted-foreground transition-colors hover:bg-background hover:text-foreground"
                    aria-label="Quitar imagen"
                    @click.stop="remove"
                >
                    <X class="size-4" />
                </button>
                <span
                    v-if="fileSizeLabel"
                    class="absolute top-2 left-2 rounded-full bg-background/80 px-2 py-0.5 text-[10px] font-medium text-muted-foreground"
                >
                    {{ fileSizeLabel }}
                </span>
            </template>

            <template v-else>
                <ImagePlus
                    class="size-8 text-muted-foreground/80 transition-colors group-hover:text-fl-gold-ink"
                />
                <p class="mt-2 px-4 text-center text-xs text-muted-foreground">
                    Arrastra una imagen aquí o
                    <span class="text-fl-gold-ink">toca para elegir</span>
                </p>
            </template>
        </div>

        <p v-if="displayError" class="fl-error-shake text-sm text-red-500">
            {{ displayError }}
        </p>
        <p v-else-if="helpText" class="text-xs text-muted-foreground/80">
            {{ helpText }}
        </p>
    </div>
</template>
