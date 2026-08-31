<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { CreditCard, Package2, ShoppingCart } from '@lucide/vue';
import { ref } from 'vue';
import Money from '@/components/shared/Money.vue';
import OrderStatusBadge from '@/components/shared/OrderStatusBadge.vue';
import PaymentStatusBadge from '@/components/shared/PaymentStatusBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Item = {
    id: number;
    name: string;
    sku: string;
    quantity: number;
    unit_price_minor: number;
    line_total_minor: number;
    fulfilled: boolean;
    owned_asset_code: string | null;
};

type PaymentRow = {
    id: number;
    provider: string;
    method: string;
    status: string;
    amount_minor: number;
    reference: string | null;
    paid_at: string | null;
};

const props = defineProps<{
    order: {
        id: number;
        uuid: string;
        order_number: string;
        customer: string | null;
        athlete: string | null;
        event: string | null;
        status: string;
        payment_status: string;
        fulfillment_status: string;
        subtotal_minor: number;
        total_minor: number;
        currency: string;
        created_at: string;
    };
    items: Item[];
    payments: PaymentRow[];
    inventoryLocations: { id: number; name: string }[];
    paymentMethods: string[];
}>();

const paymentDialogOpen = ref(false);
const paymentForm = useForm({
    method: 'cash',
    amount_minor: props.order.total_minor,
    reference: '',
    notes: '',
});

function submitPayment() {
    paymentForm.post(`/admin/orders/${props.order.uuid}/payments/manual`, {
        preserveScroll: true,
        onSuccess: () => (paymentDialogOpen.value = false),
    });
}

function fulfill(itemId: number, locationId: number) {
    router.post(
        `/admin/orders/items/${itemId}/fulfill`,
        { inventory_location_id: locationId },
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head :title="order.order_number" />

    <div class="p-4 md:p-8">
        <div class="mb-6 flex flex-wrap items-center gap-3">
            <h1 class="flex items-center gap-2 text-xl font-bold text-white">
                <ShoppingCart class="size-5 text-fl-gold" />
                {{ order.order_number }}
            </h1>
            <OrderStatusBadge :status="order.status" />
            <PaymentStatusBadge :status="order.payment_status" />
            <Badge variant="outline" class="border-white/15 text-white/50">{{
                order.fulfillment_status
            }}</Badge>
        </div>

        <div
            class="mb-6 grid grid-cols-2 gap-4 rounded-xl border border-white/10 bg-fl-graphite/30 p-5 text-sm sm:grid-cols-4"
        >
            <div>
                <p class="text-xs text-white/30 uppercase">Cliente</p>
                <p class="text-white">{{ order.customer ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs text-white/30 uppercase">Atleta</p>
                <p class="text-white">{{ order.athlete ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs text-white/30 uppercase">Evento</p>
                <p class="text-white">{{ order.event ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs text-white/30 uppercase">Total</p>
                <p class="text-white">
                    <Money
                        :minor="order.total_minor"
                        :currency="order.currency"
                    />
                </p>
            </div>
        </div>

        <section class="mb-6">
            <h2
                class="mb-2 flex items-center gap-1.5 text-sm font-semibold text-white/70"
            >
                <Package2 class="size-4" /> Artículos
            </h2>
            <div class="overflow-x-auto rounded-xl border border-white/10">
                <table class="w-full text-sm">
                    <thead>
                        <tr
                            class="border-b border-white/10 bg-fl-graphite/40 text-left text-xs text-white/50 uppercase"
                        >
                            <th class="px-4 py-3 font-medium">Producto</th>
                            <th class="px-4 py-3 font-medium">Cant.</th>
                            <th class="px-4 py-3 font-medium">Subtotal</th>
                            <th class="px-4 py-3 font-medium">Estado</th>
                            <th class="px-4 py-3 font-medium"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="item in items"
                            :key="item.id"
                            class="border-b border-white/5 text-white/80 last:border-0"
                        >
                            <td class="px-4 py-3">
                                {{ item.name }}
                                <span class="block text-xs text-white/30">{{
                                    item.sku
                                }}</span>
                            </td>
                            <td class="px-4 py-3">{{ item.quantity }}</td>
                            <td class="px-4 py-3">
                                <Money
                                    :minor="item.line_total_minor"
                                    :currency="order.currency"
                                />
                            </td>
                            <td class="px-4 py-3">
                                <Badge
                                    v-if="item.fulfilled"
                                    variant="outline"
                                    class="border-emerald-500/30 text-emerald-400"
                                    >Surtido</Badge
                                >
                                <Badge
                                    v-else
                                    variant="outline"
                                    class="border-amber-500/30 text-amber-400"
                                    >Pendiente</Badge
                                >
                                <span
                                    v-if="item.owned_asset_code"
                                    class="ml-2 font-mono text-xs text-fl-gold-soft"
                                    >{{ item.owned_asset_code }}</span
                                >
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Select
                                    v-if="!item.fulfilled"
                                    @update:model-value="
                                        (v) => fulfill(item.id, Number(v))
                                    "
                                >
                                    <SelectTrigger
                                        class="h-8 w-40 border-white/10 bg-fl-black text-xs text-white"
                                    >
                                        <SelectValue
                                            placeholder="Surtir desde…"
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="location in inventoryLocations"
                                            :key="location.id"
                                            :value="location.id"
                                        >
                                            {{ location.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section>
            <div class="mb-2 flex items-center justify-between">
                <h2
                    class="flex items-center gap-1.5 text-sm font-semibold text-white/70"
                >
                    <CreditCard class="size-4" /> Pagos
                </h2>
                <Button
                    v-if="order.payment_status !== 'paid'"
                    size="sm"
                    class="bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                    @click="paymentDialogOpen = true"
                >
                    Registrar pago manual
                </Button>
            </div>
            <div class="space-y-2">
                <div
                    v-for="payment in payments"
                    :key="payment.id"
                    class="rounded-lg border border-white/10 bg-fl-black/40 p-3 text-sm"
                >
                    <div class="flex items-center justify-between">
                        <span class="text-white"
                            >{{ payment.method }} ({{ payment.provider }})</span
                        >
                        <Money
                            :minor="payment.amount_minor"
                            :currency="order.currency"
                        />
                    </div>
                    <p class="mt-1 text-xs text-white/40">
                        {{ payment.status }} ·
                        {{ payment.reference ?? 'sin referencia' }} ·
                        {{ payment.paid_at ?? 'sin fecha' }}
                    </p>
                </div>
                <p v-if="!payments.length" class="text-sm text-white/30">
                    Sin pagos registrados.
                </p>
            </div>
        </section>

        <Dialog v-model:open="paymentDialogOpen">
            <DialogContent
                class="dark border-white/10 bg-fl-graphite text-white sm:max-w-md"
            >
                <DialogHeader>
                    <DialogTitle>Registrar pago manual</DialogTitle>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitPayment">
                    <div class="grid gap-2">
                        <Label>Método</Label>
                        <Select v-model="paymentForm.method">
                            <SelectTrigger
                                class="border-white/10 bg-fl-black text-white"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="cash">Efectivo</SelectItem>
                                <SelectItem value="mercado_pago_terminal"
                                    >Terminal Mercado Pago</SelectItem
                                >
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid gap-2">
                        <Label>Monto</Label>
                        <Input
                            :model-value="paymentForm.amount_minor / 100"
                            type="number"
                            class="bg-fl-black"
                            disabled
                        />
                        <p class="text-xs text-white/40">
                            El monto lo determina el servidor — no es editable.
                        </p>
                    </div>
                    <div
                        v-if="paymentForm.method === 'mercado_pago_terminal'"
                        class="grid gap-2"
                    >
                        <Label>Referencia / folio de la terminal</Label>
                        <Input
                            v-model="paymentForm.reference"
                            class="bg-fl-black"
                            required
                        />
                    </div>
                    <DialogFooter>
                        <Button
                            type="submit"
                            class="w-full bg-fl-gold text-fl-black hover:bg-fl-gold-soft sm:w-auto"
                            :disabled="paymentForm.processing"
                        >
                            Registrar pago
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
