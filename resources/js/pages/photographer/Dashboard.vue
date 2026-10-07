<script setup lang="ts">
/**
 * Portal del fotógrafo — panel. What sold, what payment processing and Finisher
 * Legacy kept, what the photographer earns and what is still pending
 * payout; plus profile and bank (CLABE) data for transfers.
 */
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    BadgeCheck,
    Building2,
    Camera,
    Clock,
    CreditCard,
    Hourglass,
    Landmark,
    ShoppingBag,
    Upload,
    Wallet,
    XCircle,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import FeeBreakdown from '@/components/photos/FeeBreakdown.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { timeAgo } from '@/lib/datetime';
import type { PhotoFeeRules } from '@/lib/photoFees';

type Profile = {
    uuid: string;
    display_name: string;
    bio: string | null;
    city: string | null;
    phone: string | null;
    instagram_url: string | null;
    portfolio_url: string | null;
    status: 'pending' | 'approved' | 'suspended';
    status_label: string;
    default_price_minor: number;
    payout_holder: string | null;
    payout_bank: string | null;
    payout_clabe_masked: string | null;
};

type Sale = {
    uuid: string;
    thumb_url: string | null;
    event: string | null;
    gross_minor: number;
    processor_fee_minor: number;
    platform_fee_minor: number;
    net_minor: number;
    payout_status: string;
    created_at: string;
};

const props = defineProps<{
    profile: Profile | null;
    fees: PhotoFeeRules;
    stats?: Record<string, number>;
    monthly?: { month: string; net_minor: number; sales: number }[];
    recentSales?: Sale[];
}>();

const money = (minor: number) =>
    new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
    }).format(minor / 100);

const cards = computed(() =>
    props.stats
        ? [
              {
                  label: 'Ingresos (neto)',
                  value: money(props.stats.net_minor),
                  icon: Wallet,
                  tone: 'bg-emerald-100 text-emerald-700',
              },
              {
                  label: 'Saldo pendiente',
                  value: money(props.stats.pending_payout_minor),
                  icon: Hourglass,
                  tone: 'bg-amber-100 text-amber-700',
              },
              {
                  label: 'Fotos vendidas',
                  value: String(props.stats.sales),
                  icon: ShoppingBag,
                  tone: 'bg-sky-100 text-sky-700',
              },
              {
                  label: 'Fotos publicadas',
                  value: String(props.stats.published),
                  icon: Camera,
                  tone: 'bg-fl-cream text-fl-gold-ink',
              },
          ]
        : [],
);

/** Getting-started checklist — every item from real profile/stat data. */
const steps = computed(() =>
    props.profile && props.stats
        ? [
              {
                  label: 'Perfil aprobado',
                  done: props.profile.status === 'approved',
                  href: null,
              },
              {
                  label: 'Datos bancarios para tus pagos',
                  done: !!props.profile.payout_clabe_masked,
                  href: '#perfil',
              },
              {
                  label: 'Primeras fotos subidas',
                  done:
                      props.stats.published +
                          props.stats.review +
                          props.stats.rejected >
                      0,
                  href: '/fotografo/fotos',
              },
              {
                  label: 'Primera venta',
                  done: props.stats.sales > 0,
                  href: null,
              },
          ]
        : [],
);
const stepsDone = computed(() => steps.value.filter((s) => s.done).length);
const hasMonthly = computed(() =>
    (props.monthly ?? []).some((m) => m.net_minor > 0),
);

const maxMonth = computed(() =>
    Math.max(1, ...(props.monthly ?? []).map((m) => m.net_minor)),
);
const monthLabel = (key: string) =>
    new Date(`${key}-01T12:00:00`)
        .toLocaleDateString('es-MX', { month: 'short' })
        .replace('.', '');

const statusBanner: Record<
    string,
    { icon: typeof Clock; class: string; text: string }
> = {
    pending: {
        icon: Clock,
        class: 'border-amber-200 bg-amber-50 text-amber-900',
        text: 'Estamos revisando tu solicitud. Te avisaremos cuando puedas empezar a subir fotos.',
    },
    approved: {
        icon: BadgeCheck,
        class: 'border-emerald-200 bg-emerald-50 text-emerald-900',
        text: 'Perfil aprobado: ya puedes subir y vender tus fotos.',
    },
    suspended: {
        icon: XCircle,
        class: 'border-red-200 bg-red-50 text-red-900',
        text: 'Tu perfil está suspendido. Contáctanos para revisarlo.',
    },
};

const examplePrice = ref(
    (props.profile?.default_price_minor ?? props.fees.default_price_minor) /
        100,
);

const form = useForm({
    display_name: props.profile?.display_name ?? '',
    bio: props.profile?.bio ?? '',
    city: props.profile?.city ?? '',
    phone: props.profile?.phone ?? '',
    instagram_url: props.profile?.instagram_url ?? '',
    portfolio_url: props.profile?.portfolio_url ?? '',
    payout_holder: props.profile?.payout_holder ?? '',
    payout_bank: props.profile?.payout_bank ?? '',
    payout_clabe: '',
});

function save() {
    form.transform((data) => ({
        ...data,
        payout_clabe: data.payout_clabe || undefined,
    })).patch('/fotografo/perfil', {
        preserveScroll: true,
        onSuccess: () => form.reset('payout_clabe'),
    });
}
</script>

<template>
    <Head title="Portal del fotógrafo" />

    <div class="mx-auto w-full max-w-6xl space-y-8 p-4 md:p-8">
        <div
            v-if="!profile"
            class="fl-card flex flex-col items-center gap-4 p-12 text-center"
        >
            <Camera class="size-8 text-fl-gold-ink" />
            <h1 class="font-serif text-3xl">
                Aún no tienes perfil de fotógrafo
            </h1>
            <Button as-child class="rounded-full"
                ><Link href="/fotografos/registro"
                    >Solicitar perfil</Link
                ></Button
            >
        </div>

        <template v-else>
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex items-center gap-3">
                    <span
                        class="flex size-12 items-center justify-center rounded-2xl bg-fl-cream text-fl-gold-ink"
                    >
                        <Camera class="size-5" />
                    </span>
                    <div>
                        <p class="fl-eyebrow">Portal del fotógrafo</p>
                        <h1 class="font-serif text-3xl">
                            {{ profile.display_name }}
                        </h1>
                    </div>
                </div>
                <Button
                    v-if="profile.status === 'approved'"
                    as-child
                    class="h-11 rounded-full px-6"
                >
                    <Link href="/fotografo/fotos"
                        ><Upload class="size-4" /> Subir fotos</Link
                    >
                </Button>
            </div>

            <div
                class="flex items-start gap-3 rounded-xl border p-4 text-sm"
                :class="statusBanner[profile.status].class"
            >
                <component
                    :is="statusBanner[profile.status].icon"
                    class="mt-0.5 size-4 shrink-0"
                />
                <p>
                    <strong>{{ profile.status_label }}.</strong>
                    {{ statusBanner[profile.status].text }}
                </p>
            </div>

            <div v-if="stats" class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <div
                    v-for="card in cards"
                    :key="card.label"
                    class="fl-card p-5"
                >
                    <span
                        class="flex size-10 items-center justify-center rounded-xl"
                        :class="card.tone"
                    >
                        <component :is="card.icon" class="size-4" />
                    </span>
                    <p class="legacy-numeric mt-4 text-2xl font-semibold">
                        {{ card.value }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ card.label }}
                    </p>
                </div>
            </div>

            <!-- Getting started -->
            <section
                v-if="steps.length && stepsDone < steps.length"
                class="fl-card p-5"
            >
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-sm font-semibold">Primeros pasos</h2>
                    <span class="legacy-numeric text-xs text-muted-foreground"
                        >{{ stepsDone }} de {{ steps.length }}</span
                    >
                </div>
                <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-muted">
                    <div
                        class="h-full rounded-full bg-fl-gold transition-[width] duration-700"
                        :style="{
                            width: `${(stepsDone / steps.length) * 100}%`,
                        }"
                    />
                </div>
                <ol class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                    <li v-for="(step, i) in steps" :key="step.label">
                        <component
                            :is="step.href && !step.done ? 'a' : 'div'"
                            :href="
                                step.href && !step.done ? step.href : undefined
                            "
                            class="flex h-full items-center gap-3 rounded-xl border px-3 py-2.5 text-sm"
                            :class="
                                step.done
                                    ? 'border-emerald-200 bg-emerald-50/60 text-emerald-900'
                                    : 'border-border hover:border-foreground/25'
                            "
                        >
                            <span
                                class="flex size-6 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                                :class="
                                    step.done
                                        ? 'bg-emerald-600 text-white'
                                        : 'border border-border text-muted-foreground'
                                "
                                >{{ step.done ? '✓' : i + 1 }}</span
                            >
                            {{ step.label }}
                        </component>
                    </li>
                </ol>
            </section>

            <div v-if="stats" class="grid grid-cols-1 gap-6 lg:grid-cols-5">
                <!-- Monthly chart -->
                <section class="fl-card relative p-5 lg:col-span-3">
                    <h2 class="text-sm font-semibold">
                        Ingresos netos por mes
                    </h2>
                    <p
                        v-if="!hasMonthly"
                        class="absolute inset-x-5 top-1/2 text-center text-sm text-muted-foreground"
                    >
                        Tus ingresos aparecerán aquí con tu primera venta.
                    </p>
                    <div
                        class="mt-6 flex h-48 items-end gap-3 border-b border-border"
                    >
                        <div
                            v-for="m in monthly"
                            :key="m.month"
                            class="flex flex-1 flex-col items-center gap-2"
                        >
                            <span
                                class="legacy-numeric text-[10px] text-muted-foreground"
                                >{{ m.sales }}</span
                            >
                            <div
                                class="w-full rounded-t-lg bg-gradient-to-t from-fl-gold-dim to-fl-gold transition-all"
                                :style="{
                                    height: `${Math.max((m.net_minor / maxMonth) * 100, m.net_minor ? 6 : 2)}%`,
                                }"
                                :title="money(m.net_minor)"
                            />
                            <span
                                class="text-[11px] text-muted-foreground capitalize"
                                >{{ monthLabel(m.month) }}</span
                            >
                        </div>
                    </div>
                </section>

                <!-- Where the money goes -->
                <section class="fl-card space-y-4 p-5 lg:col-span-2">
                    <h2 class="text-sm font-semibold">
                        Total vendido: {{ money(stats.gross_minor) }}
                    </h2>
                    <ul class="space-y-3 text-sm">
                        <li class="flex items-center gap-3">
                            <Wallet class="size-4 text-emerald-700" />
                            <span class="flex-1">Para ti</span>
                            <span class="legacy-numeric font-semibold">{{
                                money(stats.net_minor)
                            }}</span>
                        </li>
                        <li class="flex items-center gap-3">
                            <Building2 class="size-4 text-fl-gold-ink" />
                            <span class="flex-1"
                                >Finisher Legacy ({{
                                    fees.platform_commission_percent
                                }}%)</span
                            >
                            <span class="legacy-numeric">{{
                                money(stats.platform_fee_minor)
                            }}</span>
                        </li>
                        <li class="flex items-center gap-3">
                            <CreditCard class="size-4 text-sky-700" />
                            <span class="flex-1">Procesamiento de pago</span>
                            <span class="legacy-numeric">{{
                                money(stats.processor_fee_minor)
                            }}</span>
                        </li>
                        <li
                            class="flex items-center gap-3 border-t border-border pt-3"
                        >
                            <Landmark class="size-4 text-muted-foreground" />
                            <span class="flex-1">Ya pagado a tu cuenta</span>
                            <span class="legacy-numeric">{{
                                money(stats.paid_out_minor)
                            }}</span>
                        </li>
                    </ul>
                    <div class="rounded-xl bg-muted/50 p-3">
                        <label
                            class="flex items-center justify-between gap-3 text-xs text-muted-foreground"
                        >
                            Simula un precio
                            <span class="relative">
                                <span
                                    class="absolute top-1/2 left-2 -translate-y-1/2"
                                    >$</span
                                >
                                <Input
                                    v-model.number="examplePrice"
                                    type="number"
                                    min="0"
                                    class="h-8 w-24 pl-5 text-right"
                                />
                            </span>
                        </label>
                        <div class="mt-3">
                            <FeeBreakdown
                                :rules="fees"
                                :price-minor="
                                    Math.round(
                                        (Number(examplePrice) || 0) * 100,
                                    )
                                "
                                compact
                            />
                        </div>
                    </div>
                </section>
            </div>

            <!-- Recent sales -->
            <section v-if="recentSales">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-semibold">Ventas recientes</h2>
                    <Link
                        href="/fotografo/ventas"
                        class="text-xs font-medium text-muted-foreground hover:text-foreground"
                        >Ver todas →</Link
                    >
                </div>
                <div
                    v-if="recentSales.length"
                    class="grid grid-cols-1 gap-3 sm:grid-cols-2"
                >
                    <article
                        v-for="sale in recentSales"
                        :key="sale.uuid"
                        class="fl-card flex gap-3 p-3"
                    >
                        <img
                            v-if="sale.thumb_url"
                            :src="sale.thumb_url"
                            alt=""
                            class="size-16 rounded-lg object-cover"
                            loading="lazy"
                        />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">
                                {{ sale.event }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ timeAgo(sale.created_at) }}
                            </p>
                            <p class="mt-1 text-xs">
                                <span class="font-semibold text-emerald-700"
                                    >+{{ money(sale.net_minor) }}</span
                                >
                                <span class="text-muted-foreground">
                                    de {{ money(sale.gross_minor) }}</span
                                >
                            </p>
                        </div>
                        <span
                            class="self-start rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase"
                            :class="
                                sale.payout_status === 'paid'
                                    ? 'bg-emerald-50 text-emerald-700'
                                    : 'bg-amber-50 text-amber-700'
                            "
                            >{{
                                sale.payout_status === 'paid'
                                    ? 'Pagada'
                                    : 'Por pagar'
                            }}</span
                        >
                    </article>
                </div>
                <p
                    v-else
                    class="fl-card p-8 text-center text-sm text-muted-foreground"
                >
                    Aún no tienes ventas. Sube las fotos de tu próximo evento.
                </p>
            </section>

            <!-- Profile + payout -->
            <form
                id="perfil"
                class="fl-card grid scroll-mt-24 gap-5 p-6 md:grid-cols-2"
                @submit.prevent="save"
            >
                <h2 class="text-sm font-semibold md:col-span-2">
                    Perfil y datos de pago
                </h2>
                <div class="grid gap-1.5">
                    <Label for="pf-name">Nombre comercial</Label>
                    <Input id="pf-name" v-model="form.display_name" />
                    <InputError :message="form.errors.display_name" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="pf-city">Ciudad</Label>
                    <Input id="pf-city" v-model="form.city" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="pf-ig">Instagram (URL)</Label>
                    <Input id="pf-ig" v-model="form.instagram_url" type="url" />
                    <InputError :message="form.errors.instagram_url" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="pf-web">Portafolio (URL)</Label>
                    <Input
                        id="pf-web"
                        v-model="form.portfolio_url"
                        type="url"
                    />
                    <InputError :message="form.errors.portfolio_url" />
                </div>
                <div class="grid gap-1.5 md:col-span-2">
                    <Label for="pf-bio">Sobre tu trabajo</Label>
                    <Textarea id="pf-bio" v-model="form.bio" rows="2" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="pf-holder">Titular de la cuenta</Label>
                    <Input id="pf-holder" v-model="form.payout_holder" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="pf-bank">Banco</Label>
                    <Input id="pf-bank" v-model="form.payout_bank" />
                </div>
                <div class="grid gap-1.5 md:col-span-2">
                    <Label for="pf-clabe">CLABE interbancaria</Label>
                    <Input
                        id="pf-clabe"
                        v-model="form.payout_clabe"
                        inputmode="numeric"
                        maxlength="18"
                        :placeholder="
                            profile.payout_clabe_masked
                                ? `Guardada (${profile.payout_clabe_masked}) — escribe para cambiarla`
                                : '18 dígitos'
                        "
                        class="font-mono"
                    />
                    <InputError :message="form.errors.payout_clabe" />
                </div>
                <div class="flex justify-end md:col-span-2">
                    <Button
                        type="submit"
                        class="rounded-full"
                        :disabled="form.processing"
                        >Guardar</Button
                    >
                </div>
            </form>
        </template>
    </div>
</template>
