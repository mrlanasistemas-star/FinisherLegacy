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
 * IMPORTANT: processor_fee is an ESTIMATE from the configured rate of the
 * gateway that processed the payment (`finisher.photos.processor_fees`),
 * never the bank's reconciled fee. PhotoSale stores it with
 * `processor_fee_estimated = true`; a real fee reported by the gateway goes
 * into `processor_fee_actual_minor` (see PhotoSale::reconcileProcessorFee),
 * which never rewrites the photographer's frozen net silently.
 *
 * A manual payment (cash/transfer registered by an admin) has no card
 * processor, so its processing fee is 0.
 *
 * All amounts are integer minor units (centavos).
 */
class PhotoFeeCalculator
{
    /**
     * @return array{gross_minor: int, processor_fee_minor: int, platform_fee_minor: int, photographer_net_minor: int, commission_percent: float, payment_provider: string, processor_fee_estimated: bool}
     */
    public function split(int $grossMinor, int $itemsInOrder = 1, ?string $provider = null): array
    {
        $config = config('finisher.photos');
        $provider = $this->provider($provider);
        $rate = $this->rate($provider);
        $commission = (float) $config['platform_commission_percent'];
        $itemsInOrder = max($itemsInOrder, 1);

        $processorBase = $grossMinor * ($rate['percent'] / 100)
            + ($rate['fixed_minor'] / $itemsInOrder);
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
            'payment_provider' => $provider,
            'processor_fee_estimated' => true,
        ];
    }

    /**
     * What the portal shows next to the price field — the estimate for
     * the web checkout's default gateway, labelled generically.
     *
     * @return array<string, float|int|string|bool>
     */
    public function rules(): array
    {
        $config = config('finisher.photos');
        $rate = $this->rate($this->provider(null));

        return [
            'platform_commission_percent' => (float) $config['platform_commission_percent'],
            'processor_fee_percent' => $rate['percent'],
            'processor_fee_fixed_minor' => $rate['fixed_minor'],
            'processor_fee_vat_percent' => (float) $config['processor_fee_vat_percent'],
            'processor_fee_estimated' => true,
            'min_price_minor' => (int) $config['min_price_minor'],
            'max_price_minor' => (int) $config['max_price_minor'],
            'default_price_minor' => (int) $config['default_price_minor'],
        ];
    }

    private function provider(?string $provider): string
    {
        return $provider ?? (string) config('finisher.payments.default_gateway', 'openpay');
    }

    /**
     * @return array{percent: float, fixed_minor: int}
     */
    private function rate(string $provider): array
    {
        $config = config('finisher.photos');
        $rate = $config['processor_fees'][$provider] ?? null;

        return [
            'percent' => (float) ($rate['percent'] ?? $config['processor_fee_percent']),
            'fixed_minor' => (int) ($rate['fixed_minor'] ?? $config['processor_fee_fixed_minor']),
        ];
    }
}
