<script setup lang="ts">
/**
 * Administración → Fotógrafos → detalle: Perfil, Fotografías, Ventas y
 * Pagos. Status actions and "Registrar pago" are always visible in the
 * header. The CLABE is only ever shown masked (last 4 digits).
 */
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    AtSign,
    BadgeCheck,
    Camera,
    Globe,
    Landmark,
    MapPin,
    Pause,
    Phone,
    Play,
    ReceiptText,
    UserRound,
    Wallet,
} from '@lucide/vue';
import { ref } from 'vue';
import DatePicker from '@/components/DatePicker.vue';
import InputError from '@/components/InputError.vue';
import Pagination from '@/components/public/Pagination.vue';
import type { PaginationLink } from '@/components/public/Pagination.vue';
import Money from '@/components/shared/Money.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { confirmAction } from '@/lib/swal';

const props = defineProps<{
    photographer: {
        uuid: string;
        display_name: string;
        name: string | null;
        email: string | null;
        city: string | null;
        phone: string | null;
        bio: string | null;
        instagram_url: string | null;
        portfolio_url: string | null;
        status: 'pending' | 'approved' | 'suspended';
        status_label: string;
        created_at: string | null;
        approved_at: string | null;
        photos_count: number;
        published_count: number;
        review_count: number;
        sales_count: number;
        gross_minor: number;
        platform_fee_minor: number;
        pending_minor: number;
        paid_minor: number;
        default_price_minor: number | null;
        payout_holder: string | null;
        payout_bank: string | null;
        payout_clabe_masked: string | null;
    };
    photos: {
        data: {
            uuid: string;
            thumb_url: string;
            status: string;
            event: string | null;
            price_minor: number;
            currency: string;
            bib_numbers: string[];
        }[];
        links: PaginationLink[];
        total: number;
    };
    sales: {
        uuid: string;
        order_number: string | null;
        thumb_url: string | null;
        gross_minor: number;
        platform_fee_minor: number;
        processor_fee_minor: number;
        net_minor: number;
        currency: string;
        payment_provider: string | null;
        payout_status: string;
        created_at: string | null;
    }[];
    payouts: {
        uuid: string;
        amount_minor: number;
        currency: string;
        sales_count: number;
        reference: string | null;
        notes: string | null;
        paid_at: string;
        recorded_by: string | null;
    }[];
}>();

const tab = ref<'perfil' | 'fotos' | 'ventas' | 'pagos'>('perfil');

const statusChip: Record<string, string> = {
    pending: 'bg-amber-100 text-amber-800',
    approved: 'bg-emerald-100 text-emerald-800',
    suspended: 'bg-red-100 text-red-800',
};
const photoChip: Record<string, { label: string; class: string }> = {
    review: { label: 'Por revisar', class: 'bg-amber-100 text-amber-800' },
    published: { label: 'Publicada', class: 'bg-emerald-100 text-emerald-800' },
    rejected: { label: 'Rechazada', class: 'bg-red-100 text-red-800' },
};
const providerLabel: Record<string, string> = {
    openpay: 'Openpay',
    stripe: 'Stripe',
    manual: 'Manual',
};

async function setStatus(status: 'approved' | 'suspended') {
    if (
        status === 'suspended' &&
        !(await confirmAction({
            title: `¿Suspender a ${props.photographer.display_name}?`,
            text: 'Sus fotos dejan de venderse mientras esté suspendido.',
            confirmButtonText: 'Suspender',
        }))
    ) {
        return;
    }

    router.patch(
        `/admin/photographers/${props.photographer.uuid}/status`,
        { status },
        { preserveScroll: true },
    );
}

const payoutOpen = ref(false);
const payoutForm = useForm({
    reference: '',
    notes: '',
    paid_at: new Date().toISOString().slice(0, 10) as string | null,
});

function registerPayout() {
    payoutForm.post(`/admin/photographers/${props.photographer.uuid}/payouts`, {
        preserveScroll: true,
        onSuccess: () => {
            payoutOpen.value = false;
            payoutForm.reset();
        },
    });
}

const date = (iso: string | null) =>
    iso
        ? new Date(iso).toLocaleDateString('es-MX', {
              day: 'numeric',
              month: 'short',
              year: 'numeric',
          })
        : '—';
</script>

<template>
    <Head :title="`Fotógrafo · ${photographer.display_name}`" />

    <div class="w-full space-y-6 p-4 md:p-8">
        <Link
            href="/admin/photographers"
            class="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="size-4" /> Fotógrafos
        </Link>

        <!-- Header -->
        <div
            class="fl-card flex flex-col gap-5 p-5 sm:p-6 lg:flex-row lg:items-center"
        >
            <div class="flex min-w-0 flex-1 items-center gap-4">
                <span
                    class="flex size-14 shrink-0 items-center justify-center rounded-full bg-fl-cream font-serif text-2xl text-fl-gold-ink"
                    >{{ photographer.display_name.charAt(0) }}</span
                >
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="truncate font-serif text-2xl">
                            {{ photographer.display_name }}
                        </h1>
                        <span
                            class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                            :class="statusChip[photographer.status]"
                            >{{ photographer.status_label }}</span
                        >
                    </div>
                    <p
                        class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted-foreground"
                    >
                        <span class="inline-flex items-center gap-1"
                            ><MapPin class="size-3.5" />
                            {{ photographer.city ?? 'Sin ciudad' }}</span
                        >
                        <span
                            >Registro {{ date(photographer.created_at) }}</span
                        >
                    </p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button
                    v-if="photographer.status === 'pending'"
                    class="rounded-full"
                    @click="setStatus('approved')"
                >
                    <BadgeCheck class="size-4" /> Aprobar
                </Button>
                <Button
                    v-if="photographer.status === 'approved'"
                    variant="outline"
                    class="rounded-full text-red-700"
                    @click="setStatus('suspended')"
                >
                    <Pause class="size-4" /> Suspender
                </Button>
                <Button
                    v-if="photographer.status === 'suspended'"
                    variant="outline"
                    class="rounded-full"
                    @click="setStatus('approved')"
                >
                    <Play class="size-4" /> Reactivar
                </Button>
                <Button
                    class="rounded-full bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                    :disabled="photographer.pending_minor <= 0"
                    @click="payoutOpen = true"
                >
                    <Wallet class="size-4" /> Registrar pago
                </Button>
            </div>
        </div>

        <!-- Numbers -->
        <dl class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-5">
            <div class="fl-card p-4">
                <dt class="text-xs text-muted-foreground">Fotos publicadas</dt>
                <dd class="legacy-numeric mt-1 text-2xl font-semibold">
                    {{ photographer.published_count }}
                    <span class="text-sm text-muted-foreground"
                        >/ {{ photographer.photos_count }}</span
                    >
                </dd>
            </div>
            <div class="fl-card p-4">
                <dt class="text-xs text-muted-foreground">Fotos vendidas</dt>
                <dd class="legacy-numeric mt-1 text-2xl font-semibold">
                    {{ photographer.sales_count }}
                </dd>
            </div>
            <div class="fl-card p-4">
                <dt class="text-xs text-muted-foreground">Ventas brutas</dt>
                <dd class="legacy-numeric mt-1 text-2xl font-semibold">
                    <Money :minor="photographer.gross_minor" />
                </dd>
            </div>
            <div class="fl-card p-4">
                <dt class="text-xs text-muted-foreground">Ya pagado</dt>
                <dd class="legacy-numeric mt-1 text-2xl font-semibold">
                    <Money :minor="photographer.paid_minor" />
                </dd>
            </div>
            <div class="fl-card col-span-2 bg-fl-cream/50 p-4 lg:col-span-1">
                <dt class="text-xs text-fl-gold-ink">Saldo pendiente</dt>
                <dd class="legacy-numeric mt-1 text-2xl font-semibold">
                    <Money :minor="photographer.pending_minor" />
                </dd>
            </div>
        </dl>

        <!-- Tabs -->
        <div
            class="flex gap-1 overflow-x-auto border-b border-border"
            role="tablist"
        >
            <button
                v-for="t in [
                    { key: 'perfil', label: 'Perfil', icon: UserRound },
                    {
                        key: 'fotos',
                        label: `Fotografías (${photos.total})`,
                        icon: Camera,
                    },
                    {
                        key: 'ventas',
                        label: `Ventas (${photographer.sales_count})`,
                        icon: ReceiptText,
                    },
                    {
                        key: 'pagos',
                        label: `Pagos (${payouts.length})`,
                        icon: Wallet,
                    },
                ] as const"
                :key="t.key"
                type="button"
                role="tab"
                :aria-selected="tab === t.key"
                class="-mb-px inline-flex shrink-0 items-center gap-2 border-b-2 px-4 py-3 text-sm font-semibold transition-colors"
                :class="
                    tab === t.key
                        ? 'border-fl-gold text-foreground'
                        : 'border-transparent text-muted-foreground hover:text-foreground'
                "
                @click="tab = t.key"
            >
                <component :is="t.icon" class="size-4" /> {{ t.label }}
            </button>
        </div>

        <!-- Perfil -->
        <div
            v-if="tab === 'perfil'"
            class="grid grid-cols-1 gap-6 lg:grid-cols-2"
        >
            <section class="fl-card space-y-3 p-5 text-sm">
                <h2 class="font-semibold">Contacto</h2>
                <p class="flex items-center gap-2">
                    <UserRound class="size-4 text-muted-foreground" />
                    {{ photographer.name ?? '—' }}
                </p>
                <p class="flex items-center gap-2">
                    <AtSign class="size-4 text-muted-foreground" />
                    {{ photographer.email ?? '—' }}
                </p>
                <p class="flex items-center gap-2">
                    <Phone class="size-4 text-muted-foreground" />
                    {{ photographer.phone ?? '—' }}
                </p>
                <p
                    v-if="photographer.instagram_url"
                    class="flex items-center gap-2"
                >
                    <Globe class="size-4 text-muted-foreground" />
                    <a
                        :href="photographer.instagram_url"
                        target="_blank"
                        rel="noopener"
                        class="truncate underline underline-offset-2"
                        >{{ photographer.instagram_url }}</a
                    >
                </p>
                <p
                    v-if="photographer.portfolio_url"
                    class="flex items-center gap-2"
                >
                    <Globe class="size-4 text-muted-foreground" />
                    <a
                        :href="photographer.portfolio_url"
                        target="_blank"
                        rel="noopener"
                        class="truncate underline underline-offset-2"
                        >{{ photographer.portfolio_url }}</a
                    >
                </p>
                <p
                    v-if="photographer.bio"
                    class="border-t border-border pt-3 leading-relaxed whitespace-pre-line text-muted-foreground"
                >
                    {{ photographer.bio }}
                </p>
            </section>
            <section class="fl-card space-y-3 p-5 text-sm">
                <h2 class="flex items-center gap-2 font-semibold">
                    <Landmark class="size-4" /> Datos de pago
                </h2>
                <template v-if="photographer.payout_clabe_masked">
                    <p>
                        <span class="text-muted-foreground">Titular:</span>
                        {{ photographer.payout_holder ?? '—' }}
                    </p>
                    <p>
                        <span class="text-muted-foreground">Banco:</span>
                        {{ photographer.payout_bank ?? '—' }}
                    </p>
                    <p>
                        <span class="text-muted-foreground">CLABE:</span>
                        <span class="legacy-numeric">{{
                            photographer.payout_clabe_masked
                        }}</span>
                    </p>
                    <p class="text-xs text-muted-foreground">
                        Por seguridad la CLABE nunca se muestra completa.
                    </p>
                </template>
                <p v-else class="text-amber-700">
                    Aún no captura sus datos bancarios.
                </p>
                <p class="border-t border-border pt-3">
                    <span class="text-muted-foreground"
                        >Precio por defecto:</span
                    >
                    <Money
                        v-if="photographer.default_price_minor"
                        :minor="photographer.default_price_minor"
                    />
                    <span v-else>—</span>
                </p>
                <p>
                    <span class="text-muted-foreground">Aprobado:</span>
                    {{ date(photographer.approved_at) }}
                </p>
            </section>
        </div>

        <!-- Fotos -->
        <div v-else-if="tab === 'fotos'" class="space-y-4">
            <div
                v-if="photos.data.length"
                class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-6"
            >
                <figure
                    v-for="photo in photos.data"
                    :key="photo.uuid"
                    class="overflow-hidden rounded-xl border border-border bg-card"
                >
                    <img
                        :src="photo.thumb_url"
                        alt=""
                        loading="lazy"
                        decoding="async"
                        class="aspect-[4/3] w-full object-cover"
                    />
                    <figcaption class="space-y-1 p-2 text-xs">
                        <span
                            class="inline-block rounded-full px-2 py-0.5 text-[10px] font-semibold"
                            :class="photoChip[photo.status]?.class"
                            >{{ photoChip[photo.status]?.label }}</span
                        >
                        <p class="truncate">{{ photo.event }}</p>
                        <p class="flex justify-between text-muted-foreground">
                            <span>{{
                                photo.bib_numbers.length
                                    ? `#${photo.bib_numbers.join(' #')}`
                                    : 'Sin dorsal'
                            }}</span>
                            <Money
                                :minor="photo.price_minor"
                                :currency="photo.currency"
                            />
                        </p>
                    </figcaption>
                </figure>
            </div>
            <p
                v-else
                class="fl-card p-8 text-center text-sm text-muted-foreground"
            >
                Aún no sube fotografías.
            </p>
            <Pagination :links="photos.links" />
        </div>

        <!-- Ventas -->
        <div v-else-if="tab === 'ventas'" class="fl-card overflow-x-auto">
            <table class="w-full min-w-[720px] text-sm">
                <thead
                    class="border-b border-border text-left text-xs text-muted-foreground"
                >
                    <tr>
                        <th class="px-4 py-3 font-medium">Foto</th>
                        <th class="px-4 py-3 font-medium">Pedido</th>
                        <th class="px-4 py-3 text-right font-medium">Bruto</th>
                        <th class="px-4 py-3 text-right font-medium">
                            Comisión
                        </th>
                        <th class="px-4 py-3 text-right font-medium">
                            Procesamiento (est.)
                        </th>
                        <th class="px-4 py-3 text-right font-medium">Neto</th>
                        <th class="px-4 py-3 font-medium">Pago</th>
                        <th class="px-4 py-3 font-medium">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr v-for="sale in sales" :key="sale.uuid">
                        <td class="px-4 py-2">
                            <img
                                v-if="sale.thumb_url"
                                :src="sale.thumb_url"
                                alt=""
                                loading="lazy"
                                class="h-10 w-14 rounded object-cover"
                            />
                        </td>
                        <td class="legacy-numeric px-4 py-2">
                            {{ sale.order_number ?? '—' }}
                            <span class="block text-xs text-muted-foreground">{{
                                date(sale.created_at)
                            }}</span>
                        </td>
                        <td class="legacy-numeric px-4 py-2 text-right">
                            <Money
                                :minor="sale.gross_minor"
                                :currency="sale.currency"
                            />
                        </td>
                        <td
                            class="legacy-numeric px-4 py-2 text-right text-muted-foreground"
                        >
                            −<Money
                                :minor="sale.platform_fee_minor"
                                :currency="sale.currency"
                            />
                        </td>
                        <td
                            class="legacy-numeric px-4 py-2 text-right text-muted-foreground"
                        >
                            −<Money
                                :minor="sale.processor_fee_minor"
                                :currency="sale.currency"
                            />
                        </td>
                        <td
                            class="legacy-numeric px-4 py-2 text-right font-semibold"
                        >
                            <Money
                                :minor="sale.net_minor"
                                :currency="sale.currency"
                            />
                        </td>
                        <td class="px-4 py-2 text-muted-foreground">
                            {{
                                providerLabel[sale.payment_provider ?? ''] ??
                                'Por definir'
                            }}
                        </td>
                        <td class="px-4 py-2">
                            <span
                                class="rounded-full px-2 py-0.5 text-xs font-semibold"
                                :class="
                                    sale.payout_status === 'paid'
                                        ? 'bg-emerald-100 text-emerald-800'
                                        : 'bg-amber-100 text-amber-800'
                                "
                                >{{
                                    sale.payout_status === 'paid'
                                        ? 'Pagada'
                                        : 'Pendiente'
                                }}</span
                            >
                        </td>
                    </tr>
                    <tr v-if="!sales.length">
                        <td
                            colspan="8"
                            class="px-4 py-10 text-center text-muted-foreground"
                        >
                            Sin ventas todavía.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagos -->
        <div v-else class="space-y-3">
            <article
                v-for="payout in payouts"
                :key="payout.uuid"
                class="fl-card flex flex-wrap items-center gap-4 p-4"
            >
                <span
                    class="flex size-10 items-center justify-center rounded-xl bg-emerald-50 text-emerald-700"
                    ><Wallet class="size-4"
                /></span>
                <div class="min-w-0 flex-1">
                    <p class="legacy-numeric text-lg font-semibold">
                        <Money
                            :minor="payout.amount_minor"
                            :currency="payout.currency"
                        />
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ date(payout.paid_at) }} ·
                        {{ payout.sales_count }} ventas
                        <template v-if="payout.reference">
                            · Ref. {{ payout.reference }}</template
                        >
                        <template v-if="payout.recorded_by">
                            · registró {{ payout.recorded_by }}</template
                        >
                    </p>
                    <p v-if="payout.notes" class="mt-1 text-sm">
                        {{ payout.notes }}
                    </p>
                </div>
            </article>
            <p
                v-if="!payouts.length"
                class="fl-card p-8 text-center text-sm text-muted-foreground"
            >
                No se han registrado pagos.
            </p>
        </div>
    </div>

    <Dialog v-model:open="payoutOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Registrar pago</DialogTitle>
                <DialogDescription>
                    Registra la transferencia ya enviada a
                    {{ photographer.display_name }}. Liquida
                    <Money :minor="photographer.pending_minor" /> en ventas
                    pendientes.
                </DialogDescription>
            </DialogHeader>
            <form class="space-y-4" @submit.prevent="registerPayout">
                <div class="grid gap-1.5">
                    <Label>Fecha de la transferencia</Label>
                    <DatePicker v-model="payoutForm.paid_at" />
                    <InputError :message="payoutForm.errors.paid_at" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="po-ref">Referencia / folio</Label>
                    <Input
                        id="po-ref"
                        v-model="payoutForm.reference"
                        maxlength="120"
                        placeholder="Ej. SPEI 0123456"
                    />
                    <InputError :message="payoutForm.errors.reference" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="po-notes">Notas</Label>
                    <Textarea
                        id="po-notes"
                        v-model="payoutForm.notes"
                        rows="2"
                        maxlength="500"
                    />
                </div>
                <DialogFooter>
                    <Button
                        type="submit"
                        class="rounded-full"
                        :disabled="payoutForm.processing"
                    >
                        Sí, ya transferí
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
