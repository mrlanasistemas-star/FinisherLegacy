<script setup lang="ts">
/**
 * Lets the user add up to `max` extra photos for a medal's gallery. Kept
 * separate from ImageDropzone (which is single-file) since a grid of small
 * add/remove tiles is a different interaction than one big drop target.
 */
import { ImagePlus, X } from '@lucide/vue';
import { computed, ref, useTemplateRef, watch } from 'vue';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        modelValue: File[];
        max: number;
        error?: string;
    }>(),
    { error: undefined },
);

const emit = defineEmits<{
    'update:modelValue': [File[]];
}>();

const inputRef = useTemplateRef<HTMLInputElement>('input');
const previewUrls = ref<string[]>([]);

watch(
    () => props.modelValue,
    (files) => {
        previewUrls.value.forEach((url) => URL.revokeObjectURL(url));
        previewUrls.value = files.map((file) => URL.createObjectURL(file));
    },
    { immediate: true },
);

const remaining = computed(() => props.max - props.modelValue.length);

function onInputChange(event: Event) {
    const target = event.target as HTMLInputElement;
    const files = Array.from(target.files ?? []).slice(0, remaining.value);

    if (files.length) {
        emit('update:modelValue', [...props.modelValue, ...files]);
    }

    target.value = '';
}

function remove(index: number) {
    emit(
        'update:modelValue',
        props.modelValue.filter((_, i) => i !== index),
    );
}
</script>

<template>
    <div class="grid gap-2">
        <div class="flex flex-wrap gap-3">
            <div
                v-for="(url, index) in previewUrls"
                :key="url"
                class="relative size-20 overflow-hidden rounded-lg border border-border"
            >
                <img :src="url" class="size-full object-cover" alt="" />
                <button
                    type="button"
                    class="fl-focus-glow absolute top-1 right-1 flex size-5 items-center justify-center rounded-full bg-background/80 text-muted-foreground hover:text-foreground"
                    aria-label="Quitar imagen"
                    @click="remove(index)"
                >
                    <X class="size-3" />
                </button>
            </div>

            <button
                v-if="remaining > 0"
                type="button"
                :class="
                    cn(
                        'fl-focus-glow flex size-20 flex-col items-center justify-center gap-1 rounded-lg border border-dashed border-border text-muted-foreground/80 transition-colors hover:border-fl-gold/40 hover:text-fl-gold-ink',
                    )
                "
                @click="inputRef?.click()"
            >
                <ImagePlus class="size-5" />
                <span class="text-[10px]">{{ remaining }} más</span>
            </button>
        </div>

        <input
            ref="input"
            type="file"
            accept="image/jpeg,image/png,image/webp"
            multiple
            class="sr-only"
            @change="onInputChange"
        />

        <p v-if="error" class="fl-error-shake text-sm text-red-500">
            {{ error }}
        </p>
    </div>
</template>
