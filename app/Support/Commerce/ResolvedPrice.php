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
        /** Price before an "oferta" was applied (null = no offer). */
        public readonly ?int $originalAmountMinor = null,
        public readonly ?int $promotionId = null,
        public readonly ?string $promotionLabel = null,
    ) {}

    public function withPromotion(int $discountedMinor, int $promotionId, string $label): self
    {
        return new self($discountedMinor, $this->currency, $this->priceType, $this->scheduleId, $this->isFallback, $this->amountMinor, $promotionId, $label);
    }
}
