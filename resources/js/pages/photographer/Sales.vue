<script setup lang="ts">
/**
 * Portal del fotógrafo — every sale with its exact split (frozen at
 * payment time in PhotoSale) and whether it was already transferred.
 */
import { Head } from '@inertiajs/vue3';
import { Building2, CreditCard, Landmark, Receipt, Wallet } from '@lucide/vue';
import Pagination from '@/components/public/Pagination.vue';
import { shortDate } from '@/lib/datetime';

type Sale = {
    uuid: string;
    thumb_url: string | null;
    event: string | null;
    gross_minor: number;
    processor_fee_minor: number;
    platform_fee_minor: number;
    net_minor: number;
    commission_percent: number;
    processor_fee_estimated: boolean;
    payment_method_label: string | null;
    payout_status: string;
    created_at: string;
};

defineProps<{
    sales: {
        data: Sale[];
        links: { url: string | null; label: string; active: boolean }[];
    };
}>();

const money = (minor: number) =>
    new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
    }).format(minor / 100);
</script>

<template>
    <Head title="Ventas y pagos" />

    <div class="mx-auto w-full max-w-5xl space-y-6 p-4 md:p-8">
        <div class="flex items-center gap-3">
            <span
                class="flex size-11 items-center justify-center rounded-xl bg-fl-cream text-fl-gold-ink"
            >
                <Receipt class="size-5" />
            </span>
            <div>
                <h1 class="text-xl font-semibold">Ventas y pagos</h1>
                <p class="text-sm text-muted-foreground">
                    Cada foto vendida, con lo que se quedó cada parte.
                </p>
            </div>
        </div>

        <div v-if="sales.data.length" class="space-y-3">
            <article
                v-for="sale in sales.data"
                :key="sale.uuid"
                class="fl-card flex flex-col gap-4 p-4 sm:flex-row sm:items-center"
            >
                <img
                    v-if="sale.thumb_url"
                    :src="sale.thumb_url"
                    alt=""
                    loading="lazy"
                    class="size-20 rounded-xl object-cover"
                />
                <div class="min-w-0 flex-1">
                    <p class="font-medium">{{ sale.event }}</p>
                    <p class="text-xs text-muted-foreground">
                        {{ shortDate(sale.created_at) }} · el cliente pagó
                        {{ money(sale.gross_minor) }}
                        <template v-if="sale.payment_method_label">
                            · {{ sale.payment_method_label }}</template
                        >
                    </p>
                    <div class="mt-3 flex flex-wrap gap-2 text-xs">
                        <span
                            class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 font-semibold text-emerald-800"
                            ><Wallet class="size-3.5" /> Tú:
                            {{ money(sale.net_minor) }}</span
                        >
                        <span
                            class="inline-flex items-center gap-1 rounded-full bg-fl-cream px-2.5 py-1 text-fl-gold-ink"
                            ><Building2 class="size-3.5" /> Finisher
                            {{ sale.commission_percent }}%:
                            {{ money(sale.platform_fee_minor) }}</span
                        >
                        <span
                            class="inline-flex items-center gap-1 rounded-full bg-sky-50 px-2.5 py-1 text-sky-800"
                            ><CreditCard class="size-3.5" />
                            {{
                                sale.processor_fee_estimated
                                    ? 'Procesamiento (est.)'
                                    : 'Procesamiento'
                            }}: {{ money(sale.processor_fee_minor) }}</span
                        >
                    </div>
                </div>
                <span
                    class="inline-flex items-center gap-1 self-start rounded-full px-3 py-1 text-xs font-semibold sm:self-center"
                    :class="
                        sale.payout_status === 'paid'
                            ? 'bg-emerald-600 text-white'
                            : 'bg-amber-100 text-amber-800'
                    "
                >
                    <Landmark class="size-3.5" />
                    {{
                        sale.payout_status === 'paid'
                            ? 'Transferida'
                            : 'Por transferir'
                    }}
                </span>
            </article>
        </div>
        <p
            v-else
            class="fl-card p-12 text-center text-sm text-muted-foreground"
        >
            Aún no tienes ventas.
        </p>

        <Pagination :links="sales.links" />
    </div>
</template>
