<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CheckCircle2 } from '@lucide/vue';
import { reactive, ref } from 'vue';
import Money from '@/components/shared/Money.vue';
import OrderStatusBadge from '@/components/shared/OrderStatusBadge.vue';
import PaymentStatusBadge from '@/components/shared/PaymentStatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

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
};

type OpenpayConfig = {
    merchant_id: string;
    public_key: string;
    sandbox: boolean;
} | null;

/**
 * Minimal shape of the global Openpay.js SDK we actually call — the real
 * library has no published types, and it never ships as an npm module (brief
 * §143: loaded as a plain <script> tag below), so this is the contract, not
 * a generic wrapper around it.
 */
type OpenPaySdk = {
    setId: (id: string) => void;
    setApiKey: (key: string) => void;
    setSandboxMode: (sandbox: boolean) => void;
    token: {
        create: (
            data: Record<string, string>,
            onSuccess: (response: { data: { id: string } }) => void,
            onError: (response: {
                data?: { description?: string };
                message?: string;
            }) => void,
        ) => void;
    };
    deviceData: { setup: () => string };
};

declare global {
    interface Window {
        OpenPay?: OpenPaySdk;
    }
}

const props = defineProps<{
    order: OrderDetail;
    items: OrderItem[];
    openpay: OpenpayConfig;
}>();

const paying = ref(false);
const paymentMessage = ref<string | null>(null);
const paymentFailed = ref(false);
const paid = ref(props.order.payment_status !== 'pending');

const showCardForm = ref(false);
const loadingForm = ref(false);
const formLoadFailed = ref(false);
const deviceSessionId = ref<string | null>(null);

const card = reactive({
    holderName: '',
    cardNumber: '',
    expMonth: '',
    expYear: '',
    cvv: '',
});

const OPENPAY_JS_VERSION = '1.5.1';
let scriptsPromise: Promise<void> | null = null;

function loadScript(src: string): Promise<void> {
    return new Promise((resolve, reject) => {
        if (document.querySelector(`script[src="${src}"]`)) {
            resolve();

            return;
        }

        const script = document.createElement('script');
        script.src = src;
        script.onload = () => resolve();
        script.onerror = () => reject(new Error(`No se pudo cargar ${src}`));
        document.head.appendChild(script);
    });
}

async function ensureOpenPayReady(): Promise<boolean> {
    if (window.OpenPay) {
        return true;
    }

    scriptsPromise ??= (async () => {
        await loadScript(
            `https://resources.openpay.mx/lib/openpay-js/${OPENPAY_JS_VERSION}/openpay.v1.min.js`,
        );
        await loadScript(
            `https://resources.openpay.mx/lib/openpay-js/${OPENPAY_JS_VERSION}/openpay-data.v1.min.js`,
        );
    })();

    try {
        await scriptsPromise;
    } catch {
        scriptsPromise = null;

        return false;
    }

    return !!window.OpenPay;
}

async function openCardForm() {
    // Inertia SSR renders this page's first paint on the server, where the
    // browser globals Openpay.js needs don't exist — see admin/plates/Show.vue
    // for the same guard on a plain fetch().
    if (import.meta.env.SSR || !props.openpay) {
        return;
    }

    formLoadFailed.value = false;
    loadingForm.value = true;

    const ready = await ensureOpenPayReady();

    loadingForm.value = false;

    if (!ready || !window.OpenPay) {
        formLoadFailed.value = true;

        return;
    }

    window.OpenPay.setId(props.openpay.merchant_id);
    window.OpenPay.setApiKey(props.openpay.public_key);
    window.OpenPay.setSandboxMode(props.openpay.sandbox);
    deviceSessionId.value = window.OpenPay.deviceData.setup();
    showCardForm.value = true;
}

function csrfToken(): string {
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

async function submitCardPayment() {
    if (!window.OpenPay || !deviceSessionId.value) {
        return;
    }

    paying.value = true;
    paymentFailed.value = false;
    paymentMessage.value = null;

    window.OpenPay.token.create(
        {
            holder_name: card.holderName,
            card_number: card.cardNumber.replace(/\s+/g, ''),
            expiration_month: card.expMonth,
            expiration_year: card.expYear,
            cvv2: card.cvv,
        },
        (response) => finishPayment(response.data.id),
        (response) => {
            paying.value = false;
            paymentFailed.value = true;
            paymentMessage.value =
                response.data?.description ??
                response.message ??
                'La tarjeta fue rechazada.';
        },
    );
}

async function finishPayment(tokenId: string) {
    try {
        const response = await fetch(
            `/checkout/${props.order.uuid}/online-payment`,
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({
                    token_id: tokenId,
                    device_session_id: deviceSessionId.value,
                }),
            },
        );
        const data = await response.json();

        if (!data.available) {
            paymentFailed.value = true;
            paymentMessage.value =
                data.message ??
                'El pago en línea no está disponible en este momento.';
        } else {
            paymentFailed.value = false;
            paymentMessage.value =
                'Pago recibido. Confirmaremos el estado de tu pedido en unos minutos.';
            showCardForm.value = false;
        }
    } catch {
        paymentFailed.value = true;
        paymentMessage.value = 'No se pudo procesar el pago. Intenta de nuevo.';
    } finally {
        paying.value = false;
    }
}
</script>

<template>
    <Head :title="`Pedido #${order.order_number} — Finisher Legacy`" />

    <div class="bg-fl-black">
        <div class="mx-auto w-full max-w-3xl px-4 py-12 sm:px-6 xl:px-8">
            <Link
                href="/mis-pedidos"
                class="text-xs tracking-wide text-white/40 uppercase hover:text-fl-gold-soft"
                >← Mis pedidos</Link
            >

            <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-2xl font-black text-white">
                    Pedido #{{ order.order_number }}
                </h1>
                <div class="flex items-center gap-2">
                    <OrderStatusBadge :status="order.status" />
                    <PaymentStatusBadge :status="order.payment_status" />
                </div>
            </div>
            <p class="mt-1 text-sm text-white/40">{{ order.created_at }}</p>

            <div
                class="mt-8 divide-y divide-white/10 rounded-2xl border border-white/10 bg-fl-graphite/20"
            >
                <div
                    v-for="(item, index) in items"
                    :key="index"
                    class="flex items-center justify-between px-5 py-4"
                >
                    <div>
                        <p class="text-white">{{ item.name }}</p>
                        <p class="text-sm text-white/40">
                            x{{ item.quantity }}
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <span
                            v-if="item.fulfilled"
                            class="flex items-center gap-1 text-xs text-emerald-400"
                        >
                            <CheckCircle2 class="size-3.5" /> Entregado
                        </span>
                        <p class="text-white/70">
                            <Money
                                :minor="item.line_total_minor"
                                :currency="order.currency"
                            />
                        </p>
                    </div>
                </div>
                <div class="flex items-center justify-between px-5 py-4">
                    <p class="font-medium text-white">Total</p>
                    <p class="text-lg font-semibold text-fl-gold-soft">
                        <Money
                            :minor="order.total_minor"
                            :currency="order.currency"
                        />
                    </p>
                </div>
            </div>

            <div
                v-if="!paid && order.payment_status === 'pending'"
                class="mt-8"
            >
                <template v-if="!showCardForm">
                    <Button
                        v-if="openpay"
                        class="bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                        :disabled="loadingForm"
                        @click="openCardForm"
                    >
                        {{ loadingForm ? 'Cargando…' : 'Pagar en línea' }}
                    </Button>
                    <p v-else class="text-sm text-white/50">
                        El pago en línea no está disponible en este momento.
                        Contacta a soporte para completar tu pago.
                    </p>
                    <p
                        v-if="formLoadFailed"
                        class="mt-2 text-sm text-amber-400"
                    >
                        No se pudo cargar el formulario de pago. Verifica tu
                        conexión e intenta de nuevo.
                    </p>
                </template>

                <form
                    v-else
                    class="max-w-sm space-y-4 rounded-xl border border-white/10 bg-fl-graphite/30 p-5"
                    @submit.prevent="submitCardPayment"
                >
                    <div class="grid gap-1.5">
                        <Label class="text-white/70"
                            >Nombre en la tarjeta</Label
                        >
                        <Input
                            v-model="card.holderName"
                            required
                            autocomplete="cc-name"
                            class="border-white/10 bg-fl-black text-white"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label class="text-white/70">Número de tarjeta</Label>
                        <Input
                            v-model="card.cardNumber"
                            required
                            inputmode="numeric"
                            maxlength="19"
                            placeholder="4111 1111 1111 1111"
                            autocomplete="cc-number"
                            class="border-white/10 bg-fl-black text-white"
                        />
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <div class="grid gap-1.5">
                            <Label class="text-white/70">Mes</Label>
                            <Input
                                v-model="card.expMonth"
                                required
                                inputmode="numeric"
                                maxlength="2"
                                placeholder="MM"
                                autocomplete="cc-exp-month"
                                class="border-white/10 bg-fl-black text-white"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label class="text-white/70">Año</Label>
                            <Input
                                v-model="card.expYear"
                                required
                                inputmode="numeric"
                                maxlength="2"
                                placeholder="AA"
                                autocomplete="cc-exp-year"
                                class="border-white/10 bg-fl-black text-white"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label class="text-white/70">CVV</Label>
                            <Input
                                v-model="card.cvv"
                                required
                                inputmode="numeric"
                                maxlength="4"
                                placeholder="123"
                                autocomplete="cc-csc"
                                class="border-white/10 bg-fl-black text-white"
                            />
                        </div>
                    </div>
                    <Button
                        type="submit"
                        class="w-full bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                        :disabled="paying"
                    >
                        {{ paying ? 'Procesando…' : 'Confirmar pago' }}
                    </Button>
                    <p class="text-center text-[11px] text-white/30">
                        Tu tarjeta se procesa directamente con Openpay — este
                        sitio nunca almacena sus datos.
                    </p>
                </form>

                <p
                    v-if="paymentMessage"
                    class="mt-3 text-sm"
                    :class="
                        paymentFailed ? 'text-amber-400' : 'text-emerald-400'
                    "
                >
                    {{ paymentMessage }}
                </p>
            </div>
        </div>
    </div>
</template>
