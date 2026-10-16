<script setup lang="ts">
/**
 * Stripe Payment Element (spec §24-§25), per Stripe's intent-first guide:
 * the server creates the PaymentIntent from the Order's own amount/currency
 * (POST /checkout/{order}/online-payment → client_secret), Stripe.js is
 * loaded lazily — only here, only on checkout — and mounts the Payment
 * Element; confirmPayment() sends card data straight to Stripe (never to
 * Laravel). Success here only means "Stripe accepted the attempt": the page
 * then goes to the confirmation screen, which shows the SERVER's state; the
 * signed webhook is what marks the order paid.
 */
import { Lock } from '@lucide/vue';
import type {
    Stripe,
    StripeElements,
    StripePaymentElement,
} from '@stripe/stripe-js';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { postJson } from '@/lib/commerce';

const props = defineProps<{
    orderUuid: string;
    publishableKey: string;
    returnUrl: string;
    amountLabel: string;
    email?: string | null;
    name?: string | null;
}>();

const emit = defineEmits<{
    succeeded: [];
    state: [
        state: 'loading' | 'ready' | 'processing' | 'error' | 'unavailable',
    ];
}>();

const container = ref<HTMLDivElement | null>(null);
const status = ref<
    'loading' | 'ready' | 'processing' | 'error' | 'unavailable'
>('loading');
const message = ref<string | null>(null);
const complete = ref(false);

let stripe: Stripe | null = null;
let elements: StripeElements | null = null;
let paymentElement: StripePaymentElement | null = null;

function setStatus(next: typeof status.value) {
    status.value = next;
    emit('state', next);
}

async function init() {
    setStatus('loading');
    message.value = null;

    try {
        const data = await postJson<{
            available: boolean;
            message?: string;
            client_payload?: { client_secret?: string };
        }>(`/checkout/${props.orderUuid}/online-payment`);

        const clientSecret = data.client_payload?.client_secret;

        if (!data.available || !clientSecret) {
            message.value =
                data.message ??
                'El pago con tarjeta no está disponible en este momento.';
            setStatus('unavailable');

            return;
        }

        const { loadStripe } = await import('@stripe/stripe-js');
        stripe = await loadStripe(props.publishableKey, { locale: 'es-419' });

        if (!stripe || !container.value) {
            throw new Error('Stripe.js no cargó');
        }

        elements = stripe.elements({
            clientSecret,
            appearance: {
                theme: 'stripe',
                variables: {
                    colorPrimary: '#171714',
                    colorBackground: '#ffffff',
                    colorText: '#171714',
                    colorTextSecondary: '#5f5e58',
                    colorDanger: '#b3261e',
                    borderRadius: '12px',
                    fontFamily:
                        '"Instrument Sans", ui-sans-serif, system-ui, sans-serif',
                    spacingUnit: '4px',
                },
                rules: {
                    '.Input': { boxShadow: 'none', borderColor: '#e4e1d8' },
                    '.Input:focus': {
                        borderColor: '#171714',
                        boxShadow: '0 0 0 3px rgba(201, 164, 92, 0.25)',
                    },
                },
            },
        });

        paymentElement = elements.create('payment', {
            layout: 'accordion',
            defaultValues: {
                billingDetails: {
                    email: props.email ?? undefined,
                    name: props.name ?? undefined,
                },
            },
        });
        paymentElement.on('change', (event) => {
            complete.value = event.complete;
        });
        paymentElement.on('ready', () => setStatus('ready'));
        paymentElement.mount(container.value);
    } catch {
        message.value =
            'No pudimos cargar el formulario de pago. Revisa tu conexión e inténtalo de nuevo.';
        setStatus('error');
    }
}

async function pay() {
    if (!stripe || !elements || status.value === 'processing') {
        return;
    }

    setStatus('processing');
    message.value = null;

    const { error } = await stripe.confirmPayment({
        elements,
        confirmParams: { return_url: props.returnUrl },
        // Cards without 3DS finish here; anything needing a bank page
        // redirects to return_url and comes back to the confirmation.
        redirect: 'if_required',
    });

    if (error) {
        message.value =
            error.type === 'card_error' || error.type === 'validation_error'
                ? (error.message ?? 'Tu tarjeta fue rechazada.')
                : 'Algo falló al procesar el pago. No se hizo ningún cargo; inténtalo de nuevo.';
        setStatus('ready');

        return;
    }

    emit('succeeded');
}

onMounted(() => {
    if (!import.meta.env.SSR) {
        void init();
    }
});

onBeforeUnmount(() => {
    paymentElement?.destroy();
});
</script>

<template>
    <div>
        <div
            v-if="status === 'loading'"
            class="flex items-center gap-3 rounded-xl border border-border bg-card p-5 text-sm text-muted-foreground"
            role="status"
        >
            <Spinner class="size-4" /> Preparando el pago seguro…
        </div>

        <div
            v-show="status === 'ready' || status === 'processing'"
            ref="container"
            class="min-h-24"
        />

        <p
            v-if="message"
            class="mt-4 rounded-xl border px-4 py-3 text-sm"
            :class="
                status === 'unavailable'
                    ? 'border-border bg-muted/50 text-muted-foreground'
                    : 'border-red-200 bg-red-50 text-red-800'
            "
            role="alert"
        >
            {{ message }}
        </p>

        <Button
            v-if="status === 'error'"
            variant="outline"
            class="mt-4 h-11 rounded-full"
            @click="init"
            >Reintentar</Button
        >

        <Button
            v-if="status === 'ready' || status === 'processing'"
            class="mt-6 h-12 w-full rounded-full text-base"
            :disabled="status === 'processing' || !complete"
            @click="pay"
        >
            <Spinner v-if="status === 'processing'" class="size-4" />
            <Lock v-else class="size-4" />
            {{
                status === 'processing'
                    ? 'Procesando pago…'
                    : `Pagar ${amountLabel}`
            }}
        </Button>
    </div>
</template>
