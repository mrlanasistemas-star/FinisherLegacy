<script setup lang="ts">
/**
 * Legacy → Activaciones: recent activations (time from sale to
 * activation) and pieces sold but not activated yet, oldest first —
 * "Reenviar instrucciones" only when the buyer left an email.
 */
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Mail } from '@lucide/vue';
import { computed, ref } from 'vue';
import { resend } from '@/actions/App/Http/Controllers/Admin/Commercial/ActivationController';
import SalesChannelBadge from '@/components/admin/SalesChannelBadge.vue';
import Pagination from '@/components/public/Pagination.vue';
import { Button } from '@/components/ui/button';
import { shortDate, timeAgo } from '@/lib/datetime';

type Row = {
    uuid: string;
    serial: string;
    display_code: string;
    product: string | null;
    sku: string | null;
    channel: string | null;
    ally: string | null;
    order: { uuid: string; number: string } | null;
    sold_at: string | null;
    unreported_sale: boolean;
};

const props = defineProps<{
    recent: (Row & {
        owner: string | null;
        activated_at: string | null;
        seconds_to_activation: number | null;
    })[];
    pending: {
        data: (Row & {
            status_label: string;
            location: string | null;
            buyer_email: string | null;
        })[];
        links: { url: string | null; label: string; active: boolean }[];
        total?: number;
    };
    windowHours: number;
}>();

const tab = ref<'pending' | 'recent'>('pending');
const sending = ref<string | null>(null);
const page = usePage();
const resendError = computed(
    () => (page.props.errors as Record<string, string> | undefined)?.resend,
);

function duration(seconds: number | null): string {
    if (seconds === null) {
        return '—';
    }

    if (seconds < 3600) {
        return `${Math.max(1, Math.round(seconds / 60))} min`;
    }

    if (seconds < 86400 * 2) {
        return `${Math.round(seconds / 3600)} h`;
    }

    return `${Math.round(seconds / 86400)} días`;
}

const withinWindow = (s: number | null) =>
    s !== null && s <= props.windowHours * 3600;

function send(uuid: string) {
    router.visit(resend(uuid), {
        preserveScroll: true,
        onStart: () => (sending.value = uuid),
        onFinish: () => (sending.value = null),
    });
}
</script>

<template>
    <Head title="Activaciones · Legacy" />

    <div class="w-full px-4 py-6 md:px-6 md:py-8 xl:px-8">
        <p class="fl-eyebrow">Legacy</p>
        <h1 class="fl-display mt-1 text-3xl">Activaciones</h1>
        <p class="mt-1 text-sm text-muted-foreground">
            Piezas vendidas que esperan a su atleta, y las últimas activadas.
        </p>

        <div
            class="mt-6 inline-flex rounded-full border border-border bg-card p-1"
            role="tablist"
        >
            <button
                v-for="t in [
                    { key: 'pending', label: 'Pendientes' },
                    { key: 'recent', label: 'Recientes' },
                ] as const"
                :key="t.key"
                type="button"
                role="tab"
                class="h-8 rounded-full px-4 text-xs font-semibold transition-colors"
                :class="
                    tab === t.key
                        ? 'bg-foreground text-background'
                        : 'text-muted-foreground hover:text-foreground'
                "
                :aria-selected="tab === t.key"
                @click="tab = t.key"
            >
                {{ t.label }}
            </button>
        </div>

        <p v-if="resendError" class="mt-4 text-sm text-red-700" role="alert">
            {{ resendError }}
        </p>

        <section v-if="tab === 'pending'" class="fl-card mt-4 overflow-x-auto">
            <table
                v-if="pending.data.length"
                class="w-full min-w-[44rem] text-sm"
            >
                <thead>
                    <tr
                        class="border-b border-border text-left text-xs text-muted-foreground"
                    >
                        <th class="px-4 py-3 font-medium">Pieza</th>
                        <th class="px-4 py-3 font-medium">Canal</th>
                        <th class="px-4 py-3 font-medium">Pedido</th>
                        <th class="px-4 py-3 font-medium">Vendida</th>
                        <th class="px-4 py-3 text-right font-medium">Acción</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr v-for="row in pending.data" :key="row.uuid">
                        <td class="px-4 py-3">
                            <Link
                                :href="`/admin/tags/${row.uuid}`"
                                class="font-mono text-xs font-semibold hover:underline"
                                >{{ row.display_code }}</Link
                            >
                            <span class="block text-xs text-muted-foreground"
                                >{{ row.product ?? '—' }} ·
                                {{ row.status_label }}</span
                            >
                        </td>
                        <td class="px-4 py-3">
                            <SalesChannelBadge :channel="row.channel" short />
                            <span
                                v-if="row.ally"
                                class="block text-xs text-muted-foreground"
                                >{{ row.ally }}</span
                            >
                        </td>
                        <td class="px-4 py-3">
                            <Link
                                v-if="row.order"
                                :href="`/admin/orders/${row.order.uuid}`"
                                class="font-mono text-xs hover:underline"
                                >{{ row.order.number }}</Link
                            >
                            <span
                                v-else
                                class="text-xs text-muted-foreground"
                                >{{
                                    row.unreported_sale ? 'Venta inferida' : '—'
                                }}</span
                            >
                        </td>
                        <td class="px-4 py-3 text-xs">
                            {{ row.sold_at ? timeAgo(row.sold_at) : '—' }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <Button
                                v-if="row.buyer_email"
                                variant="ghost"
                                size="sm"
                                :disabled="sending === row.uuid"
                                :title="row.buyer_email"
                                @click="send(row.uuid)"
                            >
                                <Mail class="size-3.5" /> Reenviar instrucciones
                            </Button>
                            <span v-else class="text-xs text-muted-foreground"
                                >Sin correo del comprador</span
                            >
                        </td>
                    </tr>
                </tbody>
            </table>
            <p v-else class="p-8 text-center text-sm text-muted-foreground">
                No hay piezas vendidas pendientes de activar.
            </p>
        </section>
        <div v-if="tab === 'pending'" class="mt-4">
            <Pagination :links="pending.links" />
        </div>

        <section v-else class="fl-card mt-4 overflow-x-auto">
            <table v-if="recent.length" class="w-full min-w-[44rem] text-sm">
                <thead>
                    <tr
                        class="border-b border-border text-left text-xs text-muted-foreground"
                    >
                        <th class="px-4 py-3 font-medium">Pieza</th>
                        <th class="px-4 py-3 font-medium">Atleta</th>
                        <th class="px-4 py-3 font-medium">Canal</th>
                        <th class="px-4 py-3 font-medium">Activada</th>
                        <th class="px-4 py-3 text-right font-medium">
                            Tiempo a activar
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr v-for="row in recent" :key="row.uuid">
                        <td class="px-4 py-3">
                            <Link
                                :href="`/admin/tags/${row.uuid}`"
                                class="font-mono text-xs font-semibold hover:underline"
                                >{{ row.display_code }}</Link
                            >
                            <span class="block text-xs text-muted-foreground">{{
                                row.product ?? '—'
                            }}</span>
                        </td>
                        <td class="px-4 py-3">{{ row.owner ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <SalesChannelBadge :channel="row.channel" short />
                        </td>
                        <td class="px-4 py-3 text-xs">
                            {{ shortDate(row.activated_at) }}
                        </td>
                        <td
                            class="legacy-numeric px-4 py-3 text-right"
                            :class="
                                withinWindow(row.seconds_to_activation)
                                    ? 'text-emerald-700'
                                    : ''
                            "
                        >
                            {{ duration(row.seconds_to_activation) }}
                        </td>
                    </tr>
                </tbody>
            </table>
            <p v-else class="p-8 text-center text-sm text-muted-foreground">
                Aún no hay activaciones.
            </p>
        </section>
    </div>
</template>
