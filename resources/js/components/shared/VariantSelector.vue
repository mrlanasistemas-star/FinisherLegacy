<script setup lang="ts">
/**
 * Reusable variant picker — reads whatever attributes the backend gives it
 * (brief §30/§96: "consumir attributes backend, no hardcodear para
 * Trisuit"), works for any product's variant shape.
 */
import { computed } from 'vue';
import Money from '@/components/shared/Money.vue';

export type Variant = {
    id: number;
    sku: string;
    name: string;
    attributes: Record<string, string> | null;
    base_price_minor: number;
    currency: string;
    in_stock: boolean;
};

const props = defineProps<{
    variants: Variant[];
    modelValue: number | null;
}>();

const emit = defineEmits<{ 'update:modelValue': [number] }>();

const selected = computed(
    () => props.variants.find((v) => v.id === props.modelValue) ?? null,
);
</script>

<template>
    <div class="flex flex-wrap gap-2">
        <button
            v-for="variant in variants"
            :key="variant.id"
            type="button"
            :disabled="!variant.in_stock"
            class="rounded-lg border px-4 py-2 text-sm transition disabled:cursor-not-allowed disabled:opacity-30"
            :class="
                modelValue === variant.id
                    ? 'border-fl-gold bg-fl-gold/10 text-fl-gold-soft'
                    : 'border-white/15 text-white/70 hover:border-white/30'
            "
            @click="emit('update:modelValue', variant.id)"
        >
            {{ variant.name }}
        </button>
    </div>
    <p v-if="selected" class="mt-3 text-lg text-white">
        <Money
            :minor="selected.base_price_minor"
            :currency="selected.currency"
        />
    </p>
</template>
