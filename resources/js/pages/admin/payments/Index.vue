<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CreditCard } from '@lucide/vue';
import AdminTable from '@/components/admin/AdminTable.vue';
import SecondaryNav from '@/components/admin/SecondaryNav.vue';
import Money from '@/components/shared/Money.vue';
import PaymentStatusBadge from '@/components/shared/PaymentStatusBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { COMMERCE_ORDERS_AREA_NAV } from '@/config/areaNav';

type PaymentRow = {
    id: number;
    order_number: string;
    order_uuid: string;
    customer: string;
    provider: string;
    method: string;
    status: string;
    amount_minor: number;
    currency: string;
    paid_at: string | null;
};

defineProps<{
    payments: {
        data: PaymentRow[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: { q: string };
}>();

const columns = [
    { key: 'order_number', label: 'Pedido' },
    { key: 'customer', label: 'Cliente' },
    { key: 'method', label: 'Método' },
    { key: 'amount_minor', label: 'Monto' },
    { key: 'status', label: 'Estado' },
    { key: 'actions', label: '' },
];
</script>

<template>
    <Head title="Pagos" />

    <div class="p-4 md:p-8">
        <SecondaryNav :items="COMMERCE_ORDERS_AREA_NAV" />

        <h1 class="mb-6 flex items-center gap-2 text-xl font-bold text-white">
            <CreditCard class="size-5 text-fl-gold" />
            Pagos
        </h1>

        <AdminTable
            :columns="columns"
            :rows="payments"
            searchable
            :initial-query="filters.q"
        >
            <template #cell-order_number="{ row }">
                <span class="font-mono text-fl-gold-soft">{{
                    row.order_number
                }}</span>
            </template>
            <template #cell-method="{ row }">
                <Badge variant="outline" class="border-white/15 text-white/60"
                    >{{ row.method }} · {{ row.provider }}</Badge
                >
            </template>
            <template #cell-amount_minor="{ row }">
                <Money
                    :minor="row.amount_minor as number"
                    :currency="row.currency as string"
                />
            </template>
            <template #cell-status="{ row }">
                <PaymentStatusBadge :status="row.status as string" />
            </template>
            <template #cell-actions="{ row }">
                <Button
                    as-child
                    size="sm"
                    variant="outline"
                    class="border-white/15 text-white hover:bg-white/10"
                >
                    <Link :href="`/admin/orders/${row.order_uuid}`"
                        >Ver pedido</Link
                    >
                </Button>
            </template>
        </AdminTable>
    </div>
</template>
