<script setup lang="ts">
/**
 * OpenPay card form — the additional, configurable provider (spec §26).
 * Openpay.js (loaded lazily from OpenPay's CDN, only here) tokenizes the
 * card in the browser; Laravel only receives the token and charges the
 * Order's own amount through the same CreateOnlinePayment Action Stripe
 * uses. As with Stripe, the confirmation page shows the server's state.
 */
import { Lock } from '@lucide/vue';
import { reactive, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { postJson } from '@/lib/commerce';

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

const props = defineProps<{
    orderUuid: string;
    config: { merchant_id: string; public_key: string; sandbox: boolean };
    amountLabel: string;
}>();

const emit = defineEmits<{ succeeded: [] }>();

const VERSION = '1.5.1';
const loading = ref(false);
const ready = ref(false);
const paying = ref(false);
const message = ref<string | null>(null);
let deviceSessionId: string | null = null;

const card = reactive({
    holderName: '',
    cardNumber: '',
    expMonth: '',
    expYear: '',
    cvv: '',
});

function sdk(): OpenPaySdk | undefined {
    return (window as unknown as { OpenPay?: OpenPaySdk }).OpenPay;
}

function loadScript(src: string): Promise<void> {
    return new Promise((resolve, reject) => {
        if (document.querySelector(`script[src="${src}"]`)) {
            resolve();

            return;
        }

        const script = document.createElement('script');
        script.src = src;
        script.onload = () => resolve();
        script.onerror = () => reject(new Error(src));
        document.head.appendChild(script);
    });
}

async function open() {
    loading.value = true;
    message.value = null;

    try {
        const base = `https://resources.openpay.mx/lib/openpay-js/${VERSION}`;
        await loadScript(`${base}/openpay.v1.min.js`);
        await loadScript(`${base}/openpay-data.v1.min.js`);
        const openpay = sdk();

        if (!openpay) {
            throw new Error('openpay');
        }

        openpay.setId(props.config.merchant_id);
        openpay.setApiKey(props.config.public_key);
        openpay.setSandboxMode(props.config.sandbox);
        deviceSessionId = openpay.deviceData.setup();
        ready.value = true;
    } catch {
        message.value = 'No pudimos cargar el formulario de pago.';
    } finally {
        loading.value = false;
    }
}

function submit() {
    const openpay = sdk();

    if (!openpay || !deviceSessionId) {
        return;
    }

    paying.value = true;
    message.value = null;

    openpay.token.create(
        {
            holder_name: card.holderName,
            card_number: card.cardNumber.replace(/\s+/g, ''),
            expiration_month: card.expMonth,
            expiration_year: card.expYear,
            cvv2: card.cvv,
        },
        async (response) => {
            try {
                const data = await postJson<{
                    available: boolean;
                    message?: string;
                }>(`/checkout/${props.orderUuid}/online-payment`, {
                    token_id: response.data.id,
                    device_session_id: deviceSessionId,
                });

                if (!data.available) {
                    message.value =
                        data.message ?? 'El pago no está disponible ahora.';
                    paying.value = false;

                    return;
                }

                emit('succeeded');
            } catch {
                message.value =
                    'No se pudo procesar el pago. Intenta de nuevo.';
                paying.value = false;
            }
        },
        (error) => {
            paying.value = false;
            message.value =
                error.data?.description ??
                error.message ??
                'La tarjeta fue rechazada.';
        },
    );
}
</script>

<template>
    <div>
        <Button
            v-if="!ready"
            class="h-12 w-full rounded-full"
            :disabled="loading"
            @click="open"
        >
            <Spinner v-if="loading" class="size-4" />
            Pagar con tarjeta
        </Button>
        <form v-else class="grid gap-4" @submit.prevent="submit">
            <div class="grid gap-1.5">
                <Label for="op-name">Nombre en la tarjeta</Label>
                <Input
                    id="op-name"
                    v-model="card.holderName"
                    autocomplete="cc-name"
                    class="h-11"
                    required
                />
            </div>
            <div class="grid gap-1.5">
                <Label for="op-number">Número de tarjeta</Label>
                <Input
                    id="op-number"
                    v-model="card.cardNumber"
                    inputmode="numeric"
                    autocomplete="cc-number"
                    class="h-11"
                    required
                />
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div class="grid gap-1.5">
                    <Label for="op-month">Mes</Label>
                    <Input
                        id="op-month"
                        v-model="card.expMonth"
                        inputmode="numeric"
                        maxlength="2"
                        placeholder="MM"
                        autocomplete="cc-exp-month"
                        class="h-11"
                        required
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label for="op-year">Año</Label>
                    <Input
                        id="op-year"
                        v-model="card.expYear"
                        inputmode="numeric"
                        maxlength="2"
                        placeholder="AA"
                        autocomplete="cc-exp-year"
                        class="h-11"
                        required
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label for="op-cvv">CVV</Label>
                    <Input
                        id="op-cvv"
                        v-model="card.cvv"
                        inputmode="numeric"
                        maxlength="4"
                        autocomplete="cc-csc"
                        class="h-11"
                        required
                    />
                </div>
            </div>
            <Button
                type="submit"
                class="h-12 w-full rounded-full text-base"
                :disabled="paying"
            >
                <Spinner v-if="paying" class="size-4" />
                <Lock v-else class="size-4" />
                {{ paying ? 'Procesando pago…' : `Pagar ${amountLabel}` }}
            </Button>
        </form>
        <p
            v-if="message"
            class="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
            role="alert"
        >
            {{ message }}
        </p>
    </div>
</template>
