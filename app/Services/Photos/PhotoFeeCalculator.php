<?php

namespace App\Services\Photos;

use App\Models\PhotographerProfile;
use App\Services\Commissions\CommissionCalculator;
use App\Services\Commissions\CommissionResolver;
use App\Services\Commissions\CommissionTerms;
use Carbon\CarbonInterface;

/**
 * The one place the photo sale split is requested — used for the
 * photographer's live simulator, the photo checkout and the PhotoSale
 * ledger, so the numbers a photographer sees are the numbers recorded.
 *
 * The commission % now comes from the admin-editable terms
 * (App\Services\Commissions\CommissionResolver: photographer override →
 * global setting → env config) and the formula lives in
 * App\Services\Commissions\CommissionCalculator:
 *
 *   gross               = price the photographer set
 *   processor_fee       = (gross × gateway% + fixed fee ÷ items in order) × (1 + IVA%)
 *   platform_fee        = gross × Finisher commission%
 *   photographer_net    = gross − processor_fee − platform_fee   (photographer absorbs the gateway fee)
 *                       = gross − platform_fee                   (Finisher absorbs it)
 *
 * IMPORTANT: processor_fee is an ESTIMATE from the configured rate of the
 * gateway that processed the payment, never the bank's reconciled fee.
 * PhotoSale stores it with `processor_fee_estimated = true`; a real fee
 * reported by the gateway goes into `processor_fee_actual_minor` (see
 * PhotoSale::reconcileProcessorFee), which never rewrites the
 * photographer's frozen net silently.
 *
 * A manual payment (cash/transfer registered by an admin) has no card
 * processor, so its processing fee is 0.
 *
 * All amounts are integer minor units (centavos).
 */
class PhotoFeeCalculator
{
    public function __construct(
        private readonly CommissionResolver $resolver,
        private readonly CommissionCalculator $calculator,
    ) {}

    /**
     * @return array{gross_minor: int, processor_fee_minor: int, platform_fee_minor: int, photographer_net_minor: int, finisher_take_minor: int, commission_percent: float, commission_source: string, commission_setting_id: int|null, gateway_fee_absorbed_by: string, gateway_fee_configured: bool, payment_provider: string, processor_fee_estimated: bool}
     */
    public function split(
        int $grossMinor,
        int $itemsInOrder = 1,
        ?string $provider = null,
        PhotographerProfile|int|null $profile = null,
        ?CarbonInterface $at = null,
    ): array {
        return $this->calculator->split($grossMinor, $this->resolver->termsFor($profile, $at, $provider), $itemsInOrder);
    }

    public function terms(PhotographerProfile|int|null $profile = null, ?string $provider = null): CommissionTerms
    {
        return $this->resolver->termsFor($profile, null, $provider);
    }

    /**
     * What the portal shows next to the price field — the estimate for
     * the web checkout's default gateway, for this photographer (their
     * override, if any) — plus the next scheduled change of their rate.
     *
     * @return array{platform_commission_percent: float, commission_source: string, commission_effective_from: string|null, processor_fee_percent: float, processor_fee_fixed_minor: int, processor_fee_vat_percent: float, processor_fee_estimated: bool, gateway_fee_configured: bool, gateway_fee_absorbed_by: string, upcoming_change: array{effective_from: string, commission_percent: float, source: string}|null, min_price_minor: int, max_price_minor: int, default_price_minor: int}
     */
    public function rules(PhotographerProfile|int|null $profile = null): array
    {
        $config = config('finisher.photos');
        $terms = $this->resolver->termsFor($profile);

        return [
            'platform_commission_percent' => $terms->commissionPercent,
            'commission_source' => $terms->source,
            'commission_effective_from' => $terms->effectiveFrom?->toIso8601String(),
            'processor_fee_percent' => $terms->gatewayFeePercent,
            'processor_fee_fixed_minor' => $terms->gatewayFeeFixedMinor,
            'processor_fee_vat_percent' => $terms->gatewayFeeVatPercent,
            'processor_fee_estimated' => true,
            'gateway_fee_configured' => $terms->gatewayFeeConfigured,
            'gateway_fee_absorbed_by' => $terms->gatewayFeeAbsorbedBy,
            'upcoming_change' => $this->resolver->upcomingFor($profile),
            'min_price_minor' => (int) $config['min_price_minor'],
            'max_price_minor' => (int) $config['max_price_minor'],
            'default_price_minor' => (int) $config['default_price_minor'],
        ];
    }
}
