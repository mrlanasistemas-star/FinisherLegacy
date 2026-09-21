<?php

namespace App\Enums;

/**
 * Phase 1 only has two coupon shapes (brief §32) — no BOGO, no free
 * shipping, no rule engine. `value` on a Coupon is a whole percentage
 * (1-100) for Percentage, or minor currency units for FixedAmount — never
 * a float.
 */
enum CouponType: string
{
    case Percentage = 'percentage';
    case FixedAmount = 'fixed_amount';
}
