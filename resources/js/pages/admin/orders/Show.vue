<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { CreditCard, Package2, ShoppingCart, Undo2 } from '@lucide/vue';
import { ref } from 'vue';
import OrderCommercePanel from '@/components/admin/orders/OrderCommercePanel.vue';
import type {
    OrderCommerce,
    OrderUnit,
} from '@/components/admin/orders/OrderCommercePanel.vue';
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
import {
    fulfillmentStatus,
    paymentStatus,
    statusClass,
    statusLabel,
} from '@/lib/statusLabels';

type Item = {
    id: number;
    name: string;
    sku: string;
    quantity: number;
    unit_price_minor: number;
    line_total_minor: number;
    fulfilled: boolean;
    owned_asset_code: string | null;
    returned_quantity: number;
};

type Delivery = {
    method: 'pickup' | 'shipping';
    address: {
        line: string | null;
        neighborhood: string | null;
        city: string | null;
        state: string | null;
        postal_code: string | null;
    } | null;
} | null;

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
        contact?: {
            name: string | null;
            email: string | null;
            phone: string | null;
        };
        delivery?: Delivery;
    } & Omit<OrderCommerce, 'uuid'>;
    items: Item[];
    units?: OrderUnit[];
    deliveryMethods?: { value: string; label: string }[];
    canManage?: boolean;
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

// Return of delivered units → inverse inventory movement (audited).
const returning = ref<Item | null>(null);
const returnForm = useForm({ quantity: 1, notes: '' });

function openReturn(item: Item) {
    returning.value = item;
    returnForm.quantity = 1;
    returnForm.notes = '';
    returnForm.clearErrors();
}

function submitReturn() {
    if (!returning.value) {
        return;
    }

    returnForm.post(`/admin/orders/items/${returning.value.id}/return`, {
        preserveScroll: true,
        onSuccess: () => (returning.value = null),
    });
}

const addressLine = (delivery: Delivery) =>
    delivery?.address
        ? [
              delivery.address.line,
              delivery.address.neighborhood,
              delivery.address.city,
              delivery.address.state,
              delivery.address.postal_code,
          ]
              .filter(Boolean)
              .join(', ')
        : null;
</script>

<template>
    <Head :title="order.order_number" />

    <div class="p-4 md:p-8">
        <div class="mb-6 flex flex-wrap items-center gap-3">
            <h1
                class="flex items-center gap-2 text-xl font-bold text-foreground"
            >
                <ShoppingCart class="size-5 text-fl-gold-ink" />
                {{ order.order_number }}
            </h1>
            <OrderStatusBadge :status="order.status" />
            <PaymentStatusBadge :status="order.payment_status" />
            <Badge
                variant="outline"
                :class="
                    statusClass(fulfillmentStatus, order.fulfillment_status)
                "
                >{{
                    statusLabel(fulfillmentStatus, order.fulfillment_status)
                }}</Badge
            >
        </div>

        <div
            class="mb-6 grid grid-cols-2 gap-4 rounded-xl border border-border bg-card/30 p-5 text-sm sm:grid-cols-4"
        >
            <div>
                <p class="text-xs text-muted-foreground/80 uppercase">
                    Cliente
                </p>
                <p class="text-foreground">{{ order.customer ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs text-muted-foreground/80 uppercase">Atleta</p>
                <p class="text-foreground">{{ order.athlete ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs text-muted-foreground/80 uppercase">Evento</p>
                <p class="text-foreground">{{ order.event ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs text-muted-foreground/80 uppercase">Total</p>
                <p class="text-foreground">
                    <Money
                        :minor="order.total_minor"
                        :currency="order.currency"
                    />
                </p>
            </div>
        </div>

        <section
            v-if="order.contact || order.delivery"
            class="mb-6 grid gap-4 rounded-xl border border-border bg-card p-5 text-sm sm:grid-cols-2"
        >
            <div v-if="order.contact">
                <p class="text-xs text-muted-foreground/80 uppercase">
                    Contacto
                </p>
                <p class="text-foreground">{{ order.contact.name ?? '—' }}</p>
                <p v-if="order.contact.email" class="text-muted-foreground">
                    {{ order.contact.email }}
                </p>
                <a
                    v-if="order.contact.phone"
                    :href="`tel:${order.contact.phone}`"
                    class="text-fl-gold-ink underline-offset-4 hover:underline"
                    >{{ order.contact.phone }}</a
                >
            </div>
            <div v-if="order.delivery">
                <p class="text-xs text-muted-foreground/80 uppercase">
                    Entrega
                </p>
                <p class="text-foreground">
                    {{
                        order.delivery.method === 'shipping'
                            ? 'Envío a domicilio'
                            : 'Entrega en mano / recoger'
                    }}
                </p>
                <p
                    v-if="addressLine(order.delivery)"
                    class="text-muted-foreground"
                >
                    {{ addressLine(order.delivery) }}
                </p>
            </div>
        </section>

        <OrderCommercePanel
            :order="order"
            :units="units ?? []"
            :delivery-methods="deliveryMethods ?? []"
            :can-manage="canManage ?? false"
        />

        <section class="mb-6">
            <h2
                class="mb-2 flex items-center gap-1.5 text-sm font-semibold text-muted-foreground"
            >
                <Package2 class="size-4" /> Artículos
            </h2>
            <ul class="space-y-2">
                <li
                    v-for="item in items"
                    :key="item.id"
                    class="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-xl border border-border bg-card p-4 text-sm"
                >
                    <div class="min-w-0 flex-1 basis-48">
                        <p class="font-medium text-foreground">
                            {{ item.name }}
                        </p>
                        <p class="text-xs text-muted-foreground/80">
                            {{ item.sku }} · {{ item.quantity }} ×
                            <Money
                                :minor="item.unit_price_minor"
                                :currency="order.currency"
                            />
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <Badge
                            v-if="item.fulfilled"
                            variant="outline"
                            class="border-emerald-500/30 text-emerald-700"
                            >Entregado</Badge
                        >
                        <Badge
                            v-else
                            variant="outline"
                            class="border-amber-500/30 text-amber-700"
                            >Por entregar</Badge
                        >
                        <Badge
                            v-if="item.returned_quantity > 0"
                            variant="outline"
                            class="border-foreground/15 text-muted-foreground"
                            >{{ item.returned_quantity }} devuelto{{
                                item.returned_quantity === 1 ? '' : 's'
                            }}</Badge
                        >
                        <span
                            v-if="item.owned_asset_code"
                            class="font-mono text-xs text-fl-gold-ink"
                            >{{ item.owned_asset_code }}</span
                        >
                    </div>
                    <Money
                        class="legacy-numeric font-semibold"
                        :minor="item.line_total_minor"
                        :currency="order.currency"
                    />
                    <div class="flex w-full justify-end gap-2 sm:w-auto">
                        <Select
                            v-if="
                                !item.fulfilled && order.status !== 'cancelled'
                            "
                            @update:model-value="
                                (v) => fulfill(item.id, Number(v))
                            "
                        >
                            <SelectTrigger
                                class="h-10 w-44 border-border bg-background text-xs text-foreground"
                                aria-label="Marcar entregado desde almacén"
                            >
                                <SelectValue placeholder="Entregar desde…" />
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
                        <Button
                            v-if="
                                item.fulfilled &&
                                item.returned_quantity < item.quantity
                            "
                            variant="outline"
                            size="sm"
                            class="h-10 rounded-full"
                            @click="openReturn(item)"
                        >
                            <Undo2 class="size-3.5" />
                            Devolución
                        </Button>
                    </div>
                </li>
            </ul>
        </section>

        <section>
            <div class="mb-2 flex items-center justify-between">
                <h2
                    class="flex items-center gap-1.5 text-sm font-semibold text-muted-foreground"
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
                    class="rounded-lg border border-border bg-background/40 p-3 text-sm"
                >
                    <div class="flex items-center justify-between">
                        <span class="text-foreground"
                            >{{ payment.method }} ({{ payment.provider }})</span
                        >
                        <Money
                            :minor="payment.amount_minor"
                            :currency="order.currency"
                        />
                    </div>
                    <p class="mt-1 text-xs text-muted-foreground/80">
                        {{ statusLabel(paymentStatus, payment.status) }} ·
                        {{ payment.reference ?? 'sin referencia' }} ·
                        {{ payment.paid_at ?? 'sin fecha' }}
                    </p>
                </div>
                <p
                    v-if="!payments.length"
                    class="text-sm text-muted-foreground/80"
                >
                    Sin pagos registrados.
                </p>
            </div>
        </section>

        <Dialog
            :open="returning !== null"
            @update:open="(v) => !v && (returning = null)"
        >
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Registrar devolución</DialogTitle>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitReturn">
                    <p class="text-sm text-muted-foreground">
                        {{ returning?.name }} — las unidades vuelven al
                        inventario. El reembolso del dinero se hace aparte.
                    </p>
                    <div class="grid gap-2">
                        <Label for="return-qty">Unidades devueltas</Label>
                        <Input
                            id="return-qty"
                            v-model.number="returnForm.quantity"
                            type="number"
                            min="1"
                            :max="
                                returning
                                    ? returning.quantity -
                                      returning.returned_quantity
                                    : 1
                            "
                            class="h-11"
                            required
                        />
                        <p
                            v-if="returnForm.errors.quantity"
                            class="text-xs text-red-700"
                        >
                            {{ returnForm.errors.quantity }}
                        </p>
                    </div>
                    <div class="grid gap-2">
                        <Label for="return-notes">Motivo (opcional)</Label>
                        <Input
                            id="return-notes"
                            v-model="returnForm.notes"
                            maxlength="255"
                            placeholder="Ej. talla equivocada"
                        />
                    </div>
                    <DialogFooter>
                        <Button
                            type="submit"
                            class="w-full rounded-full sm:w-auto"
                            :disabled="returnForm.processing"
                            >Registrar devolución</Button
                        >
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="paymentDialogOpen">
            <DialogContent
                class="border-border bg-card text-foreground sm:max-w-md"
            >
                <DialogHeader>
                    <DialogTitle>Registrar pago manual</DialogTitle>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitPayment">
                    <div class="grid gap-2">
                        <Label>Método</Label>
                        <Select v-model="paymentForm.method">
                            <SelectTrigger
                                class="border-border bg-background text-foreground"
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
                            class="bg-background"
                            disabled
                        />
                        <p class="text-xs text-muted-foreground/80">
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
                            class="bg-background"
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
