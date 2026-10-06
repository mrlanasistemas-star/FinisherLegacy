<script setup lang="ts">
/**
 * Administración → Fotógrafos: applicants first, then active
 * photographers, each with their numbers and pending payout.
 */
import { Head, router } from '@inertiajs/vue3';
import {
    AtSign,
    BadgeCheck,
    Building2,
    Camera,
    Clock,
    Globe,
    Hourglass,
    Link2,
    Landmark,
    MapPin,
    Pause,
    Phone,
    Wallet,
} from '@lucide/vue';
import SecondaryNav from '@/components/admin/SecondaryNav.vue';
import { Button } from '@/components/ui/button';
import { CONTENT_AREA_NAV } from '@/config/areaNav';
import { confirmAction } from '@/lib/swal';

type Photographer = {
    id: number;
    uuid: string;
    display_name: string;
    name: string | null;
    email: string | null;
    city: string | null;
    phone: string | null;
    instagram_url: string | null;
    portfolio_url: string | null;
    bio: string | null;
    status: 'pending' | 'approved' | 'suspended';
    status_label: string;
    published_count: number;
    review_count: number;
    sales_count: number;
    gross_minor: number;
    platform_fee_minor: number;
    pending_minor: number;
    payout_holder: string | null;
    payout_bank: string | null;
    payout_clabe_masked: string | null;
};

defineProps<{
    photographers: Photographer[];
    totals: {
        pending_applicants: number;
        gross_minor: number;
        platform_fee_minor: number;
        pending_payout_minor: number;
    };
    commissionPercent: number;
}>();

const money = (minor: number) =>
    new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
        maximumFractionDigits: 0,
    }).format(minor / 100);

const statusChip: Record<string, string> = {
    pending: 'bg-amber-100 text-amber-800',
    approved: 'bg-emerald-100 text-emerald-800',
    suspended: 'bg-red-100 text-red-800',
};

function setStatus(p: Photographer, status: Photographer['status']) {
    router.patch(
        `/admin/photographers/${p.uuid}/status`,
        { status },
        { preserveScroll: true },
    );
}

async function payout(p: Photographer) {
    if (
        await confirmAction({
            title: `¿Registrar pago a ${p.display_name}?`,
            text: `Marca ${money(p.pending_minor)} pendientes como transferidos. Hazlo después de enviar la transferencia.`,
            confirmButtonText: 'Sí, ya transferí',
        })
    ) {
        router.post(
            `/admin/photographers/${p.uuid}/payouts`,
            {},
            { preserveScroll: true },
        );
    }
}
</script>

<template>
    <Head title="Fotógrafos" />

    <div class="mx-auto w-full max-w-[1400px] p-4 md:p-8">
        <SecondaryNav :items="CONTENT_AREA_NAV" />

        <div class="mb-6 flex items-center gap-3">
            <span
                class="flex size-11 items-center justify-center rounded-xl bg-fl-cream text-fl-gold-ink"
                ><Camera class="size-5"
            /></span>
            <div>
                <h1 class="text-xl font-semibold">Fotógrafos</h1>
                <p class="text-sm text-muted-foreground">
                    Comisión de Finisher Legacy: {{ commissionPercent }}% por
                    foto vendida.
                </p>
            </div>
        </div>

        <div class="mb-8 grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div class="fl-card p-4">
                <Clock class="size-4 text-amber-700" />
                <p class="legacy-numeric mt-2 text-2xl font-semibold">
                    {{ totals.pending_applicants }}
                </p>
                <p class="text-xs text-muted-foreground">
                    Solicitudes por revisar
                </p>
            </div>
            <div class="fl-card p-4">
                <Wallet class="size-4 text-sky-700" />
                <p class="legacy-numeric mt-2 text-2xl font-semibold">
                    {{ money(totals.gross_minor) }}
                </p>
                <p class="text-xs text-muted-foreground">Ventas totales</p>
            </div>
            <div class="fl-card p-4">
                <Building2 class="size-4 text-fl-gold-ink" />
                <p class="legacy-numeric mt-2 text-2xl font-semibold">
                    {{ money(totals.platform_fee_minor) }}
                </p>
                <p class="text-xs text-muted-foreground">Comisión Finisher</p>
            </div>
            <div class="fl-card p-4">
                <Hourglass class="size-4 text-red-700" />
                <p class="legacy-numeric mt-2 text-2xl font-semibold">
                    {{ money(totals.pending_payout_minor) }}
                </p>
                <p class="text-xs text-muted-foreground">
                    Por pagar a fotógrafos
                </p>
            </div>
        </div>

        <div
            v-if="photographers.length"
            class="grid gap-5 md:grid-cols-2 xl:grid-cols-3"
        >
            <article
                v-for="p in photographers"
                :key="p.id"
                class="fl-card flex flex-col p-5"
            >
                <div class="flex items-start gap-3">
                    <span
                        class="flex size-11 shrink-0 items-center justify-center rounded-full bg-foreground font-semibold text-background"
                        >{{ p.display_name.slice(0, 1) }}</span
                    >
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold">
                            {{ p.display_name }}
                        </p>
                        <p class="truncate text-xs text-muted-foreground">
                            {{ p.name }}
                        </p>
                    </div>
                    <span
                        class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold"
                        :class="statusChip[p.status]"
                        >{{ p.status_label }}</span
                    >
                </div>
                <ul class="mt-4 space-y-1.5 text-sm text-muted-foreground">
                    <li v-if="p.email" class="flex items-center gap-2">
                        <AtSign class="size-3.5" /> {{ p.email }}
                    </li>
                    <li v-if="p.city" class="flex items-center gap-2">
                        <MapPin class="size-3.5" /> {{ p.city }}
                    </li>
                    <li v-if="p.phone" class="flex items-center gap-2">
                        <Phone class="size-3.5" /> {{ p.phone }}
                    </li>
                    <li v-if="p.instagram_url" class="flex items-center gap-2">
                        <Link2 class="size-3.5" />
                        <a
                            :href="p.instagram_url"
                            target="_blank"
                            rel="noopener"
                            class="truncate hover:text-foreground"
                            >{{ p.instagram_url }}</a
                        >
                    </li>
                    <li v-if="p.portfolio_url" class="flex items-center gap-2">
                        <Globe class="size-3.5" />
                        <a
                            :href="p.portfolio_url"
                            target="_blank"
                            rel="noopener"
                            class="truncate hover:text-foreground"
                            >{{ p.portfolio_url }}</a
                        >
                    </li>
                    <li
                        v-if="p.payout_clabe_masked"
                        class="flex items-center gap-2"
                    >
                        <Landmark class="size-3.5" /> {{ p.payout_bank }} ·
                        {{ p.payout_clabe_masked }}
                    </li>
                </ul>
                <p v-if="p.bio" class="mt-3 line-clamp-3 text-sm">
                    {{ p.bio }}
                </p>
                <dl
                    class="mt-4 grid grid-cols-4 gap-2 border-t border-border pt-4 text-center"
                >
                    <div>
                        <dt class="text-[10px] text-muted-foreground uppercase">
                            Publicadas
                        </dt>
                        <dd class="legacy-numeric font-semibold">
                            {{ p.published_count }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-[10px] text-muted-foreground uppercase">
                            Revisión
                        </dt>
                        <dd class="legacy-numeric font-semibold">
                            {{ p.review_count }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-[10px] text-muted-foreground uppercase">
                            Ventas
                        </dt>
                        <dd class="legacy-numeric font-semibold">
                            {{ p.sales_count }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-[10px] text-muted-foreground uppercase">
                            Por pagar
                        </dt>
                        <dd class="legacy-numeric font-semibold text-red-700">
                            {{ money(p.pending_minor) }}
                        </dd>
                    </div>
                </dl>
                <div class="mt-auto flex flex-wrap gap-2 pt-5">
                    <Button
                        v-if="p.status !== 'approved'"
                        size="sm"
                        class="rounded-full"
                        @click="setStatus(p, 'approved')"
                        ><BadgeCheck class="size-3.5" /> Aprobar</Button
                    >
                    <Button
                        v-if="p.status === 'approved'"
                        size="sm"
                        variant="outline"
                        class="rounded-full"
                        @click="setStatus(p, 'suspended')"
                        ><Pause class="size-3.5" /> Suspender</Button
                    >
                    <Button
                        v-if="p.pending_minor > 0"
                        size="sm"
                        variant="outline"
                        class="rounded-full"
                        @click="payout(p)"
                        ><Landmark class="size-3.5" /> Registrar pago</Button
                    >
                </div>
            </article>
        </div>
        <div
            v-else
            class="fl-card flex flex-col items-center gap-3 p-12 text-center"
        >
            <Camera class="size-8 text-fl-gold-ink" />
            <p class="font-semibold">Aún no hay fotógrafos</p>
            <p class="text-sm text-muted-foreground">
                Comparte la página /fotografos para recibir solicitudes.
            </p>
        </div>
    </div>
</template>
