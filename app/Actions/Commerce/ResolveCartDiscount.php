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
    /**
     * @param  int|null  $eligibleSubtotalMinor  For coupons scoped to selected products: the
     *                                           subtotal of just those lines (defaults to the whole cart).
     */
    public function handle(?Coupon $coupon, int $subtotalMinor, ?int $eligibleSubtotalMinor = null): int
    {
        if ($coupon === null) {
            return 0;
        }

        $base = min($eligibleSubtotalMinor ?? $subtotalMinor, $subtotalMinor);

        $discount = $coupon->type === CouponType::Percentage
            ? intdiv($base * $coupon->value, 100)
            : $coupon->value;

        return min($discount, $base);
    }
}
