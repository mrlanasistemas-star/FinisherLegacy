<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { useVModel } from '@vueuse/core';
import { cn } from '@/lib/utils';

const props = defineProps<{
    defaultValue?: string | number;
    modelValue?: string | number;
    class?: HTMLAttributes['class'];
}>();

const emits = defineEmits<{
    (e: 'update:modelValue', payload: string | number): void;
}>();

const modelValue = useVModel(props, 'modelValue', emits, {
    passive: true,
    defaultValue: props.defaultValue,
});
</script>

<template>
    <textarea
        v-model="modelValue"
        data-slot="textarea"
        :class="
            cn(
                'placeholder:text-muted-foreground/70 border-input flex min-h-24 w-full rounded-lg border bg-card px-3.5 py-2.5 text-base leading-relaxed shadow-[0_1px_2px_rgb(23_23_20/0.04)] transition-[color,box-shadow,border-color] outline-none hover:border-foreground/25 disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
                'focus-visible:border-fl-gold focus-visible:ring-4 focus-visible:ring-fl-gold/15',
                'aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive',
                props.class,
            )
        "
    />
</template>
