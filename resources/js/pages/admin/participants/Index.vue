<script setup lang="ts">
/**
 * Participants V2 (product UX consolidation brief §19-§31) — EVENTO is
 * required context, every filter runs server-side (see
 * App\Queries\Operations\GetEventParticipantsList), and the KPI row above
 * the table is computed from paid Orders only, never the cart.
 */
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    Boxes,
    Camera,
    Download,
    ShoppingBag,
    Trophy,
    Upload,
    Users,
} from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import type { AcceptableValue } from 'reka-ui';
import { computed, ref, watch } from 'vue';
import EventEditionSelector from '@/components/admin/EventEditionSelector.vue';
import Pagination from '@/components/public/Pagination.vue';
import Money from '@/components/shared/Money.vue';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type ParticipantRow = {
    id: number;
    name: string;
    bib_number: string | null;
    race: string | null;
    result_status: string | null;
    official_time: string | null;
    pace: string | null;
    legacy_plate_status: string | null;
    payment_status: string | null;
    order_uuid: string | null;
    products: string[];
    media_count: number;
};

const props = defineProps<{
    events: { id: number; name: string }[];
    selectedEventEditionId: number | null;
    races: { id: number; name: string }[];
    participants: {
        data: ParticipantRow[];
        links: { url: string | null; label: string; active: boolean }[];
        total: number;
        current_page: number;
        last_page: number;
    } | null;
    metrics: Record<string, number> | null;
    filters: {
        event_edition_id: number | null;
        q: string | null;
        event_race_id: number | null;
        result: string | null;
        legacy_plate: string | null;
        product: string | null;
    };
}>();

const q = ref(props.filters.q ?? '');

const resultLabels: Record<string, string> = {
    finished: 'Finalizado',
    pending: 'Pendiente',
};

const legacyPlateLabels: Record<string, string> = {
    none: 'Sin compra',
    pending_payment: 'Pago pendiente',
    paid: 'Pagada',
    production: 'En producción',
    delivered: 'Entregada',
};

const legacyPlateStatusBadge: Record<string, string> = {
    pending_payment: 'border-amber-500/30 text-amber-700',
    paid: 'border-sky-500/30 text-sky-700',
    linked: 'border-sky-500/30 text-sky-700',
    queued: 'border-fl-gold/30 text-fl-gold-ink',
    produced: 'border-fl-gold/30 text-fl-gold-ink',
    delivered: 'border-emerald-500/30 text-emerald-700',
    cancelled: 'border-red-500/30 text-red-700',
};

const products = [
    { slug: 'trisuit', label: 'Trisuit' },
    { slug: 'fast-t1-socks', label: 'FAST T1 SOCKS' },
    { slug: 'chill-band', label: 'CHILL BAND' },
    { slug: 'racepack', label: 'RACEPACK' },
];

/** Reka Select emits `AcceptableValue` (string | number | bigint | record);
 *  every filter here is only ever a string or number, so anything else
 *  (there shouldn't be any) is dropped rather than passed through. */
function toFilterValue(v: AcceptableValue): string | number | undefined {
    return typeof v === 'string' || typeof v === 'number' ? v : undefined;
}

/** Every filter navigates without a `page` param — changing a filter
 *  always lands back on page 1 instead of preserving a page number that
 *  may no longer exist for the new, smaller result set (brief §80: "reset
 *  page=1"). */
function applyFilters(
    overrides: Record<string, string | number | null | undefined> = {},
) {
    router.get(
        '/admin/participants',
        {
            event_edition_id: props.selectedEventEditionId,
            q: q.value || undefined,
            event_race_id: props.filters.event_race_id ?? undefined,
            result: props.filters.result ?? undefined,
            legacy_plate: props.filters.legacy_plate ?? undefined,
            product: props.filters.product ?? undefined,
            ...overrides,
        },
        { preserveState: true, replace: true },
    );
}

const debouncedSearch = useDebounceFn(
    () => applyFilters({ q: q.value || undefined }),
    350,
);
watch(q, debouncedSearch);

const kpiCards = (metrics: Record<string, number>) => [
    { label: 'Participantes', value: metrics.participants, icon: Users },
    { label: 'Finishers', value: metrics.finishers, icon: Trophy },
    {
        label: 'Legacy Plate comprada',
        value: metrics.legacy_plates_bought,
        icon: Boxes,
    },
    {
        label: 'Legacy Plate pagada',
        value: metrics.legacy_plates_paid,
        icon: Boxes,
    },
    { label: 'Trisuits', value: metrics.trisuits, icon: ShoppingBag },
    { label: 'FAST T1 SOCKS', value: metrics.fast_t1_socks, icon: ShoppingBag },
    { label: 'CHILL BAND', value: metrics.chill_band, icon: ShoppingBag },
    { label: 'RACEPACK', value: metrics.racepack, icon: ShoppingBag },
];

const page = usePage();
const canImport = computed(() =>
    (page.props.auth?.permissions ?? []).includes('imports.manage'),
);

function exportUrl() {
    const params = new URLSearchParams();
    params.set('event_edition_id', String(props.selectedEventEditionId));

    if (q.value) {
        params.set('q', q.value);
    }

    if (props.filters.event_race_id) {
        params.set('event_race_id', String(props.filters.event_race_id));
    }

    if (props.filters.result) {
        params.set('result', props.filters.result);
    }

    if (props.filters.legacy_plate) {
        params.set('legacy_plate', props.filters.legacy_plate);
    }

    if (props.filters.product) {
        params.set('product', props.filters.product);
    }

    return `/admin/participants/export?${params.toString()}`;
}
</script>

<template>
    <Head title="Participantes" />

    <div class="w-full px-4 py-4 sm:px-6 md:py-6 lg:px-8 xl:px-10">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold text-foreground">Participantes</h1>
                <p class="text-sm text-muted-foreground">
                    Los corredores reales del evento — filtra, mide, exporta.
                </p>
            </div>
            <EventEditionSelector
                :events="events"
                :model-value="selectedEventEditionId"
            />
        </div>

        <div
            v-if="!selectedEventEditionId"
            class="flex flex-col items-center gap-3 rounded-2xl border border-dashed border-border py-20 text-center text-muted-foreground/80"
        >
            <Users class="size-10 text-muted-foreground/80" />
            <p>Selecciona un evento para ver sus participantes.</p>
        </div>

        <template v-else>
            <div
                v-if="metrics"
                class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4 xl:grid-cols-8"
            >
                <div
                    v-for="card in kpiCards(metrics)"
                    :key="card.label"
                    class="rounded-xl border border-border bg-card/40 p-4"
                >
                    <component
                        :is="card.icon"
                        class="size-4 text-fl-gold-ink"
                    />
                    <p class="mt-2 text-xl font-bold text-foreground">
                        {{ card.value }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ card.label }}
                    </p>
                </div>
                <div class="rounded-xl border border-fl-gold/20 bg-card/40 p-4">
                    <p class="text-xs tracking-wide text-fl-gold-ink uppercase">
                        Ingresos
                    </p>
                    <Money
                        :minor="metrics.revenue_minor"
                        class="mt-2 block text-xl font-bold text-foreground"
                    />
                </div>
            </div>

            <!-- Filters -->
            <div class="mb-4 flex flex-wrap items-center gap-2">
                <Input
                    v-model="q"
                    placeholder="Buscar nombre, bib, correo…"
                    class="w-56 border-border bg-card/60 text-foreground"
                />
                <Select
                    :model-value="filters.event_race_id ?? undefined"
                    @update:model-value="
                        (v) => applyFilters({ event_race_id: toFilterValue(v) })
                    "
                >
                    <SelectTrigger
                        class="w-44 border-border bg-background text-foreground"
                    >
                        <SelectValue placeholder="Carrera" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="race in races"
                            :key="race.id"
                            :value="race.id"
                        >
                            {{ race.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <Select
                    :model-value="filters.result ?? undefined"
                    @update:model-value="
                        (v) => applyFilters({ result: toFilterValue(v) })
                    "
                >
                    <SelectTrigger
                        class="w-40 border-border bg-background text-foreground"
                    >
                        <SelectValue placeholder="Resultado" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="finished">Finalizado</SelectItem>
                        <SelectItem value="pending">Pendiente</SelectItem>
                    </SelectContent>
                </Select>
                <Select
                    :model-value="filters.legacy_plate ?? undefined"
                    @update:model-value="
                        (v) => applyFilters({ legacy_plate: toFilterValue(v) })
                    "
                >
                    <SelectTrigger
                        class="w-48 border-border bg-background text-foreground"
                    >
                        <SelectValue placeholder="Legacy Plate" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="(label, key) in legacyPlateLabels"
                            :key="key"
                            :value="key"
                        >
                            {{ label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <Select
                    :model-value="filters.product ?? undefined"
                    @update:model-value="
                        (v) => applyFilters({ product: toFilterValue(v) })
                    "
                >
                    <SelectTrigger
                        class="w-48 border-border bg-background text-foreground"
                    >
                        <SelectValue placeholder="Producto" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="p in products"
                            :key="p.slug"
                            :value="p.slug"
                        >
                            {{ p.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>

                <Link
                    v-if="canImport"
                    href="/imports"
                    class="ml-auto flex items-center gap-1.5 rounded-md border border-border px-3 py-2 text-sm text-muted-foreground transition-colors hover:border-fl-gold/30 hover:text-fl-gold-ink"
                >
                    <Upload class="size-4" />
                    Importar
                </Link>

                <a
                    :href="exportUrl()"
                    :class="{ 'ml-auto': !canImport }"
                    class="flex items-center gap-1.5 rounded-md border border-border px-3 py-2 text-sm text-muted-foreground transition-colors hover:border-fl-gold/30 hover:text-fl-gold-ink"
                >
                    <Download class="size-4" />
                    Exportar CSV
                </a>
            </div>

            <div
                v-if="participants"
                class="overflow-x-auto rounded-xl border border-border"
            >
                <table class="w-full text-sm">
                    <thead>
                        <tr
                            class="border-b border-border bg-card/40 text-left text-xs text-muted-foreground uppercase"
                        >
                            <th class="px-4 py-3 font-medium">Bib</th>
                            <th class="px-4 py-3 font-medium">Atleta</th>
                            <th class="px-4 py-3 font-medium">Carrera</th>
                            <th class="px-4 py-3 font-medium">Resultado</th>
                            <th class="px-4 py-3 font-medium">Legacy Plate</th>
                            <th class="px-4 py-3 font-medium">Pago</th>
                            <th class="px-4 py-3 font-medium">Productos</th>
                            <th class="px-4 py-3 font-medium">Media</th>
                            <th class="px-4 py-3 font-medium"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in participants.data"
                            :key="row.id"
                            class="border-b border-border text-foreground transition-colors last:border-0 hover:bg-foreground/[0.03]"
                        >
                            <td class="px-4 py-3 font-mono text-fl-gold-ink">
                                {{
                                    row.bib_number ? `#${row.bib_number}` : '—'
                                }}
                            </td>
                            <td class="px-4 py-3">{{ row.name }}</td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ row.race ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <span v-if="row.official_time"
                                    >{{ row.official_time }} ·
                                    {{ row.pace }}</span
                                >
                                <span v-else class="text-muted-foreground/80">{{
                                    resultLabels[row.result_status ?? ''] ??
                                    'Sin resultado'
                                }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <Badge
                                    v-if="row.legacy_plate_status"
                                    variant="outline"
                                    :class="
                                        legacyPlateStatusBadge[
                                            row.legacy_plate_status
                                        ] ??
                                        'border-foreground/15 text-muted-foreground'
                                    "
                                >
                                    {{ row.legacy_plate_status }}
                                </Badge>
                                <span v-else class="text-muted-foreground/80"
                                    >—</span
                                >
                            </td>
                            <td class="px-4 py-3">
                                {{ row.payment_status ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{
                                    row.products.length
                                        ? row.products.join(', ')
                                        : '—'
                                }}
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    v-if="row.media_count"
                                    class="flex items-center gap-1 text-muted-foreground"
                                >
                                    <Camera class="size-3.5" />{{
                                        row.media_count
                                    }}
                                </span>
                                <span v-else class="text-muted-foreground/80"
                                    >—</span
                                >
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Link
                                    :href="`/admin/participants/${row.id}`"
                                    class="text-xs font-medium text-fl-gold-ink hover:underline"
                                >
                                    Ver
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="!participants.data.length">
                            <td
                                colspan="9"
                                class="px-4 py-10 text-center text-muted-foreground/80"
                            >
                                Sin participantes para estos filtros.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                <Pagination :links="participants?.links ?? []" />
            </div>
        </template>
    </div>
</template>
