<script setup lang="ts">
/**
 * Where Stripe's return_url and OpenPay's 3D Secure redirect land (and
 * where checkout goes after a successful card payment). It shows ONLY the
 * server's state — never what the redirect claims — and polls `estado`
 * (which re-reads an OpenPay charge server-side) while the payment settles:
 * procesando → confirmado, or error with a retry. Realtime
 * (`order.updated` on the user's private channel) refreshes it sooner when
 * available; polling is the fallback.
 */
import { Head, Link, usePage } from '@inertiajs/vue3';
import { CircleAlert, CircleCheck, Clock, Nfc, ShoppingBag } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import Money from '@/components/shared/Money.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import type { CheckoutOrderState } from '@/lib/commerce';
import { getJson } from '@/lib/commerce';
import { getEcho } from '@/lib/echo';

type Line = {
    id: number;
    quantity: number;
    product_name: string;
    size: string | null;
    color: string | null;
    line_total_minor: number | null;
    image_url: string | null;
    connected: boolean;
};

const props = defineProps<{
    order: CheckoutOrderState;
    items: Line[];
    currency: string;
    total_minor: number;
    delivery: { method: 'pickup' | 'shipping' } | null;
}>();

const state = ref<CheckoutOrderState>(props.order);
const attempts = ref(0);
const MAX_ATTEMPTS = 24; // ~1 minute at 2.5 s
let timer: ReturnType<typeof setTimeout> | null = null;

const view = computed<'confirmed' | 'processing' | 'waiting' | 'error'>(() => {
    switch (state.value.payment_state) {
        case 'paid':
            return 'confirmed';
        case 'failed':
        case 'cancelled':
        case 'refunded':
            return 'error';
        default:
            return attempts.value >= MAX_ATTEMPTS ? 'waiting' : 'processing';
    }
});

async function refresh() {
    try {
        const { data } = await getJson<{ data: CheckoutOrderState }>(
            `/checkout/${props.order.uuid}/estado`,
        );
        state.value = data;
    } catch {
        // Keep the last known state; the next tick retries.
    }
}

function schedule() {
    if (view.value !== 'processing') {
        return;
    }

    timer = setTimeout(async () => {
        attempts.value += 1;
        await refresh();
        schedule();
    }, 2500);
}

const page = usePage();
const channelName = computed(() => {
    const id = (page.props.auth as { user?: { id?: number } } | undefined)?.user
        ?.id;

    return id ? `App.Models.User.${id}` : null;
});

onMounted(() => {
    schedule();

    const echo = getEcho();

    if (echo && channelName.value) {
        echo.private(channelName.value).listen(
            '.order.updated',
            (payload: { uuid?: string }) => {
                if (payload.uuid === props.order.uuid) {
                    void refresh();
                }
            },
        );
    }
});

onBeforeUnmount(() => {
    if (timer) {
        clearTimeout(timer);
    }

    if (channelName.value) {
        getEcho()?.leave(channelName.value);
    }
});

function checkAgain() {
    attempts.value = 0;
    void refresh().then(schedule);
}
</script>

<template>
    <Head
        :title="
            view === 'confirmed' ? 'Pedido confirmado' : 'Confirmando tu pago'
        "
    />

    <div class="min-h-screen bg-background">
        <div class="fl-container max-w-2xl py-14 md:py-20">
            <section class="fl-card p-6 text-center md:p-10" aria-live="polite">
                <template v-if="view === 'confirmed'">
                    <span
                        class="mx-auto flex size-16 items-center justify-center rounded-full bg-emerald-50 text-emerald-700"
                    >
                        <CircleCheck class="size-8" />
                    </span>
                    <p class="fl-eyebrow mt-6">
                        Pedido #{{ state.order_number }}
                    </p>
                    <h1 class="mt-2 font-serif text-3xl font-semibold">
                        ¡Listo! Tu pago está confirmado.
                    </h1>
                    <p class="mx-auto mt-3 max-w-md text-muted-foreground">
                        Te avisaremos cuando tu pedido esté listo para
                        {{
                            delivery?.method === 'shipping'
                                ? 'enviarse'
                                : 'entregarse'
                        }}.
                        <template v-if="items.some((i) => i.connected)">
                            Tus productos conectados aparecerán en Mi equipo
                            cuando los recibas.
                        </template>
                    </p>
                </template>

                <template v-else-if="view === 'error'">
                    <span
                        class="mx-auto flex size-16 items-center justify-center rounded-full bg-red-50 text-red-700"
                    >
                        <CircleAlert class="size-8" />
                    </span>
                    <h1 class="mt-6 font-serif text-3xl font-semibold">
                        {{
                            state.payment_state === 'cancelled'
                                ? 'Este pedido ya no está activo.'
                                : 'No pudimos completar el pago.'
                        }}
                    </h1>
                    <p class="mx-auto mt-3 max-w-md text-muted-foreground">
                        {{
                            state.payable
                                ? 'No se hizo ningún cargo. Puedes intentarlo de nuevo con otra tarjeta.'
                                : 'El tiempo para pagarlo terminó y liberamos las piezas. Puedes volver a armar tu pedido.'
                        }}
                    </p>
                    <Button
                        v-if="state.payable"
                        as-child
                        class="mt-6 h-12 rounded-full px-6"
                    >
                        <Link :href="`/checkout/${state.uuid}`"
                            >Intentar de nuevo</Link
                        >
                    </Button>
                    <Button v-else as-child class="mt-6 h-12 rounded-full px-6">
                        <Link href="/tienda">Volver a la tienda</Link>
                    </Button>
                </template>

                <template v-else>
                    <span
                        class="mx-auto flex size-16 items-center justify-center rounded-full bg-fl-cream text-fl-gold-ink"
                    >
                        <Spinner v-if="view === 'processing'" class="size-7" />
                        <Clock v-else class="size-7" />
                    </span>
                    <p class="fl-eyebrow mt-6">
                        Pedido #{{ state.order_number }}
                    </p>
                    <h1 class="mt-2 font-serif text-3xl font-semibold">
                        Estamos confirmando tu pago…
                    </h1>
                    <p class="mx-auto mt-3 max-w-md text-muted-foreground">
                        <template v-if="view === 'processing'">
                            Suele tardar unos segundos. No hace falta que
                            recargues la página.
                        </template>
                        <template v-else>
                            Tu banco todavía no responde. Puedes cerrar esta
                            página: el pedido se actualizará solo en Mis
                            pedidos.
                        </template>
                    </p>
                    <div
                        v-if="view === 'waiting'"
                        class="mt-6 flex flex-wrap justify-center gap-3"
                    >
                        <Button
                            variant="outline"
                            class="h-11 rounded-full"
                            @click="checkAgain"
                            >Revisar de nuevo</Button
                        >
                        <Button
                            v-if="
                                state.payable &&
                                state.payment_state === 'pending'
                            "
                            as-child
                            variant="ghost"
                            class="h-11 rounded-full"
                        >
                            <Link :href="`/checkout/${state.uuid}`"
                                >Volver al pago</Link
                            >
                        </Button>
                    </div>
                </template>
            </section>

            <section class="fl-card mt-6 p-6">
                <ul class="space-y-3">
                    <li
                        v-for="line in items"
                        :key="line.id"
                        class="flex items-center gap-3 text-sm"
                    >
                        <div
                            class="aspect-[4/5] w-12 shrink-0 overflow-hidden rounded-lg bg-fl-cream"
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
                                class="m-auto mt-3 size-5 text-muted-foreground/60"
                            />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium">
                                {{ line.quantity }} × {{ line.product_name }}
                            </p>
                            <p class="truncate text-muted-foreground">
                                {{
                                    [
                                        line.size ? `Talla ${line.size}` : null,
                                        line.color,
                                    ]
                                        .filter(Boolean)
                                        .join(' · ')
                                }}
                                <span
                                    v-if="line.connected"
                                    class="inline-flex items-center gap-1 text-fl-gold-ink"
                                    ><Nfc class="size-3" /> conectado</span
                                >
                            </p>
                        </div>
                        <Money
                            v-if="line.line_total_minor !== null"
                            class="legacy-numeric"
                            :minor="line.line_total_minor"
                            :currency="currency"
                        />
                    </li>
                </ul>
                <div
                    class="mt-4 flex items-baseline justify-between border-t border-border pt-4 font-semibold"
                >
                    <span>Total</span>
                    <Money
                        class="legacy-numeric text-lg"
                        :minor="total_minor"
                        :currency="currency"
                    />
                </div>
            </section>

            <div class="mt-6 flex flex-wrap justify-center gap-3">
                <Button as-child variant="outline" class="h-11 rounded-full">
                    <Link :href="`/mis-pedidos/${state.uuid}`"
                        >Ver mi pedido</Link
                    >
                </Button>
                <Button as-child variant="ghost" class="h-11 rounded-full">
                    <Link href="/tienda">Seguir en la tienda</Link>
                </Button>
            </div>
        </div>
    </div>
</template>
