<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Receipt } from '@lucide/vue';
import Money from '@/components/shared/Money.vue';
import OrderStatusBadge from '@/components/shared/OrderStatusBadge.vue';
import PaymentStatusBadge from '@/components/shared/PaymentStatusBadge.vue';

type OrderRow = {
    uuid: string;
    order_number: string;
    status: string;
    payment_status: string;
    fulfillment_status: string;
    total_minor: number;
    currency: string;
    items_count: number;
    created_at: string;
};

defineProps<{ orders: OrderRow[] }>();
</script>

<template>
    <Head title="Mis pedidos — Finisher Legacy" />

    <div class="bg-fl-black">
        <div class="mx-auto max-w-4xl px-4 py-12 sm:px-6 lg:px-8">
            <h1 class="text-2xl font-black text-white">Mis pedidos</h1>

            <div
                v-if="orders.length"
                class="mt-8 divide-y divide-white/10 border-y border-white/10"
            >
                <Link
                    v-for="order in orders"
                    :key="order.uuid"
                    :href="`/mis-pedidos/${order.uuid}`"
                    class="flex flex-wrap items-center justify-between gap-3 py-5 hover:bg-white/[0.02]"
                >
                    <div>
                        <p class="font-medium text-white">
                            #{{ order.order_number }}
                        </p>
                        <p class="text-sm text-white/40">
                            {{ order.items_count }} artículo(s) ·
                            {{ order.created_at }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <OrderStatusBadge :status="order.status" />
                        <PaymentStatusBadge :status="order.payment_status" />
                        <p class="ml-2 text-white">
                            <Money
                                :minor="order.total_minor"
                                :currency="order.currency"
                            />
                        </p>
                    </div>
                </Link>
            </div>

            <div
                v-else
                class="mt-16 flex flex-col items-center gap-3 py-16 text-center text-white/30"
            >
                <Receipt class="size-10" />
                <p>Todavía no tienes pedidos.</p>
                <Link href="/tienda" class="text-fl-gold-soft hover:underline"
                    >Ir a la tienda</Link
                >
            </div>
        </div>
    </div>
</template>
