<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Ticket } from '@lucide/vue';
import { computed, ref } from 'vue';
import EventEditionSelector from '@/components/admin/EventEditionSelector.vue';
import SecondaryNav from '@/components/admin/SecondaryNav.vue';
import HelpPopover from '@/components/HelpPopover.vue';
import PaymentStatusBadge from '@/components/shared/PaymentStatusBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { LEGACY_PLATE_AREA_NAV } from '@/config/areaNav';

type LinkCandidate = {
    id: number;
    bib_number: string | null;
    full_name: string;
};

type PresaleRow = {
    id: number;
    athlete: string | null;
    athlete_id: number | null;
    bib_number: string | null;
    model: string | null;
    status: string;
    price_type: string | null;
    paid_at: string | null;
    linked: boolean;
    order_uuid: string | null;
    payment_status: string | null;
    link_candidates: LinkCandidate[];
};

const props = defineProps<{
    events: { id: number; name: string }[];
    selectedEventEditionId: number | null;
    presales: PresaleRow[];
}>();

const kpis = computed(() => {
    const total = props.presales.length;
    const paid = props.presales.filter((p) =>
        ['paid', 'linked', 'queued', 'produced', 'delivered'].includes(
            p.status,
        ),
    ).length;
    const pendingPayment = props.presales.filter(
        (p) => p.status === 'pending_payment',
    ).length;
    const pendingLink = props.presales.filter(
        (p) => !p.linked && p.status !== 'cancelled',
    ).length;
    const readyForProduction = props.presales.filter(
        (p) => p.status === 'linked',
    ).length;

    return [
        { label: 'Total preventas', value: total },
        { label: 'Pagadas', value: paid },
        { label: 'Pendientes de pago', value: pendingPayment },
        { label: 'Pendientes de vincular', value: pendingLink },
        { label: 'Listas para producción', value: readyForProduction },
    ];
});

const statusLabels: Record<string, string> = {
    pending_payment: 'Pago pendiente',
    paid: 'Pagado',
    linked: 'Vinculado',
    queued: 'En cola',
    produced: 'Producido',
    delivered: 'Entregado',
    cancelled: 'Cancelado',
};

const statusClasses: Record<string, string> = {
    pending_payment: 'border-amber-500/30 text-amber-400',
    paid: 'border-sky-500/30 text-sky-400',
    linked: 'border-sky-500/30 text-sky-400',
    queued: 'border-fl-gold/30 text-fl-gold-soft',
    produced: 'border-emerald-500/30 text-emerald-400',
    delivered: 'border-emerald-500/30 text-emerald-400',
    cancelled: 'border-red-500/30 text-red-400',
};

const selectedCandidate = ref<Record<number, string>>({});

function linkParticipant(presaleId: number) {
    const participantId = selectedCandidate.value[presaleId];

    if (!participantId) {
        return;
    }

    router.post(
        `/admin/legacy-plates/presales/${presaleId}/link`,
        { event_participant_id: participantId },
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head title="Preventas Legacy Plate" />

    <div class="p-4 md:p-8">
        <SecondaryNav :items="LEGACY_PLATE_AREA_NAV" />

        <div class="mb-6">
            <h1 class="flex items-center gap-1.5 text-xl font-bold text-white">
                <Ticket class="size-5 text-fl-gold" />
                Preventas de Legacy Plate
                <HelpPopover
                    title="¿Qué es una preventa?"
                    text="Una preventa es un Legacy Plate comprado antes del evento. Se vincula al corredor cuando su participación oficial está disponible."
                />
            </h1>
            <p class="mt-1 text-sm text-white/50">
                Compras realizadas antes del evento. Aquí puedes revisar pagos,
                vincular corredores cuando aparezcan en la lista oficial y
                preparar placas para producción.
            </p>
        </div>

        <EventEditionSelector
            :events="events"
            :model-value="selectedEventEditionId"
            class="mb-6"
        />

        <div
            v-if="selectedEventEditionId"
            class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-5"
        >
            <div
                v-for="kpi in kpis"
                :key="kpi.label"
                class="rounded-xl border border-white/10 bg-fl-graphite/40 p-4"
            >
                <p class="text-xl font-bold text-white">{{ kpi.value }}</p>
                <p class="text-xs text-white/50">{{ kpi.label }}</p>
            </div>
        </div>

        <div
            v-if="selectedEventEditionId"
            class="overflow-x-auto rounded-xl border border-white/10"
        >
            <table class="w-full text-sm">
                <thead>
                    <tr
                        class="border-b border-white/10 bg-fl-graphite/40 text-left text-xs text-white/50 uppercase"
                    >
                        <th class="px-4 py-3 font-medium">Atleta</th>
                        <th class="px-4 py-3 font-medium">Bib</th>
                        <th class="px-4 py-3 font-medium">Modelo</th>
                        <th class="px-4 py-3 font-medium">Ventana</th>
                        <th class="px-4 py-3 font-medium">Estado</th>
                        <th class="px-4 py-3 font-medium">Pago</th>
                        <th class="px-4 py-3 font-medium"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in presales"
                        :key="row.id"
                        class="border-b border-white/5 text-white/80 last:border-0"
                    >
                        <td class="px-4 py-3">
                            <Link
                                v-if="row.athlete_id"
                                :href="`/admin/athletes/${row.athlete_id}`"
                                class="hover:text-fl-gold-soft hover:underline"
                            >
                                {{ row.athlete }}
                            </Link>
                            <span v-else class="text-white/40"
                                >Sin vincular todavía</span
                            >
                        </td>
                        <td class="px-4 py-3 font-mono text-fl-gold">
                            {{ row.linked ? `#${row.bib_number}` : '—' }}
                        </td>
                        <td class="px-4 py-3">{{ row.model ?? '—' }}</td>
                        <td class="px-4 py-3 text-white/50">
                            {{ row.price_type ?? '—' }}
                        </td>
                        <td class="px-4 py-3">
                            <Badge
                                variant="outline"
                                :class="
                                    statusClasses[row.status] ??
                                    'border-white/20 text-white/50'
                                "
                            >
                                {{ statusLabels[row.status] ?? row.status }}
                            </Badge>
                        </td>
                        <td class="px-4 py-3">
                            <PaymentStatusBadge
                                v-if="row.payment_status"
                                :status="row.payment_status"
                            />
                            <span v-else class="text-xs text-white/30"
                                >Sin pedido</span
                            >
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <template
                                    v-if="
                                        !row.linked &&
                                        row.link_candidates.length
                                    "
                                >
                                    <Select v-model="selectedCandidate[row.id]">
                                        <SelectTrigger
                                            class="h-8 w-44 border-white/10 bg-fl-graphite/60 text-xs text-white"
                                        >
                                            <SelectValue
                                                placeholder="Vincular a…"
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="candidate in row.link_candidates"
                                                :key="candidate.id"
                                                :value="String(candidate.id)"
                                            >
                                                {{ candidate.full_name
                                                }}<span
                                                    v-if="candidate.bib_number"
                                                >
                                                    · #{{
                                                        candidate.bib_number
                                                    }}</span
                                                >
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <Button
                                        size="sm"
                                        class="h-8 bg-fl-gold text-fl-black hover:bg-fl-gold-soft"
                                        :disabled="!selectedCandidate[row.id]"
                                        @click="linkParticipant(row.id)"
                                    >
                                        Vincular
                                    </Button>
                                </template>
                                <Link
                                    v-if="row.order_uuid"
                                    :href="`/admin/orders/${row.order_uuid}`"
                                    class="text-xs font-medium text-fl-gold-soft hover:underline"
                                >
                                    {{
                                        row.payment_status === 'paid'
                                            ? 'Ver pedido'
                                            : 'Registrar pago'
                                    }}
                                </Link>
                                <Link
                                    v-if="row.status === 'linked'"
                                    href="/admin/legacy-plates/production"
                                    class="text-xs font-medium text-fl-gold-soft hover:underline"
                                >
                                    Ir a producción
                                </Link>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!presales.length">
                        <td
                            colspan="7"
                            class="px-4 py-10 text-center text-white/30"
                        >
                            No hay preventas para este evento.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p v-else class="text-sm text-white/30">
            Selecciona un evento para ver sus preventas.
        </p>
    </div>
</template>
