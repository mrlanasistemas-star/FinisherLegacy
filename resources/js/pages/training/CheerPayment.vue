<script setup lang="ts">
/**
 * Guest payment of an extra cheer message (CheerPaymentController,
 * docs/renovacion/tarjeta-animo.md §Pagos). The sender has no account: the
 * page is scoped to the Order of the message this browser just recorded.
 *
 *   step `pay`           summary + OpenPay card form (Openpay.js tokenizes;
 *                        Laravel only receives token_id + device_session_id)
 *   step `confirmation`  3D Secure lands here; shows the SERVER state only
 *                        and polls `estado` until it is final
 *
 * The message reaches the athlete only when the server confirms the
 * payment (webhook / server-side re-read) — never because of this page.
 * Light premium, no site chrome, safe areas respected.
 */
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Check,
    CircleAlert,
    Clock,
    Lock,
    MessageCircleHeart,
    RotateCcw,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import OpenPayCardForm from '@/components/commerce/OpenPayCardForm.vue';
import { Spinner } from '@/components/ui/spinner';
import { useI18n } from '@/i18n';
import { getJson } from '@/lib/commerce';
import type { CheckoutOrderState } from '@/lib/commerce';

defineOptions({ layout: [] });

const props = defineProps<{
    step: 'pay' | 'confirmation';
    token: string;
    title: string;
    athlete: { name: string; avatar_url: string | null };
    line: {
        name: string | null;
        messages: number;
        has_audio: boolean;
        body: string | null;
    };
    holder_name: string | null;
    order: CheckoutOrderState;
    payment: {
        provider: 'openpay' | null;
        openpay: {
            merchant_id: string;
            public_key: string;
            sandbox: boolean;
        } | null;
    } | null;
    urls: {
        online_payment: string;
        confirmation: string;
        status: string;
        cancel: string;
        retry: string;
        back: string;
    };
}>();

const { money } = useI18n();

const firstName = computed(() => props.athlete.name.split(' ')[0]);
const amount = computed(() =>
    money(props.order.total_minor, props.order.currency),
);
const state = ref(props.order.payment_state);
const cancelForm = useForm({});
const cancelError = computed(
    () => (cancelForm.errors as Record<string, string | undefined>).form,
);
let timer: ReturnType<typeof setInterval> | null = null;

const finalStates = ['paid', 'failed', 'cancelled', 'refunded'];

async function poll() {
    try {
        const response = await getJson<{ data: CheckoutOrderState }>(
            props.urls.status,
        );
        state.value = response.data.payment_state;

        if (finalStates.includes(state.value) && timer !== null) {
            clearInterval(timer);
            timer = null;
        }
    } catch {
        // Keep polling: a dropped request is not a payment result.
    }
}

onMounted(() => {
    if (props.step === 'confirmation' && !finalStates.includes(state.value)) {
        timer = setInterval(poll, 3000);
        void poll();
    }
});

onBeforeUnmount(() => {
    if (timer !== null) {
        clearInterval(timer);
    }
});

function onPaid() {
    router.visit(props.urls.confirmation);
}

function cancel() {
    cancelForm.post(props.urls.cancel);
}
</script>

<template>
    <Head :title="$t('Pago de tu mensaje')">
        <meta name="robots" content="noindex, nofollow" />
    </Head>

    <main
        class="min-h-dvh bg-fl-paper pt-[env(safe-area-inset-top)] pr-[env(safe-area-inset-right)] pb-[env(safe-area-inset-bottom)] pl-[env(safe-area-inset-left)] text-foreground"
    >
        <div class="mx-auto w-full max-w-xl px-4 pt-8 pb-16 sm:px-6 sm:pt-12">
            <div class="flex items-center justify-between gap-3">
                <img
                    src="/images/brand/logo/fl-monograma.png"
                    alt="Finisher Legacy"
                    width="40"
                    height="40"
                    class="size-10 object-contain"
                />
                <span
                    class="inline-flex items-center gap-1 text-xs font-medium text-muted-foreground"
                >
                    <Lock class="size-3.5" aria-hidden="true" />
                    {{ $t('Pago seguro') }}
                </span>
            </div>

            <!-- Summary -->
            <section class="fl-card mt-8 p-6 sm:p-8">
                <p class="fl-eyebrow">{{ $t('Envíame ánimo') }}</p>
                <h1 class="fl-display mt-2 text-2xl leading-tight sm:text-3xl">
                    {{ $t('Tu mensaje para {name}', { name: firstName }) }}
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">{{ title }}</p>

                <div
                    class="mt-6 flex items-start gap-3 rounded-2xl bg-fl-cream px-4 py-3"
                >
                    <MessageCircleHeart
                        class="mt-0.5 size-5 shrink-0 text-fl-gold-ink"
                        aria-hidden="true"
                    />
                    <div class="min-w-0 text-sm">
                        <p class="font-semibold">
                            {{
                                line.has_audio
                                    ? $t('Mensaje de voz')
                                    : $t('Mensaje de texto')
                            }}
                        </p>
                        <p
                            v-if="line.body"
                            class="mt-0.5 break-words text-muted-foreground"
                        >
                            «{{ line.body }}»
                        </p>
                    </div>
                </div>

                <dl class="mt-6 space-y-2 border-t border-border pt-4 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">
                            {{ line.name ?? $t('Mensaje de ánimo') }}
                        </dt>
                        <dd class="font-semibold">{{ amount }}</dd>
                    </div>
                    <div
                        v-if="line.messages > 1"
                        class="text-xs text-muted-foreground"
                    >
                        {{
                            $t(
                                'Incluye este mensaje y {count} más para tus siguientes mensajes desde este navegador.',
                                { count: line.messages - 1 },
                            )
                        }}
                    </div>
                </dl>
            </section>

            <!-- Pay -->
            <section v-if="step === 'pay'" class="fl-card mt-6 p-6 sm:p-8">
                <h2 class="font-heading text-lg font-semibold">
                    {{ $t('Paga con tarjeta') }}
                </h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{
                        $t(
                            '{name} recibirá tu mensaje en cuanto confirmemos el pago. Si no lo pagas, se borra y no se cobra nada.',
                            { name: firstName },
                        )
                    }}
                </p>

                <div class="mt-6">
                    <OpenPayCardForm
                        v-if="
                            payment?.provider === 'openpay' && payment.openpay
                        "
                        :order-uuid="order.uuid"
                        :config="payment.openpay"
                        :amount-label="amount"
                        :holder-name="holder_name"
                        :payment-url="urls.online_payment"
                        @succeeded="onPaid"
                    />
                    <p
                        v-else
                        class="flex items-start gap-2 rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900"
                        role="alert"
                    >
                        <CircleAlert
                            class="mt-0.5 size-4 shrink-0"
                            aria-hidden="true"
                        />
                        {{
                            $t(
                                'Por ahora no podemos cobrar mensajes adicionales. Intenta más tarde.',
                            )
                        }}
                    </p>
                </div>

                <p
                    v-if="cancelError"
                    class="mt-4 text-sm text-red-700"
                    role="alert"
                >
                    {{ cancelError }}
                </p>

                <button
                    type="button"
                    class="fl-link mt-6 inline-flex min-h-11 items-center text-sm font-medium disabled:opacity-50"
                    :disabled="cancelForm.processing"
                    @click="cancel"
                >
                    {{
                        cancelForm.processing
                            ? $t('Cancelando…')
                            : $t('Cancelar y borrar mi mensaje')
                    }}
                </button>
            </section>

            <!-- Confirmation (server state only) -->
            <section
                v-else
                class="fl-card mt-6 px-6 py-10 text-center"
                aria-live="polite"
            >
                <template v-if="state === 'paid'">
                    <span
                        class="mx-auto flex size-16 items-center justify-center rounded-full bg-fl-black text-white"
                        aria-hidden="true"
                    >
                        <Check class="size-7" />
                    </span>
                    <h2 class="mt-5 font-heading text-2xl font-semibold">
                        {{ $t('¡Pago confirmado!') }}
                    </h2>
                    <p
                        class="mx-auto mt-2 max-w-sm text-sm leading-relaxed text-muted-foreground"
                    >
                        {{
                            $t(
                                '{name} ya tiene tu mensaje. Lo escuchará durante su entrenamiento.',
                                { name: firstName },
                            )
                        }}
                    </p>
                    <a :href="urls.back" class="fl-btn-secondary fl-press mt-8">
                        {{ $t('Enviar otro mensaje') }}
                    </a>
                </template>

                <template
                    v-else-if="state === 'failed' || state === 'cancelled'"
                >
                    <span
                        class="mx-auto flex size-16 items-center justify-center rounded-full bg-red-50 text-red-700"
                        aria-hidden="true"
                    >
                        <CircleAlert class="size-7" />
                    </span>
                    <h2 class="mt-5 font-heading text-2xl font-semibold">
                        {{
                            state === 'failed'
                                ? $t('No se pudo completar el pago')
                                : $t('Este pago se canceló')
                        }}
                    </h2>
                    <p
                        class="mx-auto mt-2 max-w-sm text-sm leading-relaxed text-muted-foreground"
                    >
                        {{
                            state === 'failed'
                                ? $t(
                                      'No se hizo ningún cargo. Puedes intentarlo de nuevo con otra tarjeta.',
                                  )
                                : $t(
                                      'Tu mensaje no se envió y no se hizo ningún cargo.',
                                  )
                        }}
                    </p>
                    <a
                        v-if="state === 'failed' && order.payable"
                        :href="urls.retry"
                        class="fl-btn-primary fl-press mt-8 inline-flex items-center gap-2"
                    >
                        <RotateCcw class="size-4" aria-hidden="true" />
                        {{ $t('Intentar de nuevo') }}
                    </a>
                    <a
                        v-else
                        :href="urls.back"
                        class="fl-btn-secondary fl-press mt-8"
                    >
                        {{ $t('Volver') }}
                    </a>
                </template>

                <template v-else>
                    <span
                        class="mx-auto flex size-16 items-center justify-center rounded-full bg-fl-cream text-fl-gold-ink"
                        aria-hidden="true"
                    >
                        <Clock class="size-7" />
                    </span>
                    <h2 class="mt-5 font-heading text-2xl font-semibold">
                        {{ $t('Confirmando tu pago…') }}
                    </h2>
                    <p
                        class="mx-auto mt-2 max-w-sm text-sm leading-relaxed text-muted-foreground"
                    >
                        {{
                            $t(
                                'Esto toma unos segundos. No cierres esta página; tu mensaje se entrega en cuanto el banco lo confirme.',
                            )
                        }}
                    </p>
                    <Spinner class="mx-auto mt-6 size-6" />
                </template>
            </section>
        </div>
    </main>
</template>
