<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ShoppingCart } from '@lucide/vue';
import AdminTable from '@/components/admin/AdminTable.vue';
import Money from '@/components/shared/Money.vue';
import OrderStatusBadge from '@/components/shared/OrderStatusBadge.vue';
import PaymentStatusBadge from '@/components/shared/PaymentStatusBadge.vue';
import { Button } from '@/components/ui/button';

type OrderRow = {
    id: number;
    uuid: string;
    order_number: string;
    customer: string;
    event: string | null;
    total_minor: number;
    currency: string;
    status: string;
    payment_status: string;
    fulfillment_status: string;
    created_at: string;
};

defineProps<{
    orders: {
        data: OrderRow[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: { q: string };
}>();

const columns = [
    { key: 'order_number', label: 'Pedido' },
    { key: 'customer', label: 'Cliente' },
    { key: 'event', label: 'Evento' },
    { key: 'total_minor', label: 'Total' },
    { key: 'status', label: 'Estado' },
    { key: 'payment_status', label: 'Pago' },
    { key: 'actions', label: '' },
];
</script>

<template>
    <Head title="Pedidos" />

    <div class="p-4 md:p-8">
        <h1 class="mb-6 flex items-center gap-2 text-xl font-bold text-white">
            <ShoppingCart class="size-5 text-fl-gold" />
            Pedidos
        </h1>

        <AdminTable
            :columns="columns"
            :rows="orders"
            searchable
            :initial-query="filters.q"
        >
            <template #cell-order_number="{ row }">
                <span class="font-mono text-fl-gold-soft">{{
                    row.order_number
                }}</span>
            </template>
            <template #cell-event="{ row }">{{ row.event ?? '—' }}</template>
            <template #cell-total_minor="{ row }">
                <Money
                    :minor="row.total_minor as number"
                    :currency="row.currency as string"
                />
            </template>
            <template #cell-status="{ row }">
                <OrderStatusBadge :status="row.status as string" />
            </template>
            <template #cell-payment_status="{ row }">
                <PaymentStatusBadge :status="row.payment_status as string" />
            </template>
            <template #cell-actions="{ row }">
                <Button
                    as-child
                    size="sm"
                    variant="outline"
                    class="border-white/15 text-white hover:bg-white/10"
                >
                    <Link :href="`/admin/orders/${row.uuid}`">Ver</Link>
                </Button>
            </template>
        </AdminTable>
    </div>
</template>
