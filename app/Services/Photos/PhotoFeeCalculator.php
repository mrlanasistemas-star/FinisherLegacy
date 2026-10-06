<?php

namespace App\Services\Photos;

/**
 * The one place the photo sale split is computed — used for the
 * photographer's live calculator, the photo checkout and the PhotoSale
 * ledger, so the numbers a photographer sees are the numbers recorded.
 *
 *   gross               = price the photographer set
 *   processor_fee       = (gross × processor% + fixed fee ÷ items in order) × (1 + IVA%)
 *   platform_fee        = gross × Finisher commission%
 *   photographer_net    = gross − processor_fee − platform_fee  (never < 0)
 *
 * All amounts are integer minor units (centavos).
 */
class PhotoFeeCalculator
{
    /**
     * @return array{gross_minor: int, processor_fee_minor: int, platform_fee_minor: int, photographer_net_minor: int, commission_percent: float}
     */
    public function split(int $grossMinor, int $itemsInOrder = 1): array
    {
        $config = config('finisher.photos');
        $commission = (float) $config['platform_commission_percent'];
        $itemsInOrder = max($itemsInOrder, 1);

        $processorBase = $grossMinor * ((float) $config['processor_fee_percent'] / 100)
            + ((int) $config['processor_fee_fixed_minor'] / $itemsInOrder);
        $processor = (int) round($processorBase * (1 + (float) $config['processor_fee_vat_percent'] / 100));
        $platform = (int) round($grossMinor * $commission / 100);

        $processor = min($processor, $grossMinor);
        $platform = min($platform, $grossMinor - $processor);

        return [
            'gross_minor' => $grossMinor,
            'processor_fee_minor' => $processor,
            'platform_fee_minor' => $platform,
            'photographer_net_minor' => $grossMinor - $processor - $platform,
            'commission_percent' => $commission,
        ];
    }

    /**
     * What the portal shows next to the price field.
     *
     * @return array<string, float|int>
     */
    public function rules(): array
    {
        $config = config('finisher.photos');

        return [
            'platform_commission_percent' => (float) $config['platform_commission_percent'],
            'processor_fee_percent' => (float) $config['processor_fee_percent'],
            'processor_fee_fixed_minor' => (int) $config['processor_fee_fixed_minor'],
            'processor_fee_vat_percent' => (float) $config['processor_fee_vat_percent'],
            'min_price_minor' => (int) $config['min_price_minor'],
            'max_price_minor' => (int) $config['max_price_minor'],
            'default_price_minor' => (int) $config['default_price_minor'],
        ];
    }
}
