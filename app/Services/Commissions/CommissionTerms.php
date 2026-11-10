<?php

namespace App\Services\Commissions;

use Carbon\CarbonInterface;

/**
 * The commission terms in force for one photographer at one instant, as
 * resolved by CommissionResolver: Finisher Legacy's commission %, where
 * it came from (env config, the global admin setting or the
 * photographer's override) and the gateway fee terms for the payment
 * provider (percent + fixed per order, plus IVA on the fee, and who
 * absorbs it). Immutable.
 */
final readonly class CommissionTerms
{
    public const string SOURCE_CONFIG = 'config';

    public const string SOURCE_GLOBAL = 'global';

    public const string SOURCE_OVERRIDE = 'override';

    public function __construct(
        public float $commissionPercent,
        public string $source,
        public ?int $settingId,
        public ?CarbonInterface $effectiveFrom,
        public string $provider,
        public float $gatewayFeePercent,
        public int $gatewayFeeFixedMinor,
        public float $gatewayFeeVatPercent,
        public bool $gatewayFeeConfigured,
        public string $gatewayFeeAbsorbedBy,
    ) {}

    public function finisherAbsorbsGatewayFee(): bool
    {
        return $this->gatewayFeeAbsorbedBy === 'finisher';
    }
}
