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
    /** Ordered Portarretrato personalization (PortraitDesigns::forAdmin). */
    portrait_design?: {
        title: string | null;
        template_label: string;
        thumb_url: string | null;
        photo_available: boolean;
        fields: Record<string, string>;
        story_title: string | null;
        story_url: string | null;
        snapshot_at: string | null;
        downloads: { print_shop_pdf: string; frame_jpg: string };
    } | null;
    /** Tarjeta de ánimo NFC (CheerCards::forAdmin): the link it opens + assigned cards. */
    cheer_card?: {
        link: { uuid: string; title: string; state: string | null } | null;
        cards: { code: string; status: string; bound_to: string | null }[];
    } | null;
    /** Paid extra cheer message (guest order). */
    cheer_message?: { channel: string; messages: number } | null;
};

/** Labels of the words printed on the frame (PortraitDesigns::FIELDS). */
const portraitFieldLabels: Record<string, string> = {
    name: 'Nombre',
    event: 'Carrera',
    details: 'Lugar y fecha',
    time: 'Tiempo',
    distance: 'Distancia',
    position: 'Lugar',
    pace: 'Ritmo',
    bib: 'Número',
    category: 'Categoría',
    dedication: 'Dedicatoria',
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
    refundable?: boolean;
    refund_hint?: string | null;
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
        discount_minor?: number;
        coupon_code?: string | null;
        shipping_minor?: number;
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
    canRecordPayment?: boolean;
    canRefund?: boolean;
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

// Human labels for the payments list (admin stays in Spanish).
const methodLabel: Record<string, string> = {
    online_card: 'Tarjeta en línea',
    mercado_pago_terminal: 'Terminal Mercado Pago',
    cash: 'Efectivo',
    in_app_purchase: 'Compra dentro de la app',
};
const providerLabel: Record<string, string> = {
    openpay: 'OpenPay',
    stripe: 'Stripe',
    manual: 'Manual',
    app_store: 'App Store',
    google_play: 'Google Play',
};

// Full OpenPay refund. The order only changes when OpenPay confirms it
// (the server re-reads the charge); the dialog is the explicit confirmation.
const refunding = ref<PaymentRow | null>(null);
const refundForm = useForm({ reason: '' });

function openRefund(payment: PaymentRow) {
    refunding.value = payment;
    refundForm.reason = '';
    refundForm.clearErrors();
}

function submitRefund() {
    if (!refunding.value) {
        return;
    }

    refundForm.post(
        `/admin/orders/${props.order.uuid}/payments/${refunding.value.id}/refund`,
        {
            preserveScroll: true,
            onFinish: () => (refunding.value = null),
        },
    );
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

    <div class="w-full px-4 py-6 md:px-6 md:py-8 xl:px-8">
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
                <p
                    v-if="(order.shipping_minor ?? 0) > 0"
                    class="text-xs text-muted-foreground"
                >
                    Envío
                    <Money
                        :minor="order.shipping_minor ?? 0"
                        :currency="order.currency"
                    />
                    (incluido)
                </p>
                <p
                    v-if="(order.discount_minor ?? 0) > 0"
                    class="text-xs text-muted-foreground"
                >
                    Subtotal
                    <Money
                        :minor="order.subtotal_minor"
                        :currency="order.currency"
                    />
                    · descuento
                    <template v-if="order.coupon_code"
                        >({{ order.coupon_code }})</template
                    >
                    −<Money
                        :minor="order.discount_minor ?? 0"
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
                        <!-- Tarjeta de ánimo NFC: assigning a card at fulfillment binds it to this link. -->
                        <div
                            v-if="item.cheer_card"
                            class="mt-3 rounded-lg border border-fl-gold/30 bg-fl-cream/60 p-3 text-xs"
                        >
                            <p class="font-semibold text-foreground">
                                Tarjeta de ánimo ·
                                {{
                                    item.cheer_card.link
                                        ? `abre «${item.cheer_card.link.title}»`
                                        : 'sin enlace elegido (el cliente la vincula en la app)'
                                }}
                            </p>
                            <p
                                v-if="item.cheer_card.link?.state === 'revoked'"
                                class="mt-0.5 text-amber-800"
                            >
                                El cliente desactivó ese enlace: la tarjeta
                                llegará sin vincular.
                            </p>
                            <ul
                                v-if="item.cheer_card.cards.length"
                                class="mt-1.5 space-y-0.5 text-muted-foreground"
                            >
                                <li
                                    v-for="card in item.cheer_card.cards"
                                    :key="card.code"
                                >
                                    <span class="font-mono text-foreground">{{
                                        card.code
                                    }}</span>
                                    · {{ card.status }} ·
                                    {{
                                        card.bound_to
                                            ? `vinculada a «${card.bound_to}»`
                                            : 'sin vincular'
                                    }}
                                </li>
                            </ul>
                            <p v-else class="mt-0.5 text-muted-foreground">
                                Al surtir, la pieza asignada queda vinculada a
                                este enlace y llega lista.
                            </p>
                        </div>
                        <p
                            v-if="item.cheer_message"
                            class="mt-2 text-xs text-muted-foreground"
                        >
                            Mensaje de ánimo pagado por quien lo envía ·
                            {{
                                item.cheer_message.channel === 'nfc_card'
                                    ? 'desde Tarjeta NFC'
                                    : 'desde el enlace'
                            }}
                            · {{ item.cheer_message.messages }}
                            {{
                                item.cheer_message.messages === 1
                                    ? 'mensaje'
                                    : 'mensajes'
                            }}. Se entrega solo al confirmarse el pago.
                        </p>
                        <!-- Portarretrato: the customer's design, frozen at checkout, for production. -->
                        <div
                            v-if="item.portrait_design"
                            class="mt-3 flex gap-3 rounded-lg border border-fl-gold/30 bg-fl-cream/60 p-3"
                        >
                            <img
                                v-if="item.portrait_design.thumb_url"
                                :src="item.portrait_design.thumb_url"
                                alt=""
                                class="size-16 shrink-0 rounded-md object-cover"
                            />
                            <div class="min-w-0 flex-1 text-xs">
                                <p class="font-semibold text-foreground">
                                    Personalización ·
                                    {{
                                        item.portrait_design.title ||
                                        item.portrait_design.template_label
                                    }}
                                </p>
                                <p class="text-muted-foreground">
                                    Plantilla
                                    {{ item.portrait_design.template_label }}
                                    <template
                                        v-if="item.portrait_design.story_url"
                                    >
                                        ·
                                        <a
                                            :href="
                                                item.portrait_design.story_url
                                            "
                                            target="_blank"
                                            rel="noopener"
                                            class="underline"
                                            >{{
                                                item.portrait_design
                                                    .story_title ?? 'Recuerdo'
                                            }}</a
                                        >
                                    </template>
                                </p>
                                <dl
                                    class="mt-1.5 grid grid-cols-[auto_1fr] gap-x-2 gap-y-0.5"
                                >
                                    <template
                                        v-for="(value, key) in item
                                            .portrait_design.fields"
                                        :key="key"
                                    >
                                        <dt class="text-muted-foreground">
                                            {{
                                                portraitFieldLabels[key] ?? key
                                            }}
                                        </dt>
                                        <dd class="break-words text-foreground">
                                            {{ value }}
                                        </dd>
                                    </template>
                                </dl>
                                <div
                                    v-if="item.portrait_design.photo_available"
                                    class="mt-2 flex flex-wrap gap-3"
                                >
                                    <a
                                        :href="
                                            item.portrait_design.downloads
                                                .print_shop_pdf
                                        "
                                        class="font-medium text-fl-gold-ink underline"
                                        >Archivo para imprenta (PDF)</a
                                    >
                                    <a
                                        :href="
                                            item.portrait_design.downloads
                                                .frame_jpg
                                        "
                                        class="font-medium text-fl-gold-ink underline"
                                        >Tamaño exacto (JPG)</a
                                    >
                                </div>
                                <p v-else class="mt-2 text-amber-800">
                                    La foto de este diseño ya no está en el
                                    recuerdo del cliente.
                                </p>
                            </div>
                        </div>
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
                    v-if="canRecordPayment"
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
                            >{{
                                methodLabel[payment.method] ?? payment.method
                            }}
                            ({{
                                providerLabel[payment.provider] ??
                                payment.provider
                            }})</span
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
                    <div
                        v-if="
                            (canRefund && payment.refundable) ||
                            payment.refund_hint
                        "
                        class="mt-2 flex flex-wrap items-center justify-between gap-2"
                    >
                        <p
                            v-if="payment.refund_hint"
                            class="text-xs text-muted-foreground"
                        >
                            {{ payment.refund_hint }}
                        </p>
                        <Button
                            v-if="canRefund && payment.refundable"
                            variant="outline"
                            size="sm"
                            class="ml-auto h-9 rounded-full border-red-300 text-red-700 hover:bg-red-50"
                            @click="openRefund(payment)"
                        >
                            <Undo2 class="size-3.5" />
                            Reembolsar
                        </Button>
                    </div>
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

        <Dialog
            :open="refunding !== null"
            @update:open="(v) => !v && (refunding = null)"
        >
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Reembolsar pago</DialogTitle>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitRefund">
                    <p class="text-sm text-muted-foreground">
                        Se devolverá el total de
                        <Money
                            v-if="refunding"
                            :minor="refunding.amount_minor"
                            :currency="order.currency"
                        />
                        a la tarjeta con OpenPay. No se puede deshacer. El
                        pedido cambia a «reembolsado» solo cuando OpenPay lo
                        confirma; si quedan piezas sin entregar, el pedido se
                        cancela y vuelven al inventario.
                    </p>
                    <div class="grid gap-2">
                        <Label for="refund-reason">Motivo (opcional)</Label>
                        <Input
                            id="refund-reason"
                            v-model="refundForm.reason"
                            maxlength="200"
                            placeholder="Ej. el cliente canceló su compra"
                        />
                        <p
                            v-if="refundForm.errors.reason"
                            class="text-xs text-red-700"
                        >
                            {{ refundForm.errors.reason }}
                        </p>
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="ghost"
                            class="w-full rounded-full sm:w-auto"
                            @click="refunding = null"
                            >Cancelar</Button
                        >
                        <Button
                            type="submit"
                            class="w-full rounded-full bg-red-700 text-white hover:bg-red-800 sm:w-auto"
                            :disabled="refundForm.processing"
                            >{{
                                refundForm.processing
                                    ? 'Reembolsando…'
                                    : 'Confirmar reembolso'
                            }}</Button
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
