<script setup lang="ts">
/**
 * Live commission simulator for the photographer's price field — shown
 * BEFORE saving: "Precio al cliente → Comisión Finisher Legacy (Y%) →
 * Comisión de pasarela → Tú recibes", plus (optionally) a table of common
 * price points and the notice of the current rate and any scheduled
 * change. Same formula as App\Services\Commissions\CommissionCalculator
 * via @/lib/photoFees, so the preview equals what a sale would freeze.
 */
import { ArrowRight, CalendarClock, Info, TriangleAlert } from '@lucide/vue';
import { computed } from 'vue';
import I18nT from '@/components/i18n/I18nT.vue';
import { useI18n } from '@/i18n';
import { splitPhotoPrice } from '@/lib/photoFees';
import type { PhotoFeeRules } from '@/lib/photoFees';

const props = withDefaults(
    defineProps<{
        rules: PhotoFeeRules;
        priceMinor: number;
        pricePoints?: number[];
        showNotice?: boolean;
        showTable?: boolean;
        showLine?: boolean;
    }>(),
    {
        pricePoints: () => [],
        showNotice: false,
        showTable: false,
        showLine: true,
    },
);

const { t, regional, money } = useI18n();

const split = computed(() =>
    splitPhotoPrice(props.rules, Math.max(props.priceMinor || 0, 0)),
);
const rows = computed(() =>
    props.pricePoints.map((p) => splitPhotoPrice(props.rules, p)),
);

const longDate = (iso: string) =>
    new Date(iso).toLocaleDateString(regional.value, {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });

const rate = computed(() => props.rules.platform_commission_percent);

// One sentence per case ({rate} is filled by a slot): never glued pieces.
const currentRateText = computed(() => {
    const custom = props.rules.commission_source === 'override';
    const from = props.rules.commission_effective_from;

    if (from) {
        const date = longDate(from);

        return custom
            ? t(
                  'Comisión actual de Finisher Legacy: {rate} (tarifa personalizada para ti) · vigente desde el {date}.',
                  { date },
              )
            : t(
                  'Comisión actual de Finisher Legacy: {rate} · vigente desde el {date}.',
                  { date },
              );
    }

    return custom
        ? t(
              'Comisión actual de Finisher Legacy: {rate} (tarifa personalizada para ti).',
          )
        : t('Comisión actual de Finisher Legacy: {rate}.');
});
const gatewayConfigured = computed(
    () => props.rules.gateway_fee_configured !== false,
);
const finisherAbsorbs = computed(
    () => props.rules.gateway_fee_absorbed_by === 'finisher',
);
const belowMin = computed(
    () => (props.priceMinor || 0) < props.rules.min_price_minor,
);
</script>

<template>
    <div class="space-y-3">
        <!-- Current rate + scheduled change -->
        <div
            v-if="showNotice"
            class="fl-reveal flex gap-3 rounded-2xl border border-fl-gold/40 bg-fl-cream/70 p-4 text-sm"
        >
            <Info class="mt-0.5 size-4 shrink-0 text-fl-gold-ink" />
            <div class="space-y-1">
                <p>
                    <I18nT :text="currentRateText"
                        ><template #rate
                            ><strong class="legacy-numeric"
                                >{{ rate }}%</strong
                            ></template
                        ></I18nT
                    >
                </p>
                <p
                    v-if="rules.upcoming_change"
                    class="flex items-center gap-1.5 font-medium text-fl-gold-ink"
                >
                    <CalendarClock class="size-4 shrink-0" />
                    {{
                        $t('Cambia a {percent}% a partir del {date}.', {
                            percent: rules.upcoming_change.commission_percent,
                            date: longDate(
                                rules.upcoming_change.effective_from,
                            ),
                        })
                    }}
                </p>
                <p class="text-xs text-muted-foreground">
                    {{
                        finisherAbsorbs
                            ? $t(
                                  'La comisión de la pasarela de pago la absorbe Finisher Legacy.',
                              )
                            : $t(
                                  'La comisión de la pasarela de pago se descuenta de tu ganancia.',
                              )
                    }}
                    {{
                        $t(
                            'Cada venta conserva la comisión vigente el día en que se pagó.',
                        )
                    }}
                </p>
            </div>
        </div>

        <!-- Live line -->
        <div
            v-if="showLine"
            class="rounded-2xl border border-border bg-card p-4"
            aria-live="polite"
        >
            <p
                class="mb-2 text-[11px] font-semibold tracking-wide text-muted-foreground uppercase"
            >
                {{ $t('Simulación antes de guardar') }}
            </p>
            <ol class="flex flex-wrap items-center gap-x-2 gap-y-1.5 text-sm">
                <li>
                    <span class="text-muted-foreground">{{
                        $t('Precio al cliente')
                    }}</span>
                    <strong class="legacy-numeric ml-1">{{
                        money(split.gross)
                    }}</strong>
                </li>
                <ArrowRight class="size-3.5 text-muted-foreground" />
                <li>
                    <span class="text-muted-foreground">{{
                        $t('Comisión Finisher Legacy ({rate}%)', { rate })
                    }}</span>
                    <strong class="legacy-numeric ml-1 text-fl-gold-ink"
                        >−{{ money(split.platform) }}</strong
                    >
                </li>
                <ArrowRight class="size-3.5 text-muted-foreground" />
                <li>
                    <span class="text-muted-foreground">{{
                        $t('Comisión de pasarela')
                    }}</span>
                    <strong
                        v-if="!finisherAbsorbs"
                        class="legacy-numeric ml-1 text-sky-800"
                        >−{{ money(split.processor) }}</strong
                    >
                    <strong v-else class="ml-1 text-sky-800">{{
                        $t('{amount} (la absorbe Finisher)', {
                            amount: money(0),
                        })
                    }}</strong>
                </li>
                <ArrowRight class="size-3.5 text-muted-foreground" />
                <li
                    class="rounded-full bg-emerald-50 px-2.5 py-0.5 ring-1 ring-emerald-200"
                >
                    <span class="text-emerald-800">{{ $t('Tú recibes') }}</span>
                    <strong class="legacy-numeric ml-1 text-emerald-900">{{
                        money(split.net)
                    }}</strong>
                </li>
            </ol>
            <p
                v-if="!gatewayConfigured"
                class="mt-2 flex items-start gap-1.5 text-xs text-amber-800"
            >
                <TriangleAlert class="mt-0.5 size-3.5 shrink-0" />
                {{
                    $t(
                        'Comisión de pasarela no configurada: se muestra una estimación provisional ({percent}% + {fixed} + IVA {vat}%).',
                        {
                            percent: rules.processor_fee_percent,
                            fixed: money(rules.processor_fee_fixed_minor),
                            vat: rules.processor_fee_vat_percent,
                        },
                    )
                }}
            </p>
            <p v-if="belowMin" class="mt-2 text-xs text-red-700">
                {{
                    $t('El precio mínimo por foto es {price}.', {
                        price: money(rules.min_price_minor),
                    })
                }}
            </p>
        </div>

        <!-- Common price points -->
        <div
            v-if="showTable && rows.length"
            class="overflow-x-auto rounded-2xl border border-border"
        >
            <table class="w-full text-sm">
                <caption class="sr-only">
                    {{
                        $t('Lo que recibes en precios comunes')
                    }}
                </caption>
                <thead class="bg-muted/50 text-xs text-muted-foreground">
                    <tr>
                        <th class="px-3 py-2 text-left font-medium">
                            {{ $t('Precio') }}
                        </th>
                        <th class="px-3 py-2 text-right font-medium">
                            Finisher ({{ rate }}%)
                        </th>
                        <th class="px-3 py-2 text-right font-medium">
                            {{ $t('Pasarela') }}
                        </th>
                        <th class="px-3 py-2 text-right font-medium">
                            {{ $t('Tú recibes') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in rows"
                        :key="row.gross"
                        class="border-t border-border"
                    >
                        <td class="legacy-numeric px-3 py-2">
                            {{ money(row.gross) }}
                        </td>
                        <td class="legacy-numeric px-3 py-2 text-right">
                            −{{ money(row.platform) }}
                        </td>
                        <td class="legacy-numeric px-3 py-2 text-right">
                            {{
                                row.finisherAbsorbs
                                    ? '—'
                                    : `−${money(row.processor)}`
                            }}
                        </td>
                        <td
                            class="legacy-numeric px-3 py-2 text-right font-semibold text-emerald-800"
                        >
                            {{ money(row.net) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
