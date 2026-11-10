<?php

namespace App\Enums;

use Carbon\CarbonInterface;

/**
 * How an expansion pack is billed (docs/renovacion/cuotas-y-precios.md).
 * `one_time` capacity is permanent; the others are PREPAID terms — paid
 * once for N months and renewed by paying again (no card is stored, no
 * automatic charge). Add a case + its months() to sell another period.
 */
enum BillingPeriod: string
{
    case OneTime = 'one_time';
    case Semiannual = 'semiannual';
    case Annual = 'annual';

    /** Months a payment covers; null = forever. */
    public function months(): ?int
    {
        return match ($this) {
            self::OneTime => null,
            self::Semiannual => 6,
            self::Annual => 12,
        };
    }

    public function isTerm(): bool
    {
        return $this->months() !== null;
    }

    public function label(): string
    {
        return match ($this) {
            self::OneTime => __('Pago único'),
            self::Semiannual => __('Semestral (6 meses)'),
            self::Annual => __('Anual (12 meses)'),
        };
    }

    /** Shown next to the price before paying: "$X por 12 meses" / "$X · pago único". */
    public function priceSuffix(): string
    {
        $months = $this->months();

        return $months === null ? __('pago único') : __('por :months meses', ['months' => $months]);
    }

    public function expiresAt(CarbonInterface $startsAt): ?CarbonInterface
    {
        $months = $this->months();

        return $months === null ? null : $startsAt->copy()->addMonthsNoOverflow($months);
    }
}
