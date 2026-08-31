<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ShoppingCart, Trash2 } from '@lucide/vue';
import Money from '@/components/shared/Money.vue';
import { Button } from '@/components/ui/button';

type CartItem = {
    id: number;
    quantity: number;
    product_name: string;
    variant_name: string;
    unit_price_minor: number;
    currency: string;
};

const props = defineProps<{
    items: CartItem[];
    currency: string;
}>();

function updateQuantity(item: CartItem, quantity: number) {
    if (quantity < 0) {
        return;
    }

    router.patch(
        `/carrito/items/${item.id}`,
        { quantity },
        { preserveScroll: true },
    );
}

function removeItem(item: CartItem) {
    router.delete(`/carrito/items/${item.id}`, { preserveScroll: true });
}

const total = () =>
    props.items.reduce(
        (sum, item) => sum + item.unit_price_minor * item.quantity,
        0,
    );
</script>

<template>
    <Head title="Tu carrito — Finisher Legacy" />

    <div class="bg-fl-black">
        <div class="mx-auto max-w-4xl px-4 py-12 sm:px-6 lg:px-8">
            <h1 class="text-2xl font-black text-white">Tu carrito</h1>

            <div
                v-if="items.length"
                class="mt-8 divide-y divide-white/10 border-y border-white/10"
            >
                <div
                    v-for="item in items"
                    :key="item.id"
                    class="flex items-center justify-between gap-4 py-5"
                >
                    <div>
                        <p class="font-medium text-white">
                            {{ item.product_name }}
                        </p>
                        <p class="text-sm text-white/40">
                            {{ item.variant_name }}
                        </p>
                        <p class="mt-1 text-sm text-white/60">
                            <Money
                                :minor="item.unit_price_minor"
                                :currency="item.currency"
                            />
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <input
                            type="number"
                            min="0"
                            max="20"
                            :value="item.quantity"
                            class="w-16 rounded-lg border border-white/15 bg-fl-graphite/40 px-2 py-1 text-center text-white"
                            @change="
                                updateQuantity(
                                    item,
                                    Number(
                                        ($event.target as HTMLInputElement)
                                            .value,
                                    ),
                                )
                            "
                        />
                        <button
                            type="button"
                            class="text-white/30 hover:text-red-400"
                            @click="removeItem(item)"
                        >
                            <Trash2 class="size-4" />
                        </button>
                    </div>
                </div>
            </div>

            <div
                v-else
                class="mt-16 flex flex-col items-center gap-3 py-16 text-center text-white/30"
            >
                <ShoppingCart class="size-10" />
                <p>Tu carrito está vacío.</p>
                <Link href="/tienda" class="text-fl-gold-soft hover:underline"
                    >Ir a la tienda</Link
                >
            </div>

            <div
                v-if="items.length"
                class="mt-8 flex items-center justify-between"
            >
                <p class="text-lg text-white">
                    Total: <Money :minor="total()" :currency="currency" />
                </p>
                <Button
                    as-child
                    class="bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                >
                    <Link href="/checkout">Continuar a checkout</Link>
                </Button>
            </div>
        </div>
    </div>
</template>
