<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Package, ShieldCheck, Truck } from '@lucide/vue';
import { computed, ref } from 'vue';
import VariantSelector from '@/components/shared/VariantSelector.vue';
import type { Variant } from '@/components/shared/VariantSelector.vue';
import { Button } from '@/components/ui/button';

type Product = {
    uuid: string;
    name: string;
    slug: string;
    description: string | null;
    type: string;
    brand: string | null;
    category: string | null;
    qr_capable: boolean;
    requires_shipping: boolean;
    image_url: string | null;
    variants: Variant[];
};

const props = defineProps<{ product: Product }>();

const selectedVariantId = ref<number | null>(
    props.product.variants[0]?.id ?? null,
);
const selectedVariant = computed(
    () =>
        props.product.variants.find((v) => v.id === selectedVariantId.value) ??
        null,
);

const form = useForm({
    product_variant_id: selectedVariantId.value,
    quantity: 1,
});

function addToCart() {
    if (!selectedVariant.value) {
        return;
    }

    form.product_variant_id = selectedVariant.value.id;
    form.post('/carrito/items', { preserveScroll: true });
}
</script>

<template>
    <Head :title="`${product.name} — Finisher Legacy`" />

    <div class="bg-fl-black">
        <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6 lg:px-8">
            <Link
                href="/tienda"
                class="text-xs tracking-wide text-white/40 uppercase hover:text-fl-gold-soft"
                >← Volver a la tienda</Link
            >

            <div class="mt-6 grid gap-10 lg:grid-cols-2">
                <div
                    class="aspect-square overflow-hidden rounded-2xl border border-white/10 bg-fl-graphite/30"
                >
                    <img
                        v-if="product.image_url"
                        :src="product.image_url"
                        :alt="product.name"
                        class="size-full object-cover"
                    />
                    <div
                        v-else
                        class="flex size-full items-center justify-center"
                    >
                        <Package class="size-16 text-white/10" />
                    </div>
                </div>

                <div>
                    <p
                        v-if="product.category"
                        class="text-xs tracking-[0.2em] text-fl-gold-soft/70 uppercase"
                    >
                        {{ product.category }}
                    </p>
                    <h1 class="mt-2 text-3xl font-black text-white">
                        {{ product.name }}
                    </h1>
                    <p v-if="product.brand" class="mt-1 text-sm text-white/40">
                        {{ product.brand }}
                    </p>
                    <p v-if="product.description" class="mt-4 text-white/60">
                        {{ product.description }}
                    </p>

                    <div class="mt-8">
                        <p
                            class="mb-3 text-xs tracking-wide text-white/40 uppercase"
                        >
                            Selecciona una opción
                        </p>
                        <VariantSelector
                            v-model="selectedVariantId"
                            :variants="product.variants"
                        />
                    </div>

                    <div class="mt-8">
                        <Button
                            class="w-full bg-fl-gold text-fl-black hover:bg-fl-gold-soft sm:w-auto"
                            :disabled="
                                !selectedVariant ||
                                !selectedVariant.in_stock ||
                                form.processing
                            "
                            @click="addToCart"
                        >
                            {{
                                selectedVariant && !selectedVariant.in_stock
                                    ? 'Agotado'
                                    : 'Agregar al carrito'
                            }}
                        </Button>
                    </div>

                    <div
                        class="mt-8 space-y-2 border-t border-white/10 pt-6 text-sm text-white/50"
                    >
                        <p
                            v-if="product.requires_shipping"
                            class="flex items-center gap-2"
                        >
                            <Truck class="size-4" /> Incluye envío
                        </p>
                        <p
                            v-if="product.qr_capable"
                            class="flex items-center gap-2"
                        >
                            <ShieldCheck class="size-4" /> Vinculable a tu
                            Legacy Plate
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
