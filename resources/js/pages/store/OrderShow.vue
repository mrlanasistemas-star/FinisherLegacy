<script setup lang="ts">
/**
 * "Mi pedido": progress, what was ordered and — while the order is still
 * pending and inside its payment window — a way back to the checkout
 * payment step (the one place card payments happen).
 */
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, CheckCircle2, CreditCard } from '@lucide/vue';
import OrderStatusTimeline from '@/components/orders/OrderStatusTimeline.vue';
import Money from '@/components/shared/Money.vue';
import PaymentStatusBadge from '@/components/shared/PaymentStatusBadge.vue';
import ProductImagePlaceholder from '@/components/shared/ProductImagePlaceholder.vue';
import { Button } from '@/components/ui/button';
import { shortDate } from '@/lib/datetime';
import { index as ordersIndex } from '@/routes/store/orders';
import type { OrderProgress } from '@/types/legado';

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
    variant?: string | null;
    image_url?: string | null;
    product_slug?: string | null;
};

defineProps<{
    order: OrderDetail;
    progress?: OrderProgress;
    items: OrderItem[];
    photoDownloads?: {
        uuid: string;
        thumb_url: string;
        download_url: string;
    }[];
    payable: boolean;
}>();
</script>

<template>
    <Head :title="`Pedido #${order.order_number} — Finisher Legacy`" />

    <div class="bg-background">
        <div class="fl-container max-w-3xl py-8 md:py-12">
            <Link
                :href="ordersIndex()"
                class="inline-flex min-h-11 items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
            >
                <ArrowLeft class="size-4" /> Mis pedidos
            </Link>

            <header class="mt-2 mb-8">
                <p class="fl-eyebrow">Pedido #{{ order.order_number }}</p>
                <h1 class="mt-2 font-serif text-3xl font-semibold sm:text-4xl">
                    {{ progress?.headline ?? 'Tu pedido' }}
                </h1>
                <div
                    class="mt-3 flex flex-wrap items-center gap-2 text-sm text-muted-foreground"
                >
                    <span>Realizado el {{ shortDate(order.created_at) }}</span>
                    <PaymentStatusBadge :status="order.payment_status" />
                </div>
            </header>

            <section
                v-if="progress"
                class="fl-card mb-8 p-5 sm:p-6"
                aria-label="Seguimiento del pedido"
            >
                <OrderStatusTimeline :progress="progress" />
            </section>

            <section
                v-if="photoDownloads && photoDownloads.length"
                class="mb-8 rounded-2xl border border-emerald-200 bg-emerald-50 p-5"
            >
                <p class="font-semibold text-emerald-900">
                    Tus fotos están listas para descargar
                </p>
                <div class="mt-4 grid grid-cols-3 gap-3 sm:grid-cols-4">
                    <a
                        v-for="photo in photoDownloads"
                        :key="photo.uuid"
                        :href="photo.download_url"
                        class="group relative overflow-hidden rounded-xl"
                    >
                        <img
                            :src="photo.thumb_url"
                            alt=""
                            class="aspect-square w-full object-cover"
                        />
                        <span
                            class="absolute inset-x-1 bottom-1 rounded-full bg-white/95 py-1 text-center text-[11px] font-semibold"
                            >Descargar</span
                        >
                    </a>
                </div>
            </section>
            <h2 class="text-sm font-semibold">Lo que pediste</h2>
            <div
                class="mt-3 divide-y divide-border overflow-hidden rounded-2xl border border-border bg-card"
            >
                <div
                    v-for="(item, index) in items"
                    :key="index"
                    class="flex items-center gap-4 px-4 py-4 sm:px-5"
                >
                    <div
                        class="size-16 shrink-0 overflow-hidden rounded-xl bg-fl-cream sm:size-20"
                    >
                        <img
                            v-if="item.image_url"
                            :src="item.image_url"
                            :alt="item.name"
                            loading="lazy"
                            class="size-full object-cover"
                        />
                        <ProductImagePlaceholder
                            v-else
                            :name="item.name"
                            class="size-full"
                        />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="font-medium text-foreground">
                            {{ item.name }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            <span v-if="item.variant"
                                >{{ item.variant }} ·
                            </span>
                            {{ item.quantity }}
                            {{ item.quantity === 1 ? 'pieza' : 'piezas' }}
                        </p>
                        <span
                            v-if="item.fulfilled"
                            class="mt-1 inline-flex items-center gap-1 text-xs text-emerald-700"
                        >
                            <CheckCircle2 class="size-3.5" /> Entregado
                        </span>
                    </div>
                    <p class="shrink-0 text-foreground">
                        <Money
                            :minor="item.line_total_minor"
                            :currency="order.currency"
                        />
                    </p>
                </div>
                <div class="flex items-center justify-between px-5 py-4">
                    <p class="font-medium text-foreground">Total</p>
                    <p class="text-lg font-semibold text-fl-gold-ink">
                        <Money
                            :minor="order.total_minor"
                            :currency="order.currency"
                        />
                    </p>
                </div>
            </div>

            <section
                v-if="payable"
                class="mt-8 flex flex-col items-start gap-4 rounded-2xl border border-fl-gold/40 bg-fl-cream/60 p-5 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <p class="font-semibold text-foreground">
                        Tu pedido está apartado, falta el pago
                    </p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Paga con tarjeta de forma segura para confirmarlo.
                    </p>
                </div>
                <Button as-child class="h-11 shrink-0 rounded-full px-6">
                    <Link :href="`/checkout/${order.uuid}`">
                        <CreditCard class="size-4" /> Completar pago
                    </Link>
                </Button>
            </section>
        </div>
    </div>
</template>
