<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { CheckCircle2, Factory, XCircle } from '@lucide/vue';
import EventEditionSelector from '@/components/admin/EventEditionSelector.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type QueueRow = {
    id: number;
    bib_number: string | null;
    athlete_name: string | null;
    race: string | null;
    model: string | null;
    status: string;
    paid: boolean;
    official_time: string | null;
    pace: string | null;
    eligible: boolean;
    reasons: string[];
};

defineProps<{
    events: { id: number; name: string }[];
    selectedEventEditionId: number | null;
    queue: QueueRow[];
}>();

const reasonLabels: Record<string, string> = {
    LEGACY_PLATE_NOT_PAID: 'Pago pendiente',
    NO_PARTICIPANT_LINKED: 'Sin participante vinculado',
    IDENTITY_CONFLICT: 'Conflicto de identidad',
    NO_RESULT: 'Falta resultado',
    PLATE_ALREADY_EXISTS: 'Ya tiene Legacy Plate',
    NO_MODEL: 'Modelo no disponible',
    LEGACY_PLATE_ALREADY_EXISTS: 'Ya se generó',
};

const statusLabels: Record<string, string> = {
    pending_payment: 'Pago pendiente',
    paid: 'Pagado',
    linked: 'Vinculado',
    queued: 'En cola',
    produced: 'Producido',
    delivered: 'Entregado',
    cancelled: 'Cancelado',
};

function produce(entitlementId: number) {
    router.post(
        `/admin/legacy-plates/production/entitlements/${entitlementId}/produce`,
        {},
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head title="Producción de Legacy Plate" />

    <div class="p-4 md:p-8">
        <div class="mb-6">
            <h1 class="flex items-center gap-2 text-xl font-bold text-white">
                <Factory class="size-5 text-fl-gold" />
                Producción de Legacy Plate
            </h1>
            <p class="mt-1 text-sm text-white/50">
                Selecciona un evento para ver su cola.
            </p>
        </div>

        <EventEditionSelector
            :events="events"
            :model-value="selectedEventEditionId"
            class="mb-6"
        />

        <div
            v-if="selectedEventEditionId"
            class="overflow-x-auto rounded-xl border border-white/10"
        >
            <table class="w-full text-sm">
                <thead>
                    <tr
                        class="border-b border-white/10 bg-fl-graphite/40 text-left text-xs text-white/50 uppercase"
                    >
                        <th class="px-4 py-3 font-medium">Bib</th>
                        <th class="px-4 py-3 font-medium">Atleta</th>
                        <th class="px-4 py-3 font-medium">Modelo</th>
                        <th class="px-4 py-3 font-medium">Pago</th>
                        <th class="px-4 py-3 font-medium">Tiempo</th>
                        <th class="px-4 py-3 font-medium">Ritmo</th>
                        <th class="px-4 py-3 font-medium">Estado</th>
                        <th class="px-4 py-3 font-medium">Elegibilidad</th>
                        <th class="px-4 py-3 font-medium"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in queue"
                        :key="row.id"
                        class="border-b border-white/5 text-white/80 last:border-0"
                    >
                        <td class="px-4 py-3 font-mono text-fl-gold">
                            {{ row.bib_number ? `#${row.bib_number}` : '—' }}
                        </td>
                        <td class="px-4 py-3">{{ row.athlete_name ?? '—' }}</td>
                        <td class="px-4 py-3">{{ row.model ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <Badge
                                variant="outline"
                                :class="
                                    row.paid
                                        ? 'border-emerald-500/30 text-emerald-400'
                                        : 'border-amber-500/30 text-amber-400'
                                "
                            >
                                {{ row.paid ? 'Pagado' : 'Pendiente' }}
                            </Badge>
                        </td>
                        <td class="px-4 py-3">
                            {{ row.official_time ?? '—' }}
                        </td>
                        <td class="px-4 py-3">{{ row.pace ?? '—' }}</td>
                        <td class="px-4 py-3 text-white/50">
                            {{ statusLabels[row.status] ?? row.status }}
                        </td>
                        <td class="px-4 py-3">
                            <div
                                v-if="row.eligible"
                                class="flex items-center gap-1.5 text-emerald-400"
                            >
                                <CheckCircle2 class="size-4" />
                                Listo para producir
                            </div>
                            <div v-else class="flex flex-wrap gap-1">
                                <Badge
                                    v-for="reason in row.reasons"
                                    :key="reason"
                                    variant="outline"
                                    class="border-red-500/30 text-red-400"
                                >
                                    <XCircle class="mr-1 size-3" />
                                    {{ reasonLabels[reason] ?? reason }}
                                </Badge>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <Button
                                size="sm"
                                :disabled="!row.eligible"
                                class="bg-fl-gold text-fl-black hover:bg-fl-gold-soft disabled:opacity-30"
                                @click="produce(row.id)"
                            >
                                Producir
                            </Button>
                        </td>
                    </tr>
                    <tr v-if="!queue.length">
                        <td
                            colspan="9"
                            class="px-4 py-10 text-center text-white/30"
                        >
                            Sin Legacy Plates pendientes para este evento.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p v-else class="text-sm text-white/30">
            Selecciona un evento para ver la cola de producción.
        </p>
    </div>
</template>
