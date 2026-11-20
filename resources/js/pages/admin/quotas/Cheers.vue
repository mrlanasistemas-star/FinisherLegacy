<script setup lang="ts">
/**
 * Administración → Límites y tarifas → Mensajes de ánimo
 * (CheerPricingController, docs/renovacion/tarjeta-animo.md §Precios).
 * One card per channel — the shared link and the Tarjeta de ánimo NFC —
 * with: free messages, what happens after them (blocked / free / paid by
 * the SENDER on the web), optional hard limit, price per extra message,
 * price steps ("banderas"), packs and anonymity. Amounts are typed in
 * pesos (MXN); counts only, never message contents.
 */
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CircleAlert,
    Flag,
    Link2,
    MessageCircleHeart,
    Nfc,
    Package,
    Plus,
    Trash2,
} from '@lucide/vue';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index as quotasIndex } from '@/routes/admin/quotas';
import { update as updateCheers } from '@/routes/admin/quotas/cheers';

type Channel = {
    channel: 'link' | 'nfc_card';
    label: string;
    saved: boolean;
    free_messages: number;
    after_free: 'blocked' | 'free' | 'paid';
    hard_limit: number | null;
    price_minor: number | null;
    currency: string;
    price_steps: { from_paid: number; amount_minor: number }[];
    packs: { messages: number; amount_minor: number }[];
    allow_anonymous: boolean;
    updated_at: string | null;
    stats: {
        free: number;
        paid: number;
        credit: number;
        pending: number;
        revenue_minor: number;
    };
};

const props = defineProps<{
    channels: Channel[];
    defaults: { free_messages: number; link_cap: number };
    payment_ready: boolean;
    card_product: { published: boolean; active_cards: number };
}>();

const pesos = (minor: number | null) =>
    minor === null ? '' : String(minor / 100);
const mxn = (minor: number) =>
    new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
    }).format(minor / 100) + ' MXN';

/** One form per channel, prefilled from the saved settings (or defaults). */
const forms = Object.fromEntries(
    props.channels.map((c) => [
        c.channel,
        useForm({
            free_messages: c.free_messages,
            after_free: c.after_free,
            limited: c.hard_limit !== null,
            hard_limit: c.hard_limit ?? ('' as number | ''),
            price: pesos(c.price_minor),
            price_steps: c.price_steps.map((s) => ({
                from_paid: s.from_paid,
                price: pesos(s.amount_minor),
            })),
            packs: c.packs.map((p) => ({
                messages: p.messages,
                price: pesos(p.amount_minor),
            })),
            allow_anonymous: c.allow_anonymous,
        }),
    ]),
);

const afterOptions = [
    {
        value: 'blocked',
        label: 'Bloquear',
        hint: 'No acepta más mensajes (como hoy).',
    },
    {
        value: 'free',
        label: 'Gratis sin límite',
        hint: 'Sigue aceptando sin cobrar (hasta el límite total, si lo hay).',
    },
    {
        value: 'paid',
        label: 'Cobrar a quien envía',
        hint: 'Quien manda el mensaje lo paga con tarjeta en la web.',
    },
] as const;

const errorsOf = (channel: string) =>
    forms[channel].errors as Record<string, string>;

function addStep(channel: string) {
    const form = forms[channel];
    const last = form.price_steps[form.price_steps.length - 1];
    form.price_steps.push({
        from_paid: last ? last.from_paid + 10 : 11,
        price: '',
    });
}

function addPack(channel: string) {
    const form = forms[channel];
    const last = form.packs[form.packs.length - 1];
    form.packs.push({ messages: last ? last.messages + 2 : 3, price: '' });
}

function save(channel: string) {
    forms[channel]
        .transform((data) => ({
            free_messages: Number(data.free_messages),
            after_free: data.after_free,
            hard_limit:
                data.limited && data.hard_limit !== ''
                    ? Number(data.hard_limit)
                    : null,
            price:
                data.after_free === 'paid' && data.price !== ''
                    ? data.price
                    : null,
            price_steps: data.after_free === 'paid' ? data.price_steps : [],
            packs: data.after_free === 'paid' ? data.packs : [],
            allow_anonymous: data.allow_anonymous,
        }))
        .put(updateCheers.url(channel), { preserveScroll: true });
}

const totals = computed(() =>
    props.channels.reduce(
        (sum, c) => ({
            messages:
                sum.messages + c.stats.free + c.stats.paid + c.stats.credit,
            revenue: sum.revenue + c.stats.revenue_minor,
            pending: sum.pending + c.stats.pending,
        }),
        { messages: 0, revenue: 0, pending: 0 },
    ),
);
</script>

<template>
    <Head title="Mensajes de ánimo" />

    <div class="w-full space-y-8 px-4 py-6 md:px-6 md:py-8 xl:px-8">
        <div class="flex flex-col gap-4">
            <Link
                :href="quotasIndex.url()"
                class="inline-flex min-h-10 w-fit items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
            >
                <ArrowLeft class="size-4" /> Límites y tarifas
            </Link>
            <div class="flex items-center gap-3">
                <span
                    class="flex size-11 items-center justify-center rounded-xl bg-amber-50 text-amber-700"
                    ><MessageCircleHeart class="size-5"
                /></span>
                <div>
                    <h1 class="text-xl font-semibold">Mensajes de ánimo</h1>
                    <p class="text-sm text-muted-foreground">
                        Cuántos mensajes recibe gratis cada enlace «Envíame
                        ánimo» y cada Tarjeta de ánimo NFC, y qué pasa después.
                        Los cambios aplican a los mensajes nuevos.
                    </p>
                </div>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="fl-card p-5">
                <p class="text-xs text-muted-foreground">Mensajes entregados</p>
                <p class="mt-1 text-2xl font-semibold">{{ totals.messages }}</p>
            </div>
            <div class="fl-card p-5">
                <p class="text-xs text-muted-foreground">
                    Cobrado por mensajes
                </p>
                <p class="mt-1 text-2xl font-semibold">
                    {{ mxn(totals.revenue) }}
                </p>
            </div>
            <div class="fl-card p-5">
                <p class="text-xs text-muted-foreground">Esperando pago</p>
                <p class="mt-1 text-2xl font-semibold">{{ totals.pending }}</p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Se borran solos si no se pagan.
                </p>
            </div>
            <div class="fl-card p-5">
                <p class="text-xs text-muted-foreground">Tarjetas activas</p>
                <p class="mt-1 text-2xl font-semibold">
                    {{ card_product.active_cards }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{
                        card_product.published
                            ? 'La tarjeta está a la venta.'
                            : 'Aún no está a la venta: php artisan catalog:publish-cheer-card --price=…'
                    }}
                </p>
            </div>
        </div>

        <p
            v-if="!payment_ready"
            class="flex items-start gap-2 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900"
        >
            <CircleAlert class="mt-0.5 size-4 shrink-0" />
            OpenPay no está configurado (Configuración → Pagos). Mientras tanto,
            un canal en «Cobrar a quien envía» no acepta mensajes adicionales y
            quien envía ve un aviso claro.
        </p>

        <div class="grid gap-6 xl:grid-cols-2">
            <form
                v-for="c in channels"
                :key="c.channel"
                class="fl-card space-y-6 p-6"
                @submit.prevent="save(c.channel)"
            >
                <div class="flex items-center gap-3">
                    <span
                        class="flex size-10 items-center justify-center rounded-xl bg-muted"
                    >
                        <Link2 v-if="c.channel === 'link'" class="size-5" />
                        <Nfc v-else class="size-5" />
                    </span>
                    <div>
                        <h2 class="font-semibold">
                            {{
                                c.channel === 'link'
                                    ? 'Enlace «Envíame ánimo»'
                                    : 'Tarjeta de ánimo NFC'
                            }}
                        </h2>
                        <p class="text-xs text-muted-foreground">
                            {{
                                c.saved
                                    ? 'Configurado'
                                    : 'Sin configurar: se usan los valores de siempre'
                            }}
                            · {{ c.stats.free }} gratis ·
                            {{ c.stats.paid }} pagados · {{ c.stats.credit }} de
                            paquetes
                        </p>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <Label :for="`free-${c.channel}`"
                            >Mensajes gratis por
                            {{
                                c.channel === 'link' ? 'enlace' : 'tarjeta'
                            }}</Label
                        >
                        <Input
                            :id="`free-${c.channel}`"
                            v-model="forms[c.channel].free_messages"
                            type="number"
                            min="0"
                            class="mt-1.5"
                        />
                        <InputError
                            :message="errorsOf(c.channel).free_messages"
                        />
                    </div>
                    <div>
                        <Label :for="`limit-${c.channel}`">Límite total</Label>
                        <div class="mt-1.5 flex items-center gap-2">
                            <label
                                class="flex min-h-10 items-center gap-2 text-sm"
                            >
                                <input
                                    v-model="forms[c.channel].limited"
                                    type="checkbox"
                                    class="size-4 accent-black"
                                />
                                Con límite
                            </label>
                            <Input
                                v-if="forms[c.channel].limited"
                                :id="`limit-${c.channel}`"
                                v-model="forms[c.channel].hard_limit"
                                type="number"
                                min="1"
                                class="max-w-32"
                            />
                            <span v-else class="text-sm text-muted-foreground"
                                >Sin límite</span
                            >
                        </div>
                        <InputError :message="errorsOf(c.channel).hard_limit" />
                    </div>
                </div>

                <fieldset>
                    <legend class="text-sm font-medium">
                        Después de los gratis
                    </legend>
                    <div class="mt-2 grid gap-2 sm:grid-cols-3">
                        <label
                            v-for="option in afterOptions"
                            :key="option.value"
                            class="cursor-pointer rounded-xl border p-3 text-sm transition-colors has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-amber-400"
                            :class="
                                forms[c.channel].after_free === option.value
                                    ? 'border-foreground bg-foreground text-background'
                                    : 'border-border hover:border-amber-400'
                            "
                        >
                            <input
                                v-model="forms[c.channel].after_free"
                                type="radio"
                                :name="`after-${c.channel}`"
                                :value="option.value"
                                class="sr-only"
                            />
                            <span class="font-medium">{{ option.label }}</span>
                            <span class="mt-1 block text-xs opacity-80">{{
                                option.hint
                            }}</span>
                        </label>
                    </div>
                    <InputError :message="errorsOf(c.channel).after_free" />
                </fieldset>

                <div
                    v-if="forms[c.channel].after_free === 'paid'"
                    class="space-y-5 rounded-xl border border-border p-4"
                >
                    <div>
                        <Label :for="`price-${c.channel}`"
                            >Precio por mensaje adicional (MXN)</Label
                        >
                        <Input
                            :id="`price-${c.channel}`"
                            v-model="forms[c.channel].price"
                            type="number"
                            min="1"
                            step="0.01"
                            placeholder="Escribe el precio"
                            class="mt-1.5 max-w-48"
                        />
                        <InputError :message="errorsOf(c.channel).price" />
                    </div>

                    <div>
                        <p
                            class="flex items-center gap-1.5 text-sm font-medium"
                        >
                            <Flag class="size-4" /> Banderas (opcional)
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Desde el mensaje pagado número N, cada mensaje
                            cuesta otro precio.
                        </p>
                        <div
                            v-for="(step, i) in forms[c.channel].price_steps"
                            :key="i"
                            class="mt-2 flex flex-wrap items-end gap-2"
                        >
                            <div>
                                <Label class="text-xs">Desde el pagado #</Label>
                                <Input
                                    v-model="step.from_paid"
                                    type="number"
                                    min="2"
                                    class="mt-1 w-28"
                                />
                            </div>
                            <div>
                                <Label class="text-xs">Precio (MXN)</Label>
                                <Input
                                    v-model="step.price"
                                    type="number"
                                    min="1"
                                    step="0.01"
                                    class="mt-1 w-32"
                                />
                            </div>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label="Quitar bandera"
                                @click="
                                    forms[c.channel].price_steps.splice(i, 1)
                                "
                                ><Trash2 class="size-4"
                            /></Button>
                            <InputError
                                class="w-full"
                                :message="
                                    errorsOf(c.channel)[
                                        `price_steps.${i}.from_paid`
                                    ] ||
                                    errorsOf(c.channel)[
                                        `price_steps.${i}.price`
                                    ]
                                "
                            />
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="mt-2 rounded-full"
                            @click="addStep(c.channel)"
                            ><Plus class="size-4" /> Agregar bandera</Button
                        >
                    </div>

                    <div>
                        <p
                            class="flex items-center gap-1.5 text-sm font-medium"
                        >
                            <Package class="size-4" /> Paquetes (opcional)
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Quien envía paga varios de una vez; los que sobran
                            quedan guardados en su navegador.
                        </p>
                        <div
                            v-for="(pack, i) in forms[c.channel].packs"
                            :key="i"
                            class="mt-2 flex flex-wrap items-end gap-2"
                        >
                            <div>
                                <Label class="text-xs">Mensajes</Label>
                                <Input
                                    v-model="pack.messages"
                                    type="number"
                                    min="2"
                                    class="mt-1 w-24"
                                />
                            </div>
                            <div>
                                <Label class="text-xs">Precio (MXN)</Label>
                                <Input
                                    v-model="pack.price"
                                    type="number"
                                    min="1"
                                    step="0.01"
                                    class="mt-1 w-32"
                                />
                            </div>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label="Quitar paquete"
                                @click="forms[c.channel].packs.splice(i, 1)"
                                ><Trash2 class="size-4"
                            /></Button>
                            <InputError
                                class="w-full"
                                :message="
                                    errorsOf(c.channel)[
                                        `packs.${i}.messages`
                                    ] || errorsOf(c.channel)[`packs.${i}.price`]
                                "
                            />
                        </div>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="mt-2 rounded-full"
                            @click="addPack(c.channel)"
                            ><Plus class="size-4" /> Agregar paquete</Button
                        >
                    </div>
                </div>

                <label
                    class="flex min-h-11 cursor-pointer items-start gap-3 text-sm"
                >
                    <input
                        v-model="forms[c.channel].allow_anonymous"
                        type="checkbox"
                        class="mt-0.5 size-4 accent-black"
                    />
                    <span>
                        <span class="font-medium"
                            >Permitir mensajes anónimos</span
                        >
                        <span class="block text-xs text-muted-foreground"
                            >El nombre se vuelve opcional; el atleta ve
                            «Anónimo». Bloquear y reportar siguen
                            funcionando.</span
                        >
                    </span>
                </label>

                <p
                    v-if="c.channel === 'link'"
                    class="text-xs text-muted-foreground"
                >
                    Cada enlace conserva además su tope de seguridad de
                    {{ defaults.link_cap }} mensajes en total
                    (TRAINING_CHEER_MAX_MESSAGES_PER_LINK).
                </p>

                <div class="flex justify-end">
                    <Button
                        type="submit"
                        class="rounded-full"
                        :disabled="forms[c.channel].processing"
                        >{{
                            forms[c.channel].processing
                                ? 'Guardando…'
                                : 'Guardar'
                        }}</Button
                    >
                </div>
            </form>
        </div>
    </div>
</template>
