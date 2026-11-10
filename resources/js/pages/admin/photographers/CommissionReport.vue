<script setup lang="ts">
/**
 * Administración → Fotógrafos → Comisiones → Reporte: per photographer
 * (and currency) gross sales, Finisher commission, gateway fee, net,
 * refunds, what was already paid out and the payout-due summary
 * (pending net − clawbacks). Built from the frozen PhotoSale snapshots,
 * never recomputed from today's rate. Optional date range.
 */
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, ChartColumn, Wallet } from '@lucide/vue';
import { ref } from 'vue';
import DatePicker from '@/components/DatePicker.vue';
import { Button } from '@/components/ui/button';

type Row = {
    photographer_profile_id: number;
    display_name: string;
    uuid: string;
    currency: string;
    sales_count: number;
    gross_minor: number;
    commission_minor: number;
    gateway_fee_minor: number;
    net_minor: number;
    refunded_count: number;
    reversed_net_minor: number;
    reversed_commission_minor: number;
    paid_out_minor: number;
    pending_minor: number;
    clawback_minor: number;
    due_minor: number;
    has_payout_data: boolean;
};

const props = defineProps<{
    rows: Row[];
    totals: Record<string, Record<string, number>>;
    filters: { desde: string | null; hasta: string | null };
}>();

const desde = ref(props.filters.desde);
const hasta = ref(props.filters.hasta);

function apply() {
    router.get(
        '/admin/photographer-commissions/reporte',
        Object.fromEntries(
            Object.entries({ desde: desde.value, hasta: hasta.value }).filter(
                ([, v]) => v,
            ),
        ),
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

const money = (minor: number, currency = 'MXN') =>
    new Intl.NumberFormat('es-MX', { style: 'currency', currency }).format(
        minor / 100,
    );
</script>

<template>
    <Head title="Reporte de comisiones" />

    <div
        class="fl-page-enter w-full space-y-6 px-4 py-6 md:px-6 md:py-8 xl:px-8"
    >
        <div class="flex flex-wrap items-center gap-3">
            <Link
                href="/admin/photographer-commissions"
                class="flex size-10 items-center justify-center rounded-full border border-border hover:bg-muted"
                aria-label="Volver a comisiones"
                ><ArrowLeft class="size-4"
            /></Link>
            <span
                class="flex size-11 items-center justify-center rounded-xl bg-fl-cream text-fl-gold-ink"
                ><ChartColumn class="size-5"
            /></span>
            <div class="min-w-0 flex-1">
                <h1 class="text-xl font-semibold">
                    Ventas, comisiones y pagos a fotógrafos
                </h1>
                <p class="text-sm text-muted-foreground">
                    Montos congelados en cada venta. «Por pagar» = ganancia
                    pendiente − reembolsos de ventas ya transferidas.
                </p>
            </div>
        </div>

        <form
            class="fl-card flex flex-wrap items-end gap-3 p-4"
            @submit.prevent="apply"
        >
            <div class="grid gap-1.5">
                <span class="text-xs text-muted-foreground">Desde</span>
                <DatePicker v-model="desde" clearable />
            </div>
            <div class="grid gap-1.5">
                <span class="text-xs text-muted-foreground">Hasta</span>
                <DatePicker v-model="hasta" clearable />
            </div>
            <Button type="submit" class="fl-press rounded-full">Aplicar</Button>
        </form>

        <div
            v-for="(t, currency) in totals"
            :key="currency"
            class="grid grid-cols-2 gap-3 lg:grid-cols-5"
        >
            <div class="fl-card p-4">
                <p class="text-xs text-muted-foreground">
                    Ventas brutas ({{ currency }})
                </p>
                <p class="legacy-numeric mt-1 text-xl font-semibold">
                    {{ money(t.gross_minor, String(currency)) }}
                </p>
                <p class="text-xs text-muted-foreground">
                    {{ t.sales_count }} fotos
                </p>
            </div>
            <div class="fl-card p-4">
                <p class="text-xs text-muted-foreground">Comisión Finisher</p>
                <p class="legacy-numeric mt-1 text-xl font-semibold">
                    {{
                        money(
                            t.commission_minor - t.reversed_commission_minor,
                            String(currency),
                        )
                    }}
                </p>
                <p class="text-xs text-muted-foreground">neto de reembolsos</p>
            </div>
            <div class="fl-card p-4">
                <p class="text-xs text-muted-foreground">Pasarela (est.)</p>
                <p class="legacy-numeric mt-1 text-xl font-semibold">
                    {{ money(t.gateway_fee_minor, String(currency)) }}
                </p>
            </div>
            <div class="fl-card p-4">
                <p class="text-xs text-muted-foreground">
                    Ganancia de fotógrafos
                </p>
                <p class="legacy-numeric mt-1 text-xl font-semibold">
                    {{
                        money(
                            t.net_minor - t.reversed_net_minor,
                            String(currency),
                        )
                    }}
                </p>
                <p class="text-xs text-muted-foreground">
                    ya pagado {{ money(t.paid_out_minor, String(currency)) }}
                </p>
            </div>
            <div class="fl-card border-emerald-200 bg-emerald-50/60 p-4">
                <p class="flex items-center gap-1.5 text-xs text-emerald-800">
                    <Wallet class="size-3.5" /> Por pagar
                </p>
                <p
                    class="legacy-numeric mt-1 text-xl font-semibold text-emerald-900"
                >
                    {{ money(t.due_minor, String(currency)) }}
                </p>
            </div>
        </div>

        <section class="fl-card overflow-x-auto p-0">
            <table v-if="rows.length" class="w-full min-w-[900px] text-sm">
                <thead class="bg-muted/50 text-xs text-muted-foreground">
                    <tr class="text-left">
                        <th class="px-4 py-2.5 font-medium">Fotógrafo</th>
                        <th class="px-3 py-2.5 text-right font-medium">
                            Fotos
                        </th>
                        <th class="px-3 py-2.5 text-right font-medium">
                            Bruto
                        </th>
                        <th class="px-3 py-2.5 text-right font-medium">
                            Comisión
                        </th>
                        <th class="px-3 py-2.5 text-right font-medium">
                            Pasarela
                        </th>
                        <th class="px-3 py-2.5 text-right font-medium">Neto</th>
                        <th class="px-3 py-2.5 text-right font-medium">
                            Reembolsos
                        </th>
                        <th class="px-3 py-2.5 text-right font-medium">
                            Pagado
                        </th>
                        <th class="px-4 py-2.5 text-right font-medium">
                            Por pagar
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="r in rows"
                        :key="`${r.uuid}-${r.currency}`"
                        class="border-t border-border"
                    >
                        <td class="px-4 py-2.5">
                            <Link
                                :href="`/admin/photographers/${r.uuid}`"
                                class="fl-link font-medium"
                                >{{ r.display_name }}</Link
                            >
                            <span
                                v-if="!r.has_payout_data && r.due_minor > 0"
                                class="ml-2 rounded-full bg-amber-100 px-2 py-0.5 text-[11px] text-amber-800"
                                >Sin CLABE</span
                            >
                        </td>
                        <td class="legacy-numeric px-3 py-2.5 text-right">
                            {{ r.sales_count }}
                        </td>
                        <td class="legacy-numeric px-3 py-2.5 text-right">
                            {{ money(r.gross_minor, r.currency) }}
                        </td>
                        <td class="legacy-numeric px-3 py-2.5 text-right">
                            {{ money(r.commission_minor, r.currency) }}
                        </td>
                        <td class="legacy-numeric px-3 py-2.5 text-right">
                            {{ money(r.gateway_fee_minor, r.currency) }}
                        </td>
                        <td class="legacy-numeric px-3 py-2.5 text-right">
                            {{ money(r.net_minor, r.currency) }}
                        </td>
                        <td
                            class="legacy-numeric px-3 py-2.5 text-right"
                            :class="r.reversed_net_minor ? 'text-red-700' : ''"
                        >
                            {{
                                r.reversed_net_minor
                                    ? `−${money(r.reversed_net_minor, r.currency)} (${r.refunded_count})`
                                    : '—'
                            }}
                        </td>
                        <td class="legacy-numeric px-3 py-2.5 text-right">
                            {{ money(r.paid_out_minor, r.currency) }}
                        </td>
                        <td
                            class="legacy-numeric px-4 py-2.5 text-right font-semibold"
                            :class="
                                r.due_minor < 0
                                    ? 'text-red-700'
                                    : 'text-emerald-800'
                            "
                        >
                            {{ money(r.due_minor, r.currency) }}
                        </td>
                    </tr>
                </tbody>
            </table>
            <p v-else class="p-12 text-center text-sm text-muted-foreground">
                No hay ventas de fotos en este periodo.
            </p>
        </section>
    </div>
</template>
