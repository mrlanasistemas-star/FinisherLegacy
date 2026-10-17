<script setup lang="ts">
/**
 * Checkout (spec §28): one clean page — 1 Tus datos, 2 Entrega, 3 Pago —
 * with the order summary always visible. Step "details" creates the Order
 * (server totals, stock reserved); step "payment" is the same page for that
 * Order with the Payment Element. Explicit states: loading, processing,
 * confirmed (on the confirmation page, from server state) and error.
 */
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    Check,
    Clock,
    Lock,
    MapPin,
    Nfc,
    ShoppingBag,
    Store,
    Truck,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import OpenPayCardForm from '@/components/commerce/OpenPayCardForm.vue';
import StripePaymentElement from '@/components/commerce/StripePaymentElement.vue';
import InputError from '@/components/InputError.vue';
import Money from '@/components/shared/Money.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import type { CheckoutOrderState } from '@/lib/commerce';

type Line = {
    id: number;
    quantity: number;
    product_name: string;
    variant_name: string;
    size: string | null;
    color: string | null;
    line_total_minor: number | null;
    currency: string;
    price_available: boolean;
    in_stock: boolean;
    image_url: string | null;
    event_edition_name: string | null;
    connected: boolean;
};

const props = defineProps<{
    step: 'details' | 'payment';
    items: Line[];
    subtotal_minor: number;
    discount_minor: number;
    total_minor: number;
    currency: string;
    coupon: { code: string } | null;
    requires_shipping: boolean;
    contact: {
        name: string | null;
        email: string | null;
        phone: string | null;
    };
    delivery?: {
        method: 'pickup' | 'shipping';
        address: Record<string, string | null> | null;
    } | null;
    payment: {
        provider: 'stripe' | 'openpay' | null;
        publishable_key: string | null;
        openpay: {
            merchant_id: string;
            public_key: string;
            sandbox: boolean;
        } | null;
        expiry_minutes: number;
    };
    order: CheckoutOrderState | null;
}>();

const blocked = computed(() =>
    props.items.some((item) => !item.price_available || !item.in_stock),
);

const amountLabel = computed(() =>
    new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: props.currency,
    }).format(props.total_minor / 100),
);

// --- Step 1 + 2: datos y entrega --------------------------------------------

const form = useForm({
    name: props.contact.name ?? '',
    email: props.contact.email ?? '',
    phone: props.contact.phone ?? '',
    delivery_method: (props.requires_shipping ? 'shipping' : 'pickup') as
        'pickup' | 'shipping',
    address_line: '',
    address_neighborhood: '',
    address_city: '',
    address_state: '',
    address_postal_code: '',
    notes: '',
});

function placeOrder() {
    form.post('/checkout', { preserveScroll: true });
}

// --- Step 3: pago ----------------------------------------------------------

const paymentState = ref<
    'loading' | 'ready' | 'processing' | 'error' | 'unavailable'
>('loading');
const returnUrl = computed(() =>
    typeof window === 'undefined' || !props.order
        ? ''
        : `${window.location.origin}/checkout/${props.order.uuid}/confirmacion`,
);

function goToConfirmation() {
    if (props.order) {
        router.visit(`/checkout/${props.order.uuid}/confirmacion`);
    }
}

// Reservation countdown ("apartamos tus piezas").
const now = ref(Date.now());
let timer: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
    timer = setInterval(() => (now.value = Date.now()), 30_000);
});
onBeforeUnmount(() => timer && clearInterval(timer));

const minutesLeft = computed(() =>
    props.order?.expires_at
        ? Math.max(
              0,
              Math.round(
                  (new Date(props.order.expires_at).getTime() - now.value) /
                      60_000,
              ),
          )
        : null,
);

const deliveryText = computed(() => {
    if (!props.delivery) {
        return null;
    }

    if (props.delivery.method === 'pickup') {
        return 'Entrega en mano / recoger';
    }

    return Object.values(props.delivery.address ?? {})
        .filter(Boolean)
        .join(', ');
});

const lineDetails = (line: Line) =>
    [line.size ? `Talla ${line.size}` : null, line.color]
        .filter(Boolean)
        .join(' · ') ||
    (line.variant_name !== line.product_name ? line.variant_name : '');
</script>

<template>
    <Head title="Checkout" />

    <div class="min-h-screen bg-background">
        <div class="fl-container py-10 md:py-14">
            <header class="mb-8">
                <p class="fl-eyebrow">Checkout</p>
                <h1 class="fl-display mt-2 text-3xl md:text-4xl">
                    {{ step === 'details' ? 'Confirma tu pedido' : 'Pago' }}
                </h1>
                <p
                    v-if="order && minutesLeft !== null"
                    class="mt-2 inline-flex items-center gap-1.5 text-sm text-muted-foreground"
                >
                    <Clock class="size-4" />
                    Pedido #{{ order.order_number }} · apartamos tus piezas
                    {{
                        minutesLeft > 0
                            ? `${minutesLeft} min más`
                            : 'unos minutos más'
                    }}.
                </p>
            </header>

            <div
                class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_400px] lg:gap-12"
            >
                <div class="space-y-6">
                    <!-- 1 · Datos -->
                    <section class="fl-card p-5 md:p-7">
                        <h2
                            class="mb-5 flex items-center gap-3 text-lg font-semibold"
                        >
                            <span
                                class="flex size-7 items-center justify-center rounded-full text-sm"
                                :class="
                                    step === 'payment'
                                        ? 'bg-emerald-600 text-white'
                                        : 'bg-foreground text-background'
                                "
                            >
                                <Check
                                    v-if="step === 'payment'"
                                    class="size-4"
                                />
                                <template v-else>1</template>
                            </span>
                            Tus datos
                        </h2>

                        <div
                            v-if="step === 'payment'"
                            class="text-sm text-muted-foreground"
                        >
                            <p class="text-foreground">{{ contact.name }}</p>
                            <p>
                                {{ contact.email
                                }}{{
                                    contact.phone ? ` · ${contact.phone}` : ''
                                }}
                            </p>
                        </div>

                        <div v-else class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-1.5 sm:col-span-2">
                                <Label for="co-name">Nombre completo</Label>
                                <Input
                                    id="co-name"
                                    v-model="form.name"
                                    autocomplete="name"
                                    class="h-11"
                                    required
                                />
                                <InputError :message="form.errors.name" />
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="co-email">Correo</Label>
                                <Input
                                    id="co-email"
                                    v-model="form.email"
                                    type="email"
                                    autocomplete="email"
                                    class="h-11"
                                    required
                                />
                                <InputError :message="form.errors.email" />
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="co-phone">
                                    Teléfono
                                    <span
                                        v-if="
                                            form.delivery_method !== 'shipping'
                                        "
                                        class="font-normal text-muted-foreground"
                                        >(opcional)</span
                                    >
                                </Label>
                                <Input
                                    id="co-phone"
                                    v-model="form.phone"
                                    type="tel"
                                    autocomplete="tel"
                                    class="h-11"
                                />
                                <InputError :message="form.errors.phone" />
                            </div>
                        </div>
                    </section>

                    <!-- 2 · Entrega -->
                    <section class="fl-card p-5 md:p-7">
                        <h2
                            class="mb-5 flex items-center gap-3 text-lg font-semibold"
                        >
                            <span
                                class="flex size-7 items-center justify-center rounded-full text-sm"
                                :class="
                                    step === 'payment'
                                        ? 'bg-emerald-600 text-white'
                                        : 'bg-foreground text-background'
                                "
                            >
                                <Check
                                    v-if="step === 'payment'"
                                    class="size-4"
                                />
                                <template v-else>2</template>
                            </span>
                            Entrega
                        </h2>

                        <p
                            v-if="step === 'payment'"
                            class="flex items-start gap-2 text-sm text-muted-foreground"
                        >
                            <MapPin class="mt-0.5 size-4 shrink-0" />
                            {{ deliveryText ?? 'Entrega en mano / recoger' }}
                        </p>

                        <template v-else>
                            <div
                                class="grid gap-3 sm:grid-cols-2"
                                role="radiogroup"
                                aria-label="Método de entrega"
                            >
                                <button
                                    type="button"
                                    role="radio"
                                    :aria-checked="
                                        form.delivery_method === 'pickup'
                                    "
                                    class="flex min-h-20 items-start gap-3 rounded-control border p-4 text-left transition"
                                    :class="
                                        form.delivery_method === 'pickup'
                                            ? 'border-foreground ring-1 ring-foreground'
                                            : 'border-border hover:border-foreground/40'
                                    "
                                    @click="form.delivery_method = 'pickup'"
                                >
                                    <Store class="mt-0.5 size-5 shrink-0" />
                                    <span>
                                        <span class="block font-medium"
                                            >Recoger / entrega en mano</span
                                        >
                                        <span
                                            class="text-sm text-muted-foreground"
                                            >En tu evento o punto de entrega.
                                            Sin costo.</span
                                        >
                                    </span>
                                </button>
                                <button
                                    type="button"
                                    role="radio"
                                    :aria-checked="
                                        form.delivery_method === 'shipping'
                                    "
                                    class="flex min-h-20 items-start gap-3 rounded-control border p-4 text-left transition"
                                    :class="
                                        form.delivery_method === 'shipping'
                                            ? 'border-foreground ring-1 ring-foreground'
                                            : 'border-border hover:border-foreground/40'
                                    "
                                    @click="form.delivery_method = 'shipping'"
                                >
                                    <Truck class="mt-0.5 size-5 shrink-0" />
                                    <span>
                                        <span class="block font-medium"
                                            >Envío a domicilio</span
                                        >
                                        <span
                                            class="text-sm text-muted-foreground"
                                            >Te contactamos para coordinar la
                                            paquetería.</span
                                        >
                                    </span>
                                </button>
                            </div>

                            <div
                                v-if="form.delivery_method === 'shipping'"
                                class="mt-5 grid gap-4 sm:grid-cols-2"
                            >
                                <div class="grid gap-1.5 sm:col-span-2">
                                    <Label for="co-line">Calle y número</Label>
                                    <Input
                                        id="co-line"
                                        v-model="form.address_line"
                                        autocomplete="address-line1"
                                        class="h-11"
                                    />
                                    <InputError
                                        :message="form.errors.address_line"
                                    />
                                </div>
                                <div class="grid gap-1.5">
                                    <Label for="co-col">Colonia</Label>
                                    <Input
                                        id="co-col"
                                        v-model="form.address_neighborhood"
                                        autocomplete="address-line2"
                                        class="h-11"
                                    />
                                </div>
                                <div class="grid gap-1.5">
                                    <Label for="co-cp">Código postal</Label>
                                    <Input
                                        id="co-cp"
                                        v-model="form.address_postal_code"
                                        inputmode="numeric"
                                        maxlength="5"
                                        autocomplete="postal-code"
                                        class="h-11"
                                    />
                                    <InputError
                                        :message="
                                            form.errors.address_postal_code
                                        "
                                    />
                                </div>
                                <div class="grid gap-1.5">
                                    <Label for="co-city">Ciudad</Label>
                                    <Input
                                        id="co-city"
                                        v-model="form.address_city"
                                        autocomplete="address-level2"
                                        class="h-11"
                                    />
                                    <InputError
                                        :message="form.errors.address_city"
                                    />
                                </div>
                                <div class="grid gap-1.5">
                                    <Label for="co-state">Estado</Label>
                                    <Input
                                        id="co-state"
                                        v-model="form.address_state"
                                        autocomplete="address-level1"
                                        class="h-11"
                                    />
                                    <InputError
                                        :message="form.errors.address_state"
                                    />
                                </div>
                            </div>

                            <div class="mt-5 grid gap-1.5">
                                <Label for="co-notes"
                                    >Notas para la entrega
                                    <span
                                        class="font-normal text-muted-foreground"
                                        >(opcional)</span
                                    ></Label
                                >
                                <Textarea
                                    id="co-notes"
                                    v-model="form.notes"
                                    rows="2"
                                    maxlength="300"
                                />
                            </div>
                        </template>
                    </section>

                    <!-- 3 · Pago -->
                    <section class="fl-card p-5 md:p-7">
                        <h2
                            class="mb-5 flex items-center gap-3 text-lg font-semibold"
                        >
                            <span
                                class="flex size-7 items-center justify-center rounded-full text-sm"
                                :class="
                                    step === 'payment'
                                        ? 'bg-foreground text-background'
                                        : 'bg-muted text-muted-foreground'
                                "
                                >3</span
                            >
                            Pago
                        </h2>

                        <template v-if="step === 'details'">
                            <p class="mb-5 text-sm text-muted-foreground">
                                Al continuar apartamos tus piezas y abrimos el
                                pago seguro con tarjeta. El total lo calcula
                                nuestro servidor.
                            </p>
                            <Button
                                class="h-12 w-full rounded-full text-base"
                                :disabled="
                                    form.processing || blocked || !items.length
                                "
                                @click="placeOrder"
                            >
                                <Spinner
                                    v-if="form.processing"
                                    class="size-4"
                                />
                                {{
                                    form.processing
                                        ? 'Apartando tus piezas…'
                                        : 'Continuar al pago'
                                }}
                            </Button>
                            <p
                                v-if="blocked"
                                class="mt-3 text-sm text-amber-800"
                            >
                                Hay productos sin precio o sin existencias.
                                <Link href="/carrito" class="underline"
                                    >Revisa tu carrito</Link
                                >.
                            </p>
                        </template>

                        <template v-else-if="order">
                            <StripePaymentElement
                                v-if="
                                    payment.provider === 'stripe' &&
                                    payment.publishable_key
                                "
                                :order-uuid="order.uuid"
                                :publishable-key="payment.publishable_key"
                                :return-url="returnUrl"
                                :amount-label="amountLabel"
                                :email="contact.email"
                                :name="contact.name"
                                @state="(s) => (paymentState = s)"
                                @succeeded="goToConfirmation"
                            />
                            <OpenPayCardForm
                                v-else-if="
                                    payment.provider === 'openpay' &&
                                    payment.openpay
                                "
                                :order-uuid="order.uuid"
                                :config="payment.openpay"
                                :amount-label="amountLabel"
                                @succeeded="goToConfirmation"
                            />
                            <div
                                v-else
                                class="rounded-control border border-border bg-muted/40 p-4 text-sm text-muted-foreground"
                            >
                                El pago en línea no está disponible por ahora.
                                Tu pedido quedó registrado; puedes pagarlo en tu
                                evento o te contactaremos.
                                <Link
                                    :href="`/mis-pedidos/${order.uuid}`"
                                    class="mt-2 block font-medium text-foreground underline-offset-4 hover:underline"
                                    >Ver mi pedido</Link
                                >
                            </div>
                            <p
                                class="mt-4 flex items-center gap-1.5 text-xs text-muted-foreground"
                            >
                                <Lock class="size-3" /> Los datos de tu tarjeta
                                van directo al procesador de pagos; nunca pasan
                                por Finisher Legacy.
                            </p>
                        </template>
                    </section>
                </div>

                <!-- Summary -->
                <aside class="lg:sticky lg:top-24 lg:self-start">
                    <div class="fl-card p-6">
                        <h2 class="mb-4 font-serif text-xl font-semibold">
                            Tu pedido
                        </h2>
                        <ul class="space-y-4">
                            <li
                                v-for="line in items"
                                :key="line.id"
                                class="flex gap-3"
                            >
                                <div
                                    class="relative aspect-[4/5] w-14 shrink-0 overflow-hidden rounded-control bg-fl-cream"
                                >
                                    <img
                                        v-if="line.image_url"
                                        :src="line.image_url"
                                        :alt="line.product_name"
                                        loading="lazy"
                                        class="size-full object-cover"
                                    />
                                    <ShoppingBag
                                        v-else
                                        class="m-auto mt-4 size-5 text-muted-foreground/60"
                                    />
                                    <span
                                        class="absolute -top-0 -right-0 flex size-5 items-center justify-center rounded-bl-lg bg-foreground text-[10px] font-semibold text-background"
                                        >{{ line.quantity }}</span
                                    >
                                </div>
                                <div class="min-w-0 flex-1 text-sm">
                                    <p class="truncate font-medium">
                                        {{ line.product_name }}
                                    </p>
                                    <p
                                        v-if="lineDetails(line)"
                                        class="truncate text-muted-foreground"
                                    >
                                        {{ lineDetails(line) }}
                                    </p>
                                    <p
                                        v-if="line.connected"
                                        class="mt-0.5 inline-flex items-center gap-1 text-xs text-fl-gold-ink"
                                    >
                                        <Nfc class="size-3" /> Producto
                                        conectado
                                    </p>
                                </div>
                                <p class="legacy-numeric shrink-0 text-sm">
                                    <Money
                                        v-if="line.line_total_minor !== null"
                                        :minor="line.line_total_minor"
                                        :currency="currency"
                                    />
                                </p>
                            </li>
                        </ul>
                        <dl
                            class="mt-5 space-y-2 border-t border-border pt-4 text-sm"
                        >
                            <div
                                class="flex justify-between text-muted-foreground"
                            >
                                <dt>Subtotal</dt>
                                <dd class="legacy-numeric">
                                    <Money
                                        :minor="subtotal_minor"
                                        :currency="currency"
                                    />
                                </dd>
                            </div>
                            <div
                                v-if="discount_minor > 0"
                                class="flex justify-between text-emerald-700"
                            >
                                <dt>
                                    Descuento{{
                                        coupon ? ` (${coupon.code})` : ''
                                    }}
                                </dt>
                                <dd class="legacy-numeric">
                                    −<Money
                                        :minor="discount_minor"
                                        :currency="currency"
                                    />
                                </dd>
                            </div>
                            <div
                                class="flex items-baseline justify-between border-t border-border pt-3 text-base font-semibold"
                            >
                                <dt>Total</dt>
                                <dd class="legacy-numeric text-xl">
                                    <Money
                                        :minor="total_minor"
                                        :currency="currency"
                                    />
                                </dd>
                            </div>
                        </dl>
                        <Link
                            v-if="step === 'details'"
                            href="/carrito"
                            class="mt-4 inline-flex min-h-11 items-center text-sm font-medium text-muted-foreground hover:text-foreground"
                            >← Editar carrito</Link
                        >
                        <p
                            v-if="paymentState === 'processing'"
                            class="mt-4 text-xs text-muted-foreground"
                            role="status"
                        >
                            No cierres esta ventana mientras confirmamos tu
                            pago.
                        </p>
                    </div>
                </aside>
            </div>
        </div>
    </div>
</template>
