<script setup lang="ts">
/**
 * "Mis pedidos" (spec §7/§134) — the signed-in customer's own orders, each
 * with where it is in plain words and what's inside at a glance. Renders in
 * the shared site navbar like the rest of the store.
 */
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, ShoppingBag } from '@lucide/vue';
import Pagination from '@/components/public/Pagination.vue';
import type { PaginationLink } from '@/components/public/Pagination.vue';
import Money from '@/components/shared/Money.vue';
import { Button } from '@/components/ui/button';
import { shortDate } from '@/lib/datetime';
import { show as orderShow } from '@/routes/store/orders';
import { index as storeIndex } from '@/routes/store/products';

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
    progress: string;
    preview: { name: string; image_url: string | null }[];
};

defineProps<{
    orders: {
        data: OrderRow[];
        links: PaginationLink[];
    };
}>();

function tone(order: OrderRow): string {
    if (order.status === 'cancelled' || order.payment_status === 'refunded') {
        return 'bg-muted text-muted-foreground';
    }

    if (
        order.payment_status === 'pending' ||
        order.payment_status === 'failed'
    ) {
        return 'bg-amber-100 text-amber-900';
    }

    if (order.progress === 'Entregado') {
        return 'bg-emerald-100 text-emerald-900';
    }

    return 'bg-fl-cream text-fl-gold-ink';
}
</script>

<template>
    <Head title="Mis pedidos" />

    <div class="fl-container py-6 md:py-10">
        <header>
            <p class="fl-eyebrow">Mi espacio</p>
            <h1 class="fl-display mt-2 text-4xl sm:text-5xl">Mis pedidos</h1>
            <p class="mt-3 max-w-xl text-muted-foreground">
                Todo lo que has pedido y en qué va cada pedido.
            </p>
        </header>

        <ul v-if="orders.data.length" class="mt-10 space-y-3">
            <li v-for="order in orders.data" :key="order.uuid">
                <Link
                    :href="orderShow(order.uuid)"
                    class="group fl-card flex flex-col gap-4 p-4 transition-colors hover:border-foreground/20 focus-visible:ring-2 focus-visible:ring-fl-gold focus-visible:outline-none sm:flex-row sm:items-center sm:p-5"
                >
                    <div class="flex shrink-0 -space-x-3">
                        <div
                            v-for="(item, index) in order.preview"
                            :key="index"
                            class="size-16 overflow-hidden rounded-xl border-2 border-card bg-fl-cream"
                        >
                            <img
                                v-if="item.image_url"
                                :src="item.image_url"
                                :alt="item.name"
                                loading="lazy"
                                class="size-full object-cover"
                            />
                            <span
                                v-else
                                class="flex size-full items-center justify-center font-serif text-xl text-fl-gold-ink"
                                aria-hidden="true"
                                >{{ item.name.charAt(0) }}</span
                            >
                        </div>
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                class="rounded-full px-2.5 py-1 text-xs font-semibold"
                                :class="tone(order)"
                                >{{ order.progress }}</span
                            >
                            <span class="text-xs text-muted-foreground"
                                >Pedido #{{ order.order_number }}</span
                            >
                        </div>
                        <p class="mt-2 truncate font-medium">
                            {{
                                order.preview
                                    .map((item) => item.name)
                                    .join(', ') || 'Pedido'
                            }}
                        </p>
                        <p class="mt-0.5 text-sm text-muted-foreground">
                            {{ shortDate(order.created_at) }} ·
                            {{ order.items_count }}
                            {{
                                order.items_count === 1
                                    ? 'artículo'
                                    : 'artículos'
                            }}
                        </p>
                    </div>

                    <div
                        class="flex items-center justify-between gap-4 sm:justify-end"
                    >
                        <Money
                            :minor="order.total_minor"
                            :currency="order.currency"
                            class="text-lg font-semibold"
                        />
                        <ArrowRight
                            class="size-5 text-muted-foreground transition-transform group-hover:translate-x-0.5"
                            aria-hidden="true"
                        />
                    </div>
                </Link>
            </li>
        </ul>

        <div v-if="orders.data.length" class="mt-8">
            <Pagination :links="orders.links" />
        </div>

        <div
            v-else
            class="mt-10 flex flex-col items-center gap-3 rounded-2xl border border-dashed border-foreground/15 bg-card px-6 py-16 text-center"
        >
            <ShoppingBag class="size-9 text-fl-gold-ink" />
            <p class="font-serif text-2xl">Aún no tienes pedidos.</p>
            <p class="max-w-md text-sm text-muted-foreground">
                Tu Legacy Plate, tu equipo y lo que uses para correr también
                forman parte de tu historia.
            </p>
            <Button as-child class="mt-2 rounded-full">
                <Link :href="storeIndex()">Ir a la tienda</Link>
            </Button>
        </div>
    </div>
</template>
