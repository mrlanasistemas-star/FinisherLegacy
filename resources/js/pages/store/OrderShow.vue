<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, CheckCircle2 } from '@lucide/vue';
import { reactive, ref } from 'vue';
import OrderStatusTimeline from '@/components/orders/OrderStatusTimeline.vue';
import Money from '@/components/shared/Money.vue';
import PaymentStatusBadge from '@/components/shared/PaymentStatusBadge.vue';
import ProductImagePlaceholder from '@/components/shared/ProductImagePlaceholder.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { shortDate } from '@/lib/datetime';
import { index as ordersIndex } from '@/routes/store/orders';
import type { OrderProgress } from '@/types/legado';

// "Mis pedidos" detail lives in the athlete's personal area (spec §7).
defineOptions({ layout: AppLayout });

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
    progress?: OrderProgress;
    items: OrderItem[];
    photoDownloads?: {
        uuid: string;
        thumb_url: string;
        download_url: string;
    }[];
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

    <div class="bg-background">
        <div
            class="mx-auto w-full max-w-3xl px-4 py-6 sm:px-6 md:py-10 xl:px-8"
        >
            <Link
                :href="ordersIndex()"
                class="inline-flex min-h-11 items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
            >
                <ArrowLeft class="size-4" /> Mis pedidos
            </Link>

            <header class="mt-2 mb-8">
                <p class="fl-eyebrow">Pedido #{{ order.order_number }}</p>
                <h1 class="fl-display mt-2 text-4xl sm:text-5xl">
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
                    <p v-else class="text-sm text-muted-foreground">
                        El pago en línea no está disponible en este momento.
                        Contacta a soporte para completar tu pago.
                    </p>
                    <p
                        v-if="formLoadFailed"
                        class="mt-2 text-sm text-amber-700"
                    >
                        No se pudo cargar el formulario de pago. Verifica tu
                        conexión e intenta de nuevo.
                    </p>
                </template>

                <form
                    v-else
                    class="max-w-sm space-y-4 rounded-xl border border-border bg-card/30 p-5"
                    @submit.prevent="submitCardPayment"
                >
                    <div class="grid gap-1.5">
                        <Label class="text-muted-foreground"
                            >Nombre en la tarjeta</Label
                        >
                        <Input
                            v-model="card.holderName"
                            required
                            autocomplete="cc-name"
                            class="border-border bg-background text-foreground"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label class="text-muted-foreground"
                            >Número de tarjeta</Label
                        >
                        <Input
                            v-model="card.cardNumber"
                            required
                            inputmode="numeric"
                            maxlength="19"
                            placeholder="4111 1111 1111 1111"
                            autocomplete="cc-number"
                            class="border-border bg-background text-foreground"
                        />
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <div class="grid gap-1.5">
                            <Label class="text-muted-foreground">Mes</Label>
                            <Input
                                v-model="card.expMonth"
                                required
                                inputmode="numeric"
                                maxlength="2"
                                placeholder="MM"
                                autocomplete="cc-exp-month"
                                class="border-border bg-background text-foreground"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label class="text-muted-foreground">Año</Label>
                            <Input
                                v-model="card.expYear"
                                required
                                inputmode="numeric"
                                maxlength="2"
                                placeholder="AA"
                                autocomplete="cc-exp-year"
                                class="border-border bg-background text-foreground"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label class="text-muted-foreground">CVV</Label>
                            <Input
                                v-model="card.cvv"
                                required
                                inputmode="numeric"
                                maxlength="4"
                                placeholder="123"
                                autocomplete="cc-csc"
                                class="border-border bg-background text-foreground"
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
                    <p class="text-center text-[11px] text-muted-foreground/80">
                        Tu tarjeta se procesa directamente con Openpay — este
                        sitio nunca almacena sus datos.
                    </p>
                </form>

                <p
                    v-if="paymentMessage"
                    class="mt-3 text-sm"
                    :class="
                        paymentFailed ? 'text-amber-700' : 'text-emerald-700'
                    "
                >
                    {{ paymentMessage }}
                </p>
            </div>
        </div>
    </div>
</template>
