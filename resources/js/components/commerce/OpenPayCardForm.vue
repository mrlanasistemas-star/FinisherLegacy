<script setup lang="ts">
/**
 * OpenPay card form (primary web gateway).
 *
 * Openpay.js + openpay-data.js are loaded lazily from OpenPay's official
 * CDN (js.openpay.mx — the same files serve sandbox and production; the
 * mode is set with OpenPay.setSandboxMode). The card is tokenized in the
 * browser: Laravel only receives `token_id` + `device_session_id` and
 * charges the Order's own amount (POST /checkout/{order}/online-payment).
 *
 * 3D Secure: when the bank asks for it, the server answers with
 * `client_payload.redirect_url` and we send the shopper there; OpenPay
 * brings them back to the confirmation page, which re-reads the charge
 * from OpenPay. As with Stripe, "paid" is always the server's word.
 */
import { CreditCard, Lock, ShieldCheck } from '@lucide/vue';
import { computed, onMounted, reactive, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { postJson } from '@/lib/commerce';

type OpenPayError = {
    status?: number;
    message?: string;
    data?: { description?: string; error_code?: number };
};

type OpenPaySdk = {
    setId: (id: string) => void;
    setApiKey: (key: string) => void;
    setSandboxMode: (sandbox: boolean) => void;
    token: {
        create: (
            data: Record<string, string>,
            onSuccess: (response: { data: { id: string } }) => void,
            onError: (response: OpenPayError) => void,
        ) => void;
    };
    card: {
        validateCardNumber: (n: string) => boolean;
        validateCVC: (cvc: string, n?: string) => boolean;
        validateExpiry: (month: string, year: string) => boolean;
        cardType: (n: string) => string;
    };
    deviceData: { setup: (formId?: string, fieldName?: string) => string };
};

type PaymentResponse = {
    available: boolean;
    declined?: boolean;
    message?: string;
    client_payload?: { status?: string; redirect_url?: string | null };
};

const props = defineProps<{
    orderUuid: string;
    config: { merchant_id: string; public_key: string; sandbox: boolean };
    amountLabel: string;
    holderName?: string | null;
}>();

const emit = defineEmits<{
    succeeded: [];
    state: [state: 'loading' | 'ready' | 'processing' | 'error'];
}>();

// Official OpenPay MX CDN (see OpenPay's Openpay.js docs).
const OPENPAY_JS = 'https://js.openpay.mx/openpay.v1.min.js';
const OPENPAY_DATA_JS = 'https://js.openpay.mx/openpay-data.v1.min.js';

const status = ref<
    'loading' | 'ready' | 'processing' | 'redirecting' | 'error'
>('loading');
const message = ref<string | null>(null);
const errors = reactive<Record<string, string | undefined>>({});
let deviceSessionId: string | null = null;

const card = reactive({
    holderName: props.holderName ?? '',
    number: '',
    expiry: '',
    cvv: '',
});

function sdk(): OpenPaySdk | undefined {
    return (window as unknown as { OpenPay?: OpenPaySdk }).OpenPay;
}

function setStatus(next: typeof status.value) {
    status.value = next;

    if (next !== 'redirecting') {
        emit('state', next);
    }
}

function loadScript(src: string): Promise<void> {
    return new Promise((resolve, reject) => {
        const existing = document.querySelector<HTMLScriptElement>(
            `script[src="${src}"]`,
        );

        if (existing?.dataset.loaded === '1') {
            resolve();

            return;
        }

        const script = existing ?? document.createElement('script');
        script.addEventListener('load', () => {
            script.dataset.loaded = '1';
            resolve();
        });
        script.addEventListener('error', () => reject(new Error(src)));

        if (!existing) {
            script.src = src;
            script.async = true;
            document.head.appendChild(script);
        }
    });
}

async function init() {
    setStatus('loading');
    message.value = null;

    try {
        // openpay-data.js extends the OpenPay object, so order matters.
        await loadScript(OPENPAY_JS);
        await loadScript(OPENPAY_DATA_JS);
        const openpay = sdk();

        if (!openpay) {
            throw new Error('openpay');
        }

        openpay.setId(props.config.merchant_id);
        openpay.setApiKey(props.config.public_key);
        openpay.setSandboxMode(props.config.sandbox);
        deviceSessionId = openpay.deviceData.setup();
        setStatus('ready');
    } catch {
        message.value =
            'No pudimos cargar el formulario de pago. Revisa tu conexión (o desactiva el bloqueador de anuncios) e intenta de nuevo.';
        setStatus('error');
    }
}

onMounted(init);

// --- Formatting -------------------------------------------------------------

const digits = (value: string) => value.replace(/\D+/g, '');

const brand = computed(() => {
    const n = digits(card.number);

    if (/^4/.test(n)) {
        return 'Visa';
    }

    if (/^(5[1-5]|2[2-7])/.test(n)) {
        return 'Mastercard';
    }

    if (/^3[47]/.test(n)) {
        return 'American Express';
    }

    if (/^(506|627|636)/.test(n)) {
        return 'Carnet';
    }

    return null;
});

const isAmex = computed(() => brand.value === 'American Express');

function onNumber(value: string | number) {
    const n = digits(String(value)).slice(0, isAmex.value ? 15 : 16);
    card.number = isAmex.value
        ? n.replace(/^(\d{0,4})(\d{0,6})(\d{0,5}).*/, (_, a, b, c) =>
              [a, b, c].filter(Boolean).join(' '),
          )
        : n.replace(/(\d{4})(?=\d)/g, '$1 ');
    errors.number = undefined;
}

function onExpiry(value: string | number) {
    const n = digits(String(value)).slice(0, 4);
    card.expiry = n.length > 2 ? `${n.slice(0, 2)}/${n.slice(2)}` : n;
    errors.expiry = undefined;
}

function onCvv(value: string | number) {
    card.cvv = digits(String(value)).slice(0, isAmex.value ? 4 : 3);
    errors.cvv = undefined;
}

function validate(openpay: OpenPaySdk): boolean {
    const number = digits(card.number);
    const [month = '', year = ''] = card.expiry.split('/');

    errors.holderName =
        card.holderName.trim().length < 3
            ? 'Escribe el nombre como aparece en la tarjeta.'
            : undefined;
    errors.number = !openpay.card.validateCardNumber(number)
        ? 'Revisa el número de tarjeta.'
        : undefined;
    errors.expiry =
        month.length !== 2 ||
        year.length !== 2 ||
        !openpay.card.validateExpiry(month, year)
            ? 'Revisa la fecha de vencimiento (MM/AA).'
            : undefined;
    errors.cvv = !openpay.card.validateCVC(card.cvv, number)
        ? 'Revisa el código de seguridad.'
        : undefined;

    return !Object.values(errors).some(Boolean);
}

// --- Pay --------------------------------------------------------------------

function tokenizeError(error: OpenPayError): string {
    const code = error.data?.error_code;

    if (code === 2004) {
        return 'El número de tarjeta no es válido.';
    }

    if (code === 2005) {
        return 'La tarjeta está vencida.';
    }

    if (code === 2006 || code === 2009) {
        return 'Revisa el código de seguridad (CVV).';
    }

    if (error.status === 0) {
        return 'No pudimos contactar al procesador de pagos. Revisa tu conexión.';
    }

    return 'No pudimos validar la tarjeta. Revisa los datos e intenta de nuevo.';
}

async function charge(tokenId: string) {
    try {
        const data = await postJson<PaymentResponse>(
            `/checkout/${props.orderUuid}/online-payment`,
            { token_id: tokenId, device_session_id: deviceSessionId },
        );

        if (!data.available || data.declined) {
            message.value =
                data.message ?? 'El pago no está disponible en este momento.';
            setStatus(data.available ? 'ready' : 'error');

            return;
        }

        const redirect = data.client_payload?.redirect_url;

        if (redirect) {
            // 3D Secure: the bank's page, then back to our confirmation.
            setStatus('redirecting');
            window.location.assign(redirect);

            return;
        }

        emit('succeeded');
    } catch {
        message.value =
            'No pudimos confirmar el pago. Revisa “Mis pedidos” antes de intentar de nuevo para evitar un doble cargo.';
        setStatus('ready');
    }
}

function submit() {
    const openpay = sdk();

    if (!openpay || !deviceSessionId || status.value !== 'ready') {
        return;
    }

    message.value = null;

    if (!validate(openpay)) {
        return;
    }

    const [month, year] = card.expiry.split('/');
    setStatus('processing');

    openpay.token.create(
        {
            holder_name: card.holderName.trim(),
            card_number: digits(card.number),
            expiration_month: month,
            expiration_year: year,
            cvv2: card.cvv,
        },
        (response) => void charge(response.data.id),
        (error) => {
            message.value = tokenizeError(error);
            setStatus('ready');
        },
    );
}

const busy = computed(
    () => status.value === 'processing' || status.value === 'redirecting',
);
</script>

<template>
    <div>
        <div
            v-if="status === 'loading'"
            class="flex items-center gap-3 rounded-2xl border border-border bg-muted/30 px-4 py-6 text-sm text-muted-foreground"
            role="status"
        >
            <Spinner class="size-4" /> Preparando el pago seguro…
        </div>

        <div
            v-else-if="status === 'error'"
            class="rounded-2xl border border-red-200 bg-red-50 px-4 py-4 text-sm text-red-800"
            role="alert"
        >
            <p>{{ message }}</p>
            <Button
                variant="outline"
                class="mt-3 h-10 rounded-full bg-white"
                @click="init"
            >
                Reintentar
            </Button>
        </div>

        <form
            v-else
            class="grid gap-4"
            novalidate
            autocomplete="on"
            @submit.prevent="submit"
        >
            <div class="grid gap-1.5">
                <Label for="op-number">Número de tarjeta</Label>
                <div class="relative">
                    <Input
                        id="op-number"
                        :model-value="card.number"
                        inputmode="numeric"
                        autocomplete="cc-number"
                        placeholder="1234 5678 9012 3456"
                        class="legacy-numeric h-12 pr-28 text-base"
                        :aria-invalid="!!errors.number"
                        :disabled="busy"
                        @update:model-value="onNumber"
                    />
                    <span
                        class="pointer-events-none absolute inset-y-0 right-3 flex items-center gap-1.5 text-xs font-medium text-muted-foreground"
                    >
                        <CreditCard class="size-4" />
                        {{ brand ?? '' }}
                    </span>
                </div>
                <InputError :message="errors.number" />
            </div>

            <div class="grid gap-1.5">
                <Label for="op-name">Nombre en la tarjeta</Label>
                <Input
                    id="op-name"
                    v-model="card.holderName"
                    autocomplete="cc-name"
                    class="h-12 text-base"
                    :aria-invalid="!!errors.holderName"
                    :disabled="busy"
                />
                <InputError :message="errors.holderName" />
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="grid gap-1.5">
                    <Label for="op-exp">Vencimiento</Label>
                    <Input
                        id="op-exp"
                        :model-value="card.expiry"
                        inputmode="numeric"
                        placeholder="MM/AA"
                        autocomplete="cc-exp"
                        class="legacy-numeric h-12 text-base"
                        :aria-invalid="!!errors.expiry"
                        :disabled="busy"
                        @update:model-value="onExpiry"
                    />
                    <InputError :message="errors.expiry" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="op-cvv">Código de seguridad</Label>
                    <Input
                        id="op-cvv"
                        :model-value="card.cvv"
                        inputmode="numeric"
                        type="password"
                        :placeholder="isAmex ? '4 dígitos' : '3 dígitos'"
                        autocomplete="cc-csc"
                        class="legacy-numeric h-12 text-base"
                        :aria-invalid="!!errors.cvv"
                        :disabled="busy"
                        @update:model-value="onCvv"
                    />
                    <InputError :message="errors.cvv" />
                </div>
            </div>

            <p
                v-if="message"
                class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
                role="alert"
            >
                {{ message }}
            </p>

            <Button
                type="submit"
                class="mt-1 h-12 w-full rounded-full text-base"
                :disabled="busy"
            >
                <Spinner v-if="busy" class="size-4" />
                <Lock v-else class="size-4" />
                {{
                    status === 'redirecting'
                        ? 'Abriendo la verificación de tu banco…'
                        : status === 'processing'
                          ? 'Procesando pago…'
                          : `Pagar ${amountLabel}`
                }}
            </Button>

            <div
                class="flex flex-wrap items-center justify-between gap-2 text-xs text-muted-foreground"
            >
                <span class="inline-flex items-center gap-1.5">
                    <ShieldCheck class="size-3.5 text-emerald-700" />
                    Pago seguro procesado por OpenPay (BBVA)
                </span>
                <span>Visa · Mastercard · American Express · Carnet</span>
            </div>
            <p
                v-if="config.sandbox"
                class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-900"
            >
                Modo de pruebas (sandbox): usa una tarjeta de prueba de OpenPay;
                no se hace ningún cargo real.
            </p>
        </form>
    </div>
</template>
