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
    sale_price_minor?: number | null;
    promotion_label?: string | null;
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
            class="rounded-full border px-4 py-2 text-sm font-medium transition disabled:cursor-not-allowed disabled:opacity-30"
            :class="
                modelValue === variant.id
                    ? 'border-foreground bg-foreground text-background'
                    : 'border-border text-muted-foreground hover:border-foreground/15'
            "
            @click="emit('update:modelValue', variant.id)"
        >
            {{ variant.name }}
        </button>
    </div>
    <p v-if="selected" class="mt-4 flex flex-wrap items-baseline gap-2">
        <Money
            :minor="selected.sale_price_minor ?? selected.base_price_minor"
            :currency="selected.currency"
            class="font-serif text-3xl"
            :class="
                selected.sale_price_minor ? 'text-red-700' : 'text-foreground'
            "
        />
        <template v-if="selected.sale_price_minor">
            <Money
                :minor="selected.base_price_minor"
                :currency="selected.currency"
                class="text-base text-muted-foreground line-through"
            />
            <span
                class="rounded-full bg-red-600 px-2.5 py-0.5 text-[11px] font-bold text-white uppercase"
                >{{ selected.promotion_label ?? 'Oferta' }}</span
            >
        </template>
    </p>
</template>
