<?php

namespace App\Support\Commerce;

use App\Enums\ProductPriceType;

final class ResolvedPrice
{
    public function __construct(
        public readonly int $amountMinor,
        public readonly string $currency,
        public readonly ProductPriceType $priceType,
        public readonly ?int $scheduleId,
        /** True when no ProductPriceSchedule applied and this is the variant's plain base_price_minor. */
        public readonly bool $isFallback,
    ) {}
}
