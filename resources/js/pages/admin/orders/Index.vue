<script setup lang="ts">
/**
 * Pedidos as a kanban board — Por pagar → Por preparar → En preparación
 * → Entregados (+ Cancelados). Lanes come straight from the real order /
 * payment / fulfillment states (Admin\Store\OrderController::index).
 */
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Ban,
    Camera,
    CheckCircle2,
    Clock,
    CreditCard,
    Flag,
    PackageOpen,
    Search,
    ShoppingCart,
    Truck,
    UserRound,
} from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { computed, ref } from 'vue';
import KanbanBoard from '@/components/admin/KanbanBoard.vue';
import type { KanbanLane } from '@/components/admin/KanbanBoard.vue';
import SecondaryNav from '@/components/admin/SecondaryNav.vue';
import Money from '@/components/shared/Money.vue';
import { COMMERCE_ORDERS_AREA_NAV } from '@/config/areaNav';
import { timeAgo } from '@/lib/datetime';

type OrderCard = {
    id: number;
    uuid: string;
    order_number: string;
    customer: string | null;
    event: string | null;
    total_minor: number;
    currency: string;
    payment_status: string;
    fulfillment_status: string;
    items_count: number;
    items_preview: string[];
    has_photos: boolean;
    created_at: string;
};

const props = defineProps<{
    board: {
        key: string;
        count: number;
        total_minor: number;
        orders: OrderCard[];
    }[];
    filters: { q: string };
}>();

const laneMeta: Record<
    string,
    { title: string; icon: typeof Clock; accent: string }
> = {
    to_pay: {
        title: 'Por pagar',
        icon: CreditCard,
        accent: 'bg-amber-100 text-amber-700',
    },
    to_prepare: {
        title: 'Por preparar',
        icon: PackageOpen,
        accent: 'bg-sky-100 text-sky-700',
    },
    in_progress: {
        title: 'En preparación',
        icon: Truck,
        accent: 'bg-violet-100 text-violet-700',
    },
    done: {
        title: 'Entregados',
        icon: CheckCircle2,
        accent: 'bg-emerald-100 text-emerald-700',
    },
    cancelled: {
        title: 'Cancelados',
        icon: Ban,
        accent: 'bg-muted text-muted-foreground',
    },
};

const money = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    maximumFractionDigits: 0,
});

const lanes = computed<KanbanLane<OrderCard>[]>(() =>
    props.board.map((lane) => ({
        key: lane.key,
        title: laneMeta[lane.key]?.title ?? lane.key,
        subtitle: money.format(lane.total_minor / 100),
        icon: laneMeta[lane.key]?.icon ?? Clock,
        accent: laneMeta[lane.key]?.accent ?? 'bg-muted',
        count: lane.count,
        items: lane.orders,
    })),
);

const query = ref(props.filters.q ?? '');
const search = useDebounceFn(() => {
    router.get(
        '/admin/orders',
        { q: query.value || undefined },
        { preserveState: true, replace: true },
    );
}, 350);
</script>

<template>
    <Head title="Pedidos" />

    <div class="w-full p-4 md:p-8">
        <SecondaryNav :items="COMMERCE_ORDERS_AREA_NAV" />

        <div
            class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-3">
                <span
                    class="flex size-11 items-center justify-center rounded-xl bg-fl-cream text-fl-gold-ink"
                >
                    <ShoppingCart class="size-5" />
                </span>
                <div>
                    <h1 class="text-xl font-semibold">Pedidos</h1>
                    <p class="text-sm text-muted-foreground">
                        Cada pedido avanza de izquierda a derecha.
                    </p>
                </div>
            </div>
            <div class="relative">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <input
                    v-model="query"
                    type="search"
                    placeholder="Número o cliente…"
                    aria-label="Buscar pedido"
                    class="h-10 w-64 rounded-full border border-input bg-card pr-4 pl-10 text-sm outline-none hover:border-foreground/25 focus:border-fl-gold focus:ring-4 focus:ring-fl-gold/15"
                    @input="search"
                />
            </div>
        </div>

        <KanbanBoard :lanes="lanes" :item-key="(order) => order.id">
            <template #card="{ item: order }">
                <Link
                    :href="`/admin/orders/${order.uuid}`"
                    class="block rounded-xl border border-border bg-card p-3.5 shadow-[0_1px_2px_rgb(23_23_20/0.04)] transition-[border-color,transform] hover:-translate-y-0.5 hover:border-foreground/25"
                >
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-mono text-xs font-semibold">{{
                            order.order_number
                        }}</span>
                        <span class="text-[11px] text-muted-foreground">{{
                            timeAgo(order.created_at)
                        }}</span>
                    </div>
                    <p
                        class="mt-2 flex items-center gap-1.5 text-sm font-medium"
                    >
                        <UserRound class="size-3.5 text-muted-foreground" />
                        <span class="truncate">{{
                            order.customer ?? 'Invitado'
                        }}</span>
                    </p>
                    <p
                        v-if="order.event"
                        class="mt-1 flex items-center gap-1.5 text-xs text-muted-foreground"
                    >
                        <Flag class="size-3" />
                        <span class="truncate">{{ order.event }}</span>
                    </p>
                    <ul class="mt-2 space-y-0.5">
                        <li
                            v-for="name in order.items_preview"
                            :key="name"
                            class="truncate text-xs text-muted-foreground"
                        >
                            · {{ name }}
                        </li>
                    </ul>
                    <div
                        class="mt-3 flex items-center justify-between border-t border-border pt-2.5"
                    >
                        <span
                            class="inline-flex items-center gap-1 text-xs text-muted-foreground"
                        >
                            <Camera
                                v-if="order.has_photos"
                                class="size-3.5 text-fl-gold-ink"
                            />
                            {{ order.items_count }} artículo{{
                                order.items_count === 1 ? '' : 's'
                            }}
                        </span>
                        <Money
                            :minor="order.total_minor"
                            :currency="order.currency"
                            class="text-sm font-semibold"
                        />
                    </div>
                </Link>
            </template>
        </KanbanBoard>
    </div>
</template>
