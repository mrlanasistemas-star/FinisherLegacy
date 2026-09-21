<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { TriangleAlert } from '@lucide/vue';
import { computed } from 'vue';
import Money from '@/components/shared/Money.vue';
import { Button } from '@/components/ui/button';

type CheckoutItem = {
    product_name: string;
    variant_name: string;
    quantity: number;
    line_total_minor: number | null;
    price_available: boolean;
};

const props = defineProps<{
    items: CheckoutItem[];
    subtotal_minor: number;
    discount_minor: number;
    total_minor: number;
    currency: string;
    coupon: { code: string } | null;
}>();

const hasUnavailablePrice = computed(() =>
    props.items.some((item) => !item.price_available),
);

const form = useForm({});

function placeOrder() {
    form.post('/checkout');
}
</script>

<template>
    <Head title="Checkout — Finisher Legacy" />

    <div class="min-h-screen bg-fl-black">
        <div class="mx-auto w-full max-w-2xl px-4 py-12 sm:px-6 xl:px-8">
            <h1 class="text-2xl font-black text-white">Confirmar pedido</h1>

            <div
                class="mt-8 divide-y divide-white/10 rounded-2xl border border-white/10 bg-fl-graphite/20"
            >
                <div
                    v-for="(item, index) in items"
                    :key="index"
                    class="flex items-center justify-between px-5 py-4"
                >
                    <div>
                        <p class="text-white">{{ item.product_name }}</p>
                        <p class="text-sm text-white/40">
                            {{ item.variant_name }} · x{{ item.quantity }}
                        </p>
                    </div>
                    <p class="text-white/70">
                        <Money
                            v-if="item.price_available"
                            :minor="item.line_total_minor as number"
                            :currency="currency"
                        />
                        <span
                            v-else
                            class="flex items-center gap-1 text-xs text-amber-400"
                        >
                            <TriangleAlert class="size-3.5" />
                            Sin precio disponible
                        </span>
                    </p>
                </div>
                <div
                    class="flex items-center justify-between px-5 py-4 text-sm text-white/70"
                >
                    <p>Subtotal</p>
                    <p>
                        <Money :minor="subtotal_minor" :currency="currency" />
                    </p>
                </div>
                <div
                    v-if="discount_minor > 0"
                    class="flex items-center justify-between px-5 py-4 text-sm text-emerald-400"
                >
                    <p>Descuento{{ coupon ? ` (${coupon.code})` : '' }}</p>
                    <p>
                        -<Money :minor="discount_minor" :currency="currency" />
                    </p>
                </div>
                <div class="flex items-center justify-between px-5 py-4">
                    <p class="font-medium text-white">Total</p>
                    <p class="text-lg font-semibold text-fl-gold-soft">
                        <Money :minor="total_minor" :currency="currency" />
                    </p>
                </div>
            </div>

            <p class="mt-4 text-xs text-white/40">
                El pago se resuelve de forma segura al confirmar el pedido; el
                monto siempre lo determina el servidor.
            </p>

            <p
                v-if="hasUnavailablePrice"
                class="mt-4 flex items-center gap-1.5 text-xs text-amber-400"
            >
                <TriangleAlert class="size-3.5" />
                Vuelve al carrito y quita los productos sin precio disponible
                antes de confirmar.
            </p>

            <Button
                class="mt-8 w-full bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                :disabled="
                    form.processing || !items.length || hasUnavailablePrice
                "
                @click="placeOrder"
            >
                Confirmar pedido
            </Button>
        </div>
    </div>
</template>
