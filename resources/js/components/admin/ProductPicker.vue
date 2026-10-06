<script setup lang="ts">
/**
 * Visual multi-select of products (photo + name) used by Ofertas and
 * Cupones to scope a discount to selected products.
 */
import { Check, Package, Search } from '@lucide/vue';
import { computed, ref } from 'vue';

export type PickerProduct = {
    id: number;
    name: string;
    image_url: string | null;
    active: boolean;
};

const props = defineProps<{ products: PickerProduct[] }>();
const model = defineModel<number[]>({ default: () => [] });

const query = ref('');
const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();

    return q
        ? props.products.filter((p) => p.name.toLowerCase().includes(q))
        : props.products;
});

function toggle(id: number) {
    model.value = model.value.includes(id)
        ? model.value.filter((x) => x !== id)
        : [...model.value, id];
}
</script>

<template>
    <div class="rounded-xl border border-border">
        <div class="flex items-center gap-2 border-b border-border px-3">
            <Search class="size-4 text-muted-foreground" />
            <input
                v-model="query"
                type="search"
                placeholder="Buscar producto…"
                aria-label="Buscar producto"
                class="h-10 flex-1 bg-transparent text-sm outline-none"
            />
            <span class="text-xs text-muted-foreground"
                >{{ model.length }} seleccionados</span
            >
        </div>
        <ul
            class="grid max-h-64 grid-cols-2 gap-2 overflow-y-auto p-2 sm:grid-cols-3"
        >
            <li v-for="product in filtered" :key="product.id">
                <button
                    type="button"
                    class="relative flex w-full items-center gap-2 rounded-lg border p-2 text-left text-xs transition-colors"
                    :class="
                        model.includes(product.id)
                            ? 'border-fl-gold bg-fl-cream/70'
                            : 'border-border hover:border-foreground/25'
                    "
                    :aria-pressed="model.includes(product.id)"
                    @click="toggle(product.id)"
                >
                    <img
                        v-if="product.image_url"
                        :src="product.image_url"
                        alt=""
                        class="size-9 shrink-0 rounded-md object-cover"
                    />
                    <span
                        v-else
                        class="flex size-9 shrink-0 items-center justify-center rounded-md bg-muted"
                        ><Package class="size-4 text-muted-foreground"
                    /></span>
                    <span
                        class="line-clamp-2 flex-1 font-medium"
                        :class="product.active ? '' : 'text-muted-foreground'"
                        >{{ product.name }}</span
                    >
                    <span
                        v-if="model.includes(product.id)"
                        class="flex size-5 shrink-0 items-center justify-center rounded-full bg-fl-gold text-fl-black"
                        ><Check class="size-3"
                    /></span>
                </button>
            </li>
        </ul>
    </div>
</template>
