<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import type { AcceptableValue } from 'reka-ui';
import AdminTable from '@/components/admin/AdminTable.vue';
import Money from '@/components/shared/Money.vue';
import { Badge } from '@/components/ui/badge';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    legacyPlateEntitlementStatus,
    preregistrationStatus,
    statusClass,
    statusLabel,
} from '@/lib/statusLabels';

type PreregistrationRow = {
    id: number;
    name: string;
    email: string;
    event: string | null;
    race: string | null;
    bib_number: string | null;
    status: string;
    created_at: string | null;
    legacy_plate_status: string;
    legacy_plate_model: string | null;
    legacy_plate_price_minor: number | null;
    legacy_plate_currency: string | null;
    legacy_plate_method: string | null;
    legacy_plate_paid_at: string | null;
    order_number: string | null;
    order_uuid: string | null;
    athlete_linked: boolean;
    participant_linked: boolean;
};

const props = defineProps<{
    preregistrations: {
        data: PreregistrationRow[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    statuses: string[];
    filters: { q: string; status: string | null; legacy_plate: string };
    counters: {
        preregistrations: number;
        presales: number;
        paid: number;
        pending: number;
        conversion_rate: number;
    };
}>();

const columns = [
    { key: 'name', label: 'Nombre' },
    { key: 'event', label: 'Evento' },
    { key: 'race', label: 'Carrera' },
    { key: 'created_at', label: 'Prerregistro' },
    { key: 'legacy_plate_status', label: 'Legacy Plate' },
    { key: 'legacy_plate_model', label: 'Modelo' },
    { key: 'legacy_plate_paid_at', label: 'Pago' },
    { key: 'participant_linked', label: 'Vinculado' },
];

// A status change navigates without a `page` param, same as the search
// box — it always lands back on page 1 for the new, possibly smaller,
// result set rather than preserving a page number that may not exist
// anymore (product UX consolidation brief §80).
function onStatusChange(value: AcceptableValue) {
    router.get(
        '/admin/preregistrations',
        {
            q: props.filters.q || undefined,
            status: typeof value === 'string' ? value : undefined,
            legacy_plate: props.filters.legacy_plate || undefined,
        },
        { preserveState: true, replace: true },
    );
}

const filterOptions = [
    { value: 'all', label: 'Todos' },
    { value: 'none', label: 'Sin Legacy Plate' },
    { value: 'pending', label: 'Pago pendiente' },
    { value: 'paid', label: 'Legacy Plate pagado' },
    { value: 'linked', label: 'Vinculado a participante' },
    { value: 'unlinked', label: 'Sin vincular' },
];

function applyFilter(value: string) {
    router.get(
        '/admin/preregistrations',
        {
            legacy_plate: value,
            q: props.filters.q || undefined,
            status: props.filters.status || undefined,
        },
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <Head title="Prerregistros" />

    <div class="p-4 md:p-8">
        <h1 class="mb-1 text-xl font-bold text-white">Prerregistros</h1>
        <p class="mb-6 text-sm text-white/50">
            Prerregistro al evento y preventa de Legacy Plate, vistos juntos —
            siguen siendo cosas distintas.
        </p>

        <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-5">
            <div
                class="rounded-xl border border-white/10 bg-fl-graphite/30 p-4"
            >
                <p class="text-[10px] tracking-wide text-white/40 uppercase">
                    Prerregistros
                </p>
                <p class="mt-1 text-2xl font-bold text-white">
                    {{ counters.preregistrations }}
                </p>
            </div>
            <div
                class="rounded-xl border border-white/10 bg-fl-graphite/30 p-4"
            >
                <p class="text-[10px] tracking-wide text-white/40 uppercase">
                    Preventas Legacy Plate
                </p>
                <p class="mt-1 text-2xl font-bold text-fl-gold-soft">
                    {{ counters.presales }}
                </p>
            </div>
            <div
                class="rounded-xl border border-white/10 bg-fl-graphite/30 p-4"
            >
                <p class="text-[10px] tracking-wide text-white/40 uppercase">
                    Pagadas
                </p>
                <p class="mt-1 text-2xl font-bold text-emerald-400">
                    {{ counters.paid }}
                </p>
            </div>
            <div
                class="rounded-xl border border-white/10 bg-fl-graphite/30 p-4"
            >
                <p class="text-[10px] tracking-wide text-white/40 uppercase">
                    Pendientes
                </p>
                <p class="mt-1 text-2xl font-bold text-amber-400">
                    {{ counters.pending }}
                </p>
            </div>
            <div
                class="rounded-xl border border-white/10 bg-fl-graphite/30 p-4"
            >
                <p class="text-[10px] tracking-wide text-white/40 uppercase">
                    Conversión
                </p>
                <p class="mt-1 text-2xl font-bold text-white">
                    {{ counters.conversion_rate }}%
                </p>
            </div>
        </div>

        <div class="mb-4 flex flex-wrap gap-2">
            <button
                v-for="option in filterOptions"
                :key="option.value"
                type="button"
                class="rounded-full border px-3 py-1.5 text-xs uppercase transition"
                :class="
                    filters.legacy_plate === option.value
                        ? 'border-fl-gold text-fl-gold-soft'
                        : 'border-white/15 text-white/50 hover:border-white/30'
                "
                @click="applyFilter(option.value)"
            >
                {{ option.label }}
            </button>
        </div>

        <div class="mb-4">
            <Select
                :model-value="filters.status ?? undefined"
                @update:model-value="onStatusChange"
            >
                <SelectTrigger
                    class="w-48 border-white/10 bg-fl-black text-white"
                >
                    <SelectValue placeholder="Todos los estados" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="status in statuses"
                        :key="status"
                        :value="status"
                    >
                        {{ statusLabel(preregistrationStatus, status) }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>

        <AdminTable
            :columns="columns"
            :rows="preregistrations"
            searchable
            :initial-query="filters.q"
        >
            <template #cell-legacy_plate_status="{ row }">
                <Badge
                    variant="outline"
                    :class="
                        statusClass(
                            legacyPlateEntitlementStatus,
                            row.legacy_plate_status as string,
                        )
                    "
                >
                    {{
                        statusLabel(
                            legacyPlateEntitlementStatus,
                            row.legacy_plate_status as string,
                        )
                    }}
                </Badge>
            </template>
            <template #cell-legacy_plate_paid_at="{ row }">
                <span
                    v-if="row.legacy_plate_price_minor !== null"
                    class="text-white/70"
                >
                    <Money
                        :minor="row.legacy_plate_price_minor as number"
                        :currency="
                            (row.legacy_plate_currency as string) ?? 'MXN'
                        "
                    />
                    <span class="block text-xs text-white/40">{{
                        row.legacy_plate_paid_at ?? '—'
                    }}</span>
                </span>
                <span v-else class="text-white/30">—</span>
            </template>
            <template #cell-participant_linked="{ row }">
                <Badge
                    v-if="row.participant_linked"
                    variant="outline"
                    class="border-emerald-500/30 text-emerald-400"
                    >Vinculado
                    {{ row.bib_number ? `· #${row.bib_number}` : '' }}</Badge
                >
                <Badge
                    v-else
                    variant="outline"
                    class="border-white/20 text-white/50"
                    >Sin vincular</Badge
                >
            </template>
        </AdminTable>
    </div>
</template>
