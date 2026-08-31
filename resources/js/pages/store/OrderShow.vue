<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CheckCircle2 } from '@lucide/vue';
import { ref } from 'vue';
import Money from '@/components/shared/Money.vue';
import OrderStatusBadge from '@/components/shared/OrderStatusBadge.vue';
import PaymentStatusBadge from '@/components/shared/PaymentStatusBadge.vue';
import { Button } from '@/components/ui/button';

type OrderDetail = {
    uuid: string;
    order_number: string;
    status: string;
    payment_status: string;
    fulfillment_status: string;
    subtotal_minor: number;
    total_minor: number;
    currency: string;
    created_at: string;
};

type OrderItem = {
    name: string;
    quantity: number;
    line_total_minor: number;
    fulfilled: boolean;
};

const props = defineProps<{
    order: OrderDetail;
    items: OrderItem[];
}>();

const paying = ref(false);
const paymentMessage = ref<string | null>(null);

function csrfToken(): string {
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

async function payOnline() {
    // A relative-URL fetch() during Inertia SSR has no browser location to
    // resolve against — see admin/plates/Show.vue for the same guard.
    if (import.meta.env.SSR) {
        return;
    }

    paying.value = true;
    paymentMessage.value = null;

    try {
        const response = await fetch(
            `/checkout/${props.order.uuid}/online-payment`,
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
            },
        );
        const data = await response.json();

        if (!data.available) {
            paymentMessage.value =
                data.message ??
                'El pago en línea no está disponible en este momento.';
        } else {
            paymentMessage.value = 'Pago en proceso.';
        }
    } catch {
        paymentMessage.value = 'No se pudo iniciar el pago. Intenta de nuevo.';
    } finally {
        paying.value = false;
    }
}
</script>

<template>
    <Head :title="`Pedido #${order.order_number} — Finisher Legacy`" />

    <div class="bg-fl-black">
        <div class="mx-auto max-w-3xl px-4 py-12 sm:px-6 lg:px-8">
            <Link
                href="/mis-pedidos"
                class="text-xs tracking-wide text-white/40 uppercase hover:text-fl-gold-soft"
                >← Mis pedidos</Link
            >

            <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-2xl font-black text-white">
                    Pedido #{{ order.order_number }}
                </h1>
                <div class="flex items-center gap-2">
                    <OrderStatusBadge :status="order.status" />
                    <PaymentStatusBadge :status="order.payment_status" />
                </div>
            </div>
            <p class="mt-1 text-sm text-white/40">{{ order.created_at }}</p>

            <div
                class="mt-8 divide-y divide-white/10 rounded-2xl border border-white/10 bg-fl-graphite/20"
            >
                <div
                    v-for="(item, index) in items"
                    :key="index"
                    class="flex items-center justify-between px-5 py-4"
                >
                    <div>
                        <p class="text-white">{{ item.name }}</p>
                        <p class="text-sm text-white/40">
                            x{{ item.quantity }}
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <span
                            v-if="item.fulfilled"
                            class="flex items-center gap-1 text-xs text-emerald-400"
                        >
                            <CheckCircle2 class="size-3.5" /> Entregado
                        </span>
                        <p class="text-white/70">
                            <Money
                                :minor="item.line_total_minor"
                                :currency="order.currency"
                            />
                        </p>
                    </div>
                </div>
                <div class="flex items-center justify-between px-5 py-4">
                    <p class="font-medium text-white">Total</p>
                    <p class="text-lg font-semibold text-fl-gold-soft">
                        <Money
                            :minor="order.total_minor"
                            :currency="order.currency"
                        />
                    </p>
                </div>
            </div>

            <div v-if="order.payment_status === 'pending'" class="mt-8">
                <Button
                    class="bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                    :disabled="paying"
                    @click="payOnline"
                >
                    Pagar en línea
                </Button>
                <p v-if="paymentMessage" class="mt-2 text-sm text-white/50">
                    {{ paymentMessage }}
                </p>
            </div>
        </div>
    </div>
</template>
