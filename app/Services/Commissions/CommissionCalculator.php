<?php

namespace App\Services\Commissions;

/**
 * Pure money split of one photo sale from a set of CommissionTerms, in
 * integer minor units (centavos). Mirrored for live previews by
 * resources/js/lib/photoFees.ts — keep both formulas identical.
 *
 *   gateway_fee  = round((gross × fee% + fixed ÷ items in order) × (1 + IVA%)), ≤ gross
 *   commission   = round(gross × commission%)
 *
 *   absorbed by photographer: commission ≤ gross − gateway_fee
 *                             net = gross − gateway_fee − commission
 *   absorbed by Finisher:     commission ≤ gross
 *                             net = gross − commission
 *                             (Finisher keeps commission − gateway_fee)
 *
 * round() is half away from zero, identical to JS Math.round for the
 * non-negative values used here. The net is never negative.
 */
class CommissionCalculator
{
    /**
     * @return array{gross_minor: int, processor_fee_minor: int, platform_fee_minor: int, photographer_net_minor: int, finisher_take_minor: int, commission_percent: float, commission_source: string, commission_setting_id: int|null, gateway_fee_absorbed_by: string, gateway_fee_configured: bool, payment_provider: string, processor_fee_estimated: bool}
     */
    public function split(int $grossMinor, CommissionTerms $terms, int $itemsInOrder = 1): array
    {
        $grossMinor = max($grossMinor, 0);
        $itemsInOrder = max($itemsInOrder, 1);

        $gatewayBase = $grossMinor * ($terms->gatewayFeePercent / 100)
            + ($terms->gatewayFeeFixedMinor / $itemsInOrder);
        $gateway = min((int) round($gatewayBase * (1 + $terms->gatewayFeeVatPercent / 100)), $grossMinor);
        $commission = (int) round($grossMinor * $terms->commissionPercent / 100);

        if ($terms->finisherAbsorbsGatewayFee()) {
            $commission = min($commission, $grossMinor);
            $net = $grossMinor - $commission;
            $finisherTake = $commission - $gateway;
        } else {
            $commission = min($commission, $grossMinor - $gateway);
            $net = $grossMinor - $gateway - $commission;
            $finisherTake = $commission;
        }

        return [
            'gross_minor' => $grossMinor,
            'processor_fee_minor' => $gateway,
            'platform_fee_minor' => $commission,
            'photographer_net_minor' => $net,
            'finisher_take_minor' => $finisherTake,
            'commission_percent' => $terms->commissionPercent,
            'commission_source' => $terms->source,
            'commission_setting_id' => $terms->settingId,
            'gateway_fee_absorbed_by' => $terms->gatewayFeeAbsorbedBy,
            'gateway_fee_configured' => $terms->gatewayFeeConfigured,
            'payment_provider' => $terms->provider,
            'processor_fee_estimated' => true,
        ];
    }
}
