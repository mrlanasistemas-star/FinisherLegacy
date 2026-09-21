<?php

namespace App\Enums;

/**
 * Why a coupon code was rejected — kept distinct from a bare "invalid"
 * message so the cart UI can show the specific feedback the brief asks
 * for (§42: "Cupón no válido / Expirado / Límite alcanzado / No aplica
 * por monto mínimo"), not a single generic error.
 */
enum CouponRejectionReason: string
{
    case NotFound = 'not_found';
    case Inactive = 'inactive';
    case NotStarted = 'not_started';
    case Expired = 'expired';
    case UsageLimitReached = 'usage_limit_reached';
    case PerUserLimitReached = 'per_user_limit_reached';
    case MinimumOrderNotMet = 'minimum_order_not_met';
    case CurrencyMismatch = 'currency_mismatch';
}
