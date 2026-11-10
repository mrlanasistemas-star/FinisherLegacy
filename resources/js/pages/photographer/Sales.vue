<script setup lang="ts">
/**
 * Portal del fotógrafo — every sale with its exact split (frozen at
 * payment time in PhotoSale) and whether it was already transferred.
 */
import { Head } from '@inertiajs/vue3';
import { Building2, CreditCard, Landmark, Receipt, Wallet } from '@lucide/vue';
import Pagination from '@/components/public/Pagination.vue';
import { useI18n } from '@/i18n';
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
    gateway_fee_absorbed_by?: 'photographer' | 'finisher';
    refunded_at?: string | null;
    reversed_net_minor?: number | null;
    created_at: string;
};

const { t, regional } = useI18n();

function payoutLabel(sale: Sale): { label: string; class: string } {
    if (sale.refunded_at) {
        return sale.payout_status === 'paid'
            ? {
                  label: t('Reembolsada · se descuenta del próximo pago'),
                  class: 'bg-red-100 text-red-800',
              }
            : { label: t('Reembolsada'), class: 'bg-red-100 text-red-800' };
    }

    return sale.payout_status === 'paid'
        ? { label: t('Transferida'), class: 'bg-emerald-600 text-white' }
        : { label: t('Por transferir'), class: 'bg-amber-100 text-amber-800' };
}

defineProps<{
    sales: {
        data: Sale[];
        links: { url: string | null; label: string; active: boolean }[];
    };
}>();

const money = (minor: number) =>
    new Intl.NumberFormat(regional.value, {
        style: 'currency',
        currency: 'MXN',
    }).format(minor / 100);
</script>

<template>
    <Head :title="$t('Ventas y pagos')" />

    <div class="w-full space-y-6 px-4 py-6 md:px-6 md:py-8 xl:px-8">
        <div class="flex items-center gap-3">
            <span
                class="flex size-11 items-center justify-center rounded-xl bg-fl-cream text-fl-gold-ink"
            >
                <Receipt class="size-5" />
            </span>
            <div>
                <h1 class="text-xl font-semibold">
                    {{ $t('Ventas y pagos') }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{
                        $t('Cada foto vendida, con lo que se quedó cada parte.')
                    }}
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
                        {{ shortDate(sale.created_at) }} ·
                        {{
                            $t('el cliente pagó {amount}', {
                                amount: money(sale.gross_minor),
                            })
                        }}
                        <template v-if="sale.payment_method_label">
                            · {{ sale.payment_method_label }}</template
                        >
                    </p>
                    <div class="mt-3 flex flex-wrap gap-2 text-xs">
                        <span
                            class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 font-semibold text-emerald-800"
                            ><Wallet class="size-3.5" />
                            {{
                                $t('Tú: {amount}', {
                                    amount: money(sale.net_minor),
                                })
                            }}</span
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
                                    ? $t('Procesamiento (est.): {amount}', {
                                          amount: money(
                                              sale.processor_fee_minor,
                                          ),
                                      })
                                    : $t('Procesamiento: {amount}', {
                                          amount: money(
                                              sale.processor_fee_minor,
                                          ),
                                      })
                            }}
                            <template
                                v-if="
                                    sale.gateway_fee_absorbed_by === 'finisher'
                                "
                            >
                                {{ $t('(lo absorbe Finisher)') }}</template
                            ></span
                        >
                    </div>
                </div>
                <span
                    class="inline-flex items-center gap-1 self-start rounded-full px-3 py-1 text-xs font-semibold sm:self-center"
                    :class="payoutLabel(sale).class"
                >
                    <Landmark class="size-3.5" />
                    {{ payoutLabel(sale).label }}
                </span>
            </article>
        </div>
        <p
            v-else
            class="fl-card p-12 text-center text-sm text-muted-foreground"
        >
            {{ $t('Aún no tienes ventas.') }}
        </p>

        <Pagination :links="sales.links" />
    </div>
</template>
