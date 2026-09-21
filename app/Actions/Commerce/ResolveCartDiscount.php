<?php

namespace App\Actions\Commerce;

use App\Enums\CouponType;
use App\Models\Coupon;

/**
 * The only place a coupon's discount amount is computed from a subtotal
 * (brief §37) — always minor units, never a float, and never more than
 * the subtotal itself (brief §40: total can never go negative).
 */
class ResolveCartDiscount
{
    public function handle(?Coupon $coupon, int $subtotalMinor): int
    {
        if ($coupon === null) {
            return 0;
        }

        $discount = $coupon->type === CouponType::Percentage
            ? intdiv($subtotalMinor * $coupon->value, 100)
            : $coupon->value;

        return min($discount, $subtotalMinor);
    }
}
