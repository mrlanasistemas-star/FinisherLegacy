<script setup lang="ts">
/**
 * Live, transparent split of one photo sale — same formula as
 * App\Services\Photos\PhotoFeeCalculator (card processor fee with IVA,
 * Finisher Legacy commission, photographer net), drawn as a stacked bar.
 */
import { Building2, CreditCard, Wallet } from '@lucide/vue';
import { computed } from 'vue';
import { useI18n } from '@/i18n';
import { splitPhotoPrice } from '@/lib/photoFees';
import type { PhotoFeeRules } from '@/lib/photoFees';

const props = withDefaults(
    defineProps<{
        rules: PhotoFeeRules;
        priceMinor: number;
        compact?: boolean;
    }>(),
    { compact: false },
);

const { money } = useI18n();

const split = computed(() =>
    splitPhotoPrice(props.rules, Math.max(props.priceMinor || 0, 0)),
);

const pct = (part: number) =>
    split.value.gross > 0 ? `${(part / split.value.gross) * 100}%` : '0%';
</script>

<template>
    <div class="space-y-3">
        <div
            class="flex h-3 w-full overflow-hidden rounded-full bg-muted"
            role="img"
            :aria-label="
                $t('Recibes {net} de {gross}', {
                    net: money(split.net),
                    gross: money(split.gross),
                })
            "
        >
            <span class="bg-emerald-500" :style="{ width: pct(split.net) }" />
            <span class="bg-fl-gold" :style="{ width: pct(split.platform) }" />
            <span
                v-if="!split.finisherAbsorbs"
                class="bg-sky-500"
                :style="{ width: pct(split.processor) }"
            />
        </div>
        <dl
            class="grid gap-2"
            :class="compact ? 'grid-cols-1' : 'sm:grid-cols-3'"
        >
            <div
                class="flex items-center gap-2.5 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2.5"
            >
                <Wallet class="size-4 shrink-0 text-emerald-700" />
                <div class="min-w-0">
                    <dt class="text-[11px] text-emerald-800">
                        {{ $t('Tú recibes') }}
                    </dt>
                    <dd class="legacy-numeric font-semibold text-emerald-900">
                        {{ money(split.net) }}
                    </dd>
                </div>
            </div>
            <div
                class="flex items-center gap-2.5 rounded-xl border border-fl-gold/40 bg-fl-cream px-3 py-2.5"
            >
                <Building2 class="size-4 shrink-0 text-fl-gold-ink" />
                <div class="min-w-0">
                    <dt class="text-[11px] text-fl-gold-ink">
                        Finisher Legacy ({{
                            rules.platform_commission_percent
                        }}%)
                    </dt>
                    <dd class="legacy-numeric font-semibold">
                        {{ money(split.platform) }}
                    </dd>
                </div>
            </div>
            <div
                class="flex items-center gap-2.5 rounded-xl border border-sky-200 bg-sky-50 px-3 py-2.5"
            >
                <CreditCard class="size-4 shrink-0 text-sky-700" />
                <div class="min-w-0">
                    <dt class="text-[11px] text-sky-800">
                        {{
                            split.finisherAbsorbs
                                ? $t('Pasarela (la absorbe Finisher)')
                                : $t('Procesamiento de pago (est.)')
                        }}
                    </dt>
                    <dd class="legacy-numeric font-semibold text-sky-900">
                        {{ money(split.processor) }}
                    </dd>
                </div>
            </div>
        </dl>
        <p v-if="!compact" class="text-xs text-muted-foreground">
            {{
                $t(
                    'El procesamiento de pago es una estimación: {percent}% + {fixed} por pago, más IVA ({vat}%). El cargo real depende de la pasarela con la que pague el cliente (tarjeta o pago manual) y queda registrado en cada venta. El cargo fijo se reparte entre las fotos del mismo pedido. Publicar tus fotos no tiene costo inicial.',
                    {
                        percent: rules.processor_fee_percent,
                        fixed: money(rules.processor_fee_fixed_minor),
                        vat: rules.processor_fee_vat_percent,
                    },
                )
            }}
        </p>
    </div>
</template>
