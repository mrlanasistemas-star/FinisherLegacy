<?php

namespace App\Support\Commerce;

use App\Models\Coupon;
use Illuminate\Support\Collection;

final class CartSummary
{
    /**
     * @param  Collection<int, CartSummaryItem>  $items
     */
    public function __construct(
        public readonly Collection $items,
        public readonly int $subtotalMinor,
        public readonly int $discountMinor,
        public readonly int $totalMinor,
        public readonly string $currency,
        public readonly ?Coupon $coupon,
    ) {}
}
