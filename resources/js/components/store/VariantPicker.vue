<script setup lang="ts">
/**
 * Purchase-column variant picker (owner spec §20). When variants carry
 * `color` / `size` attributes it shows color swatches and a size grid
 * with per-size stock; otherwise it falls back to one chip per variant.
 * Never shows a stock count — only available / últimas piezas / agotada.
 */
import { computed, watch } from 'vue';
import type { Variant } from '@/components/shared/VariantSelector.vue';
import { swatchColor } from '@/lib/store';

type StoreVariant = Variant & { low_stock?: boolean };

const props = defineProps<{ variants: StoreVariant[] }>();
const model = defineModel<number | null>({ required: true });

const attr = (v: StoreVariant, ...keys: string[]): string | null => {
    for (const key of keys) {
        const value = v.attributes?.[key];

        if (value) {
            return String(value);
        }
    }

    return null;
};

const colorOf = (v: StoreVariant) => attr(v, 'color', 'colour');
const sizeOf = (v: StoreVariant) => attr(v, 'size', 'talla');

const colors = computed(() => {
    const seen = new Map<string, { name: string; hex: string | null }>();

    for (const v of props.variants) {
        const name = colorOf(v);

        if (name && !seen.has(name)) {
            seen.set(name, {
                name,
                hex: (v.attributes?.color_hex as string | undefined) ?? null,
            });
        }
    }

    return [...seen.values()];
});

const hasSizes = computed(() => props.variants.some((v) => sizeOf(v)));
const structured = computed(() => colors.value.length > 0 || hasSizes.value);

const selected = computed(
    () => props.variants.find((v) => v.id === model.value) ?? null,
);
const selectedColor = computed(() =>
    selected.value ? colorOf(selected.value) : (colors.value[0]?.name ?? null),
);

const sizesForColor = computed(() =>
    props.variants.filter(
        (v) =>
            sizeOf(v) &&
            (!colors.value.length || colorOf(v) === selectedColor.value),
    ),
);

function pickColor(name: string) {
    const currentSize = selected.value ? sizeOf(selected.value) : null;
    const sameColor = props.variants.filter((v) => colorOf(v) === name);
    const next =
        sameColor.find((v) => sizeOf(v) === currentSize && v.in_stock) ??
        sameColor.find((v) => v.in_stock) ??
        sameColor[0];

    if (next) {
        model.value = next.id;
    }
}

function colorHasStock(name: string): boolean {
    return props.variants.some((v) => colorOf(v) === name && v.in_stock);
}

// Keep a valid selection if the variant list changes.
watch(
    () => props.variants,
    (list) => {
        if (!list.some((v) => v.id === model.value)) {
            model.value =
                list.find((v) => v.in_stock)?.id ?? list[0]?.id ?? null;
        }
    },
);
</script>

<template>
    <div v-if="structured" class="space-y-6">
        <fieldset v-if="colors.length">
            <legend class="mb-3 flex items-baseline gap-2 text-sm">
                <span class="font-medium text-foreground">Color</span>
                <span class="text-muted-foreground">{{ selectedColor }}</span>
            </legend>
            <div class="flex flex-wrap gap-2.5">
                <button
                    v-for="color in colors"
                    :key="color.name"
                    type="button"
                    class="relative flex size-11 items-center justify-center rounded-full border-2 transition focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    :class="
                        selectedColor === color.name
                            ? 'border-foreground'
                            : 'border-transparent hover:border-foreground/25'
                    "
                    :aria-label="`${color.name}${colorHasStock(color.name) ? '' : ' (agotado)'}`"
                    :aria-pressed="selectedColor === color.name"
                    :title="color.name"
                    @click="pickColor(color.name)"
                >
                    <span
                        class="size-8 rounded-full ring-1 ring-foreground/15"
                        :style="{ backgroundColor: swatchColor(color) }"
                    />
                    <span
                        v-if="!colorHasStock(color.name)"
                        class="absolute h-px w-9 rotate-45 bg-foreground/50"
                        aria-hidden="true"
                    />
                </button>
            </div>
        </fieldset>

        <fieldset v-if="sizesForColor.length">
            <legend class="mb-3 text-sm font-medium text-foreground">
                Talla
            </legend>
            <div class="grid grid-cols-4 gap-2 sm:grid-cols-5">
                <button
                    v-for="variant in sizesForColor"
                    :key="variant.id"
                    type="button"
                    :disabled="!variant.in_stock"
                    class="relative h-12 rounded-xl border text-sm font-medium transition focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed"
                    :class="
                        model === variant.id
                            ? 'border-foreground bg-foreground text-background'
                            : variant.in_stock
                              ? 'border-border bg-card text-foreground hover:border-foreground/40'
                              : 'border-dashed border-border bg-transparent text-muted-foreground/50 line-through'
                    "
                    :aria-pressed="model === variant.id"
                    :aria-label="`Talla ${sizeOf(variant)}${variant.in_stock ? (variant.low_stock ? ', últimas piezas' : '') : ', agotada'}`"
                    @click="model = variant.id"
                >
                    {{ sizeOf(variant) }}
                    <span
                        v-if="variant.in_stock && variant.low_stock"
                        class="absolute top-1.5 right-1.5 size-1.5 rounded-full bg-[#c98a1b]"
                        aria-hidden="true"
                    />
                </button>
            </div>
            <p
                v-if="sizesForColor.some((v) => v.in_stock && v.low_stock)"
                class="mt-2 flex items-center gap-1.5 text-xs text-muted-foreground"
            >
                <span
                    class="size-1.5 rounded-full bg-[#c98a1b]"
                    aria-hidden="true"
                />
                Últimas piezas
            </p>
        </fieldset>
    </div>

    <fieldset v-else-if="variants.length > 1">
        <legend class="mb-3 text-sm font-medium text-foreground">Opción</legend>
        <div class="flex flex-wrap gap-2">
            <button
                v-for="variant in variants"
                :key="variant.id"
                type="button"
                :disabled="!variant.in_stock"
                class="h-11 rounded-full border px-4 text-sm font-medium transition focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-40"
                :class="
                    model === variant.id
                        ? 'border-foreground bg-foreground text-background'
                        : 'border-border bg-card text-foreground hover:border-foreground/40'
                "
                :aria-pressed="model === variant.id"
                @click="model = variant.id"
            >
                {{ variant.name }}
            </button>
        </div>
    </fieldset>
</template>
