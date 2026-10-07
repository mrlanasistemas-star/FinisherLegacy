<script setup lang="ts">
/**
 * Administración → Fotógrafos: applicants first, then active and
 * suspended photographers. Table on desktop, cards on mobile — the main
 * actions (Aprobar / Suspender / Reactivar / Ver) are always visible,
 * never hidden in a menu. Payouts are registered from the detail page.
 */
import { Head, Link, router } from '@inertiajs/vue3';
import {
    BadgeCheck,
    Camera,
    ChevronRight,
    Hourglass,
    Landmark,
    MapPin,
    Pause,
    Play,
    Search,
    Wallet,
} from '@lucide/vue';
import { ref } from 'vue';
import SecondaryNav from '@/components/admin/SecondaryNav.vue';
import Pagination from '@/components/public/Pagination.vue';
import type { PaginationLink } from '@/components/public/Pagination.vue';
import Money from '@/components/shared/Money.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { CONTENT_AREA_NAV } from '@/config/areaNav';
import { confirmAction } from '@/lib/swal';

type Photographer = {
    id: number;
    uuid: string;
    display_name: string;
    name: string | null;
    email: string | null;
    city: string | null;
    status: 'pending' | 'approved' | 'suspended';
    status_label: string;
    created_at: string | null;
    photos_count: number;
    published_count: number;
    review_count: number;
    sales_count: number;
    gross_minor: number;
    pending_minor: number;
    has_payout_data: boolean;
};

const props = defineProps<{
    photographers: { data: Photographer[]; links: PaginationLink[] };
    filters: { status: string | null; q: string | null };
    counts: Record<string, number>;
    totals: {
        pending_applicants: number;
        gross_minor: number;
        platform_fee_minor: number;
        pending_payout_minor: number;
    };
    commissionPercent: number;
}>();

const q = ref(props.filters.q ?? '');

const tabs = [
    { key: null, label: 'Todos' },
    { key: 'pending', label: 'Por aprobar' },
    { key: 'approved', label: 'Aprobados' },
    { key: 'suspended', label: 'Suspendidos' },
] as const;

const statusChip: Record<string, string> = {
    pending: 'bg-amber-100 text-amber-800',
    approved: 'bg-emerald-100 text-emerald-800',
    suspended: 'bg-red-100 text-red-800',
};

function visit(params: Record<string, string | null>) {
    router.get(
        '/admin/photographers',
        Object.fromEntries(
            Object.entries({
                status: props.filters.status,
                q: q.value || null,
                ...params,
            }).filter(([, v]) => v),
        ),
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

async function setStatus(p: Photographer, status: Photographer['status']) {
    if (
        status === 'suspended' &&
        !(await confirmAction({
            title: `¿Suspender a ${p.display_name}?`,
            text: 'Sus fotos dejan de venderse mientras esté suspendido. Sus ventas y saldo se conservan.',
            confirmButtonText: 'Suspender',
        }))
    ) {
        return;
    }

    router.patch(
        `/admin/photographers/${p.uuid}/status`,
        { status },
        { preserveScroll: true },
    );
}

const shortDate = (iso: string | null) =>
    iso
        ? new Date(iso).toLocaleDateString('es-MX', {
              day: 'numeric',
              month: 'short',
              year: 'numeric',
          })
        : '—';
</script>

<template>
    <Head title="Fotógrafos" />

    <div class="w-full space-y-6 p-4 md:p-8">
        <SecondaryNav :items="CONTENT_AREA_NAV" />

        <div class="flex flex-wrap items-center gap-3">
            <span
                class="flex size-11 items-center justify-center rounded-xl bg-fl-cream text-fl-gold-ink"
                ><Camera class="size-5"
            /></span>
            <div class="min-w-0 flex-1">
                <h1 class="text-xl font-semibold">Fotógrafos</h1>
                <p class="text-sm text-muted-foreground">
                    Comisión de Finisher Legacy: {{ commissionPercent }}% por
                    foto vendida.
                </p>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
            <div class="fl-card p-4">
                <p
                    class="flex items-center gap-1.5 text-xs text-muted-foreground"
                >
                    <Hourglass class="size-3.5" /> Por aprobar
                </p>
                <p class="legacy-numeric mt-1 text-2xl font-semibold">
                    {{ totals.pending_applicants }}
                </p>
            </div>
            <div class="fl-card p-4">
                <p class="text-xs text-muted-foreground">Ventas brutas</p>
                <p class="legacy-numeric mt-1 text-2xl font-semibold">
                    <Money :minor="totals.gross_minor" />
                </p>
            </div>
            <div class="fl-card p-4">
                <p class="text-xs text-muted-foreground">Comisión Finisher</p>
                <p class="legacy-numeric mt-1 text-2xl font-semibold">
                    <Money :minor="totals.platform_fee_minor" />
                </p>
            </div>
            <div class="fl-card p-4">
                <p
                    class="flex items-center gap-1.5 text-xs text-muted-foreground"
                >
                    <Wallet class="size-3.5" /> Saldo pendiente
                </p>
                <p class="legacy-numeric mt-1 text-2xl font-semibold">
                    <Money :minor="totals.pending_payout_minor" />
                </p>
            </div>
        </div>

        <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
            <div
                class="flex gap-1 overflow-x-auto rounded-full border border-border bg-card p-1"
            >
                <button
                    v-for="tab in tabs"
                    :key="tab.label"
                    type="button"
                    class="shrink-0 rounded-full px-4 py-1.5 text-sm font-semibold transition-colors"
                    :class="
                        filters.status === tab.key
                            ? 'bg-foreground text-background'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    @click="visit({ status: tab.key, page: null })"
                >
                    {{ tab.label }}
                    <span
                        v-if="tab.key && counts[tab.key]"
                        class="legacy-numeric ml-1 text-xs opacity-70"
                        >{{ counts[tab.key] }}</span
                    >
                </button>
            </div>
            <form
                class="relative lg:ml-auto lg:w-80"
                @submit.prevent="visit({ page: null })"
            >
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="q"
                    class="pl-9"
                    placeholder="Nombre, ciudad o correo"
                    aria-label="Buscar fotógrafo"
                />
            </form>
        </div>

        <!-- Desktop table -->
        <div class="fl-card hidden overflow-x-auto lg:block">
            <table class="w-full text-sm">
                <thead
                    class="border-b border-border text-left text-xs text-muted-foreground"
                >
                    <tr>
                        <th class="px-4 py-3 font-medium">Fotógrafo</th>
                        <th class="px-4 py-3 font-medium">Ciudad</th>
                        <th class="px-4 py-3 font-medium">Estado</th>
                        <th class="px-4 py-3 text-right font-medium">Fotos</th>
                        <th class="px-4 py-3 text-right font-medium">Ventas</th>
                        <th class="px-4 py-3 text-right font-medium">
                            Saldo pendiente
                        </th>
                        <th class="px-4 py-3 font-medium">Registro</th>
                        <th class="px-4 py-3 text-right font-medium">
                            Acciones
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr
                        v-for="p in photographers.data"
                        :key="p.id"
                        class="hover:bg-muted/40"
                    >
                        <td class="px-4 py-3">
                            <Link
                                :href="`/admin/photographers/${p.uuid}`"
                                class="flex items-center gap-3"
                            >
                                <span
                                    class="flex size-9 shrink-0 items-center justify-center rounded-full bg-fl-cream font-serif text-fl-gold-ink"
                                    >{{ p.display_name.charAt(0) }}</span
                                >
                                <span class="min-w-0">
                                    <span
                                        class="block truncate font-medium hover:underline"
                                        >{{ p.display_name }}</span
                                    >
                                    <span
                                        class="block truncate text-xs text-muted-foreground"
                                        >{{ p.email }}</span
                                    >
                                </span>
                            </Link>
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ p.city ?? '—' }}
                        </td>
                        <td class="px-4 py-3">
                            <span
                                class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                :class="statusChip[p.status]"
                                >{{ p.status_label }}</span
                            >
                        </td>
                        <td class="legacy-numeric px-4 py-3 text-right">
                            {{ p.published_count }}
                            <span class="text-xs text-muted-foreground"
                                >/ {{ p.photos_count }}</span
                            >
                        </td>
                        <td class="legacy-numeric px-4 py-3 text-right">
                            {{ p.sales_count }}
                        </td>
                        <td
                            class="legacy-numeric px-4 py-3 text-right font-semibold"
                        >
                            <Money :minor="p.pending_minor" />
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ shortDate(p.created_at) }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-1.5">
                                <Button
                                    v-if="p.status === 'pending'"
                                    size="sm"
                                    class="rounded-full"
                                    @click="setStatus(p, 'approved')"
                                >
                                    <BadgeCheck class="size-3.5" /> Aprobar
                                </Button>
                                <Button
                                    v-if="p.status === 'approved'"
                                    size="sm"
                                    variant="outline"
                                    class="rounded-full text-red-700"
                                    @click="setStatus(p, 'suspended')"
                                >
                                    <Pause class="size-3.5" /> Suspender
                                </Button>
                                <Button
                                    v-if="p.status === 'suspended'"
                                    size="sm"
                                    variant="outline"
                                    class="rounded-full"
                                    @click="setStatus(p, 'approved')"
                                >
                                    <Play class="size-3.5" /> Reactivar
                                </Button>
                                <Button
                                    as-child
                                    size="sm"
                                    variant="ghost"
                                    class="rounded-full"
                                >
                                    <Link
                                        :href="`/admin/photographers/${p.uuid}`"
                                        >Ver <ChevronRight class="size-3.5"
                                    /></Link>
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Mobile cards -->
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:hidden">
            <article
                v-for="p in photographers.data"
                :key="p.id"
                class="fl-card p-4"
            >
                <div class="flex items-start gap-3">
                    <span
                        class="flex size-10 shrink-0 items-center justify-center rounded-full bg-fl-cream font-serif text-fl-gold-ink"
                        >{{ p.display_name.charAt(0) }}</span
                    >
                    <div class="min-w-0 flex-1">
                        <Link
                            :href="`/admin/photographers/${p.uuid}`"
                            class="block truncate font-semibold"
                            >{{ p.display_name }}</Link
                        >
                        <p
                            class="flex items-center gap-1 text-xs text-muted-foreground"
                        >
                            <MapPin class="size-3" />
                            {{ p.city ?? 'Sin ciudad' }} ·
                            {{ shortDate(p.created_at) }}
                        </p>
                    </div>
                    <span
                        class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
                        :class="statusChip[p.status]"
                        >{{ p.status_label }}</span
                    >
                </div>
                <dl class="mt-4 grid grid-cols-3 gap-2 text-center">
                    <div class="rounded-lg bg-muted/60 p-2">
                        <dt class="text-[10px] text-muted-foreground">Fotos</dt>
                        <dd class="legacy-numeric font-semibold">
                            {{ p.published_count }}
                        </dd>
                    </div>
                    <div class="rounded-lg bg-muted/60 p-2">
                        <dt class="text-[10px] text-muted-foreground">
                            Ventas
                        </dt>
                        <dd class="legacy-numeric font-semibold">
                            {{ p.sales_count }}
                        </dd>
                    </div>
                    <div class="rounded-lg bg-muted/60 p-2">
                        <dt class="text-[10px] text-muted-foreground">Saldo</dt>
                        <dd class="legacy-numeric text-sm font-semibold">
                            <Money :minor="p.pending_minor" />
                        </dd>
                    </div>
                </dl>
                <p
                    v-if="!p.has_payout_data"
                    class="mt-3 flex items-center gap-1.5 text-xs text-amber-700"
                >
                    <Landmark class="size-3.5" /> Sin datos bancarios
                </p>
                <div class="mt-4 flex flex-wrap gap-2">
                    <Button
                        v-if="p.status === 'pending'"
                        size="sm"
                        class="flex-1 rounded-full"
                        @click="setStatus(p, 'approved')"
                    >
                        <BadgeCheck class="size-3.5" /> Aprobar
                    </Button>
                    <Button
                        v-if="p.status === 'approved'"
                        size="sm"
                        variant="outline"
                        class="flex-1 rounded-full text-red-700"
                        @click="setStatus(p, 'suspended')"
                    >
                        <Pause class="size-3.5" /> Suspender
                    </Button>
                    <Button
                        v-if="p.status === 'suspended'"
                        size="sm"
                        variant="outline"
                        class="flex-1 rounded-full"
                        @click="setStatus(p, 'approved')"
                    >
                        <Play class="size-3.5" /> Reactivar
                    </Button>
                    <Button
                        as-child
                        size="sm"
                        variant="outline"
                        class="flex-1 rounded-full"
                    >
                        <Link :href="`/admin/photographers/${p.uuid}`"
                            >Ver detalle</Link
                        >
                    </Button>
                </div>
            </article>
        </div>

        <p
            v-if="!photographers.data.length"
            class="fl-card px-6 py-12 text-center text-sm text-muted-foreground"
        >
            No hay fotógrafos con estos filtros.
        </p>

        <Pagination :links="photographers.links" />
    </div>
</template>
