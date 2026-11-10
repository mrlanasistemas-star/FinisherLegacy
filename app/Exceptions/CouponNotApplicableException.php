<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use App\Enums\CouponRejectionReason;
use App\Exceptions\Api\ApiException;

/**
 * A coupon code was resolved but failed validation — never trust the
 * frontend's "this coupon is valid" state, both ApplyCouponToCart and
 * CheckoutCart re-run App\Actions\Commerce\ValidateCoupon (brief §35/§38).
 * `reason` drives the specific cart-facing copy (brief §42) instead of a
 * single generic "invalid coupon" message.
 */
class CouponNotApplicableException extends ApiException
{
    public function __construct(public readonly CouponRejectionReason $reason)
    {
        parent::__construct(self::messageFor($reason), ApiErrorCode::CouponNotApplicable, 422, ['reason' => $reason->value]);
    }

    private static function messageFor(CouponRejectionReason $reason): string
    {
        return match ($reason) {
            CouponRejectionReason::NotFound => __('Este código de cupón no es válido.'),
            CouponRejectionReason::Inactive => __('Este cupón ya no está activo.'),
            CouponRejectionReason::NotStarted => __('Este cupón todavía no está disponible.'),
            CouponRejectionReason::Expired => __('Este cupón ya expiró.'),
            CouponRejectionReason::UsageLimitReached => __('Este cupón alcanzó su límite de usos.'),
            CouponRejectionReason::PerUserLimitReached => __('Ya usaste este cupón el máximo de veces permitido.'),
            CouponRejectionReason::MinimumOrderNotMet => __('Tu compra no alcanza el monto mínimo para este cupón.'),
            CouponRejectionReason::CurrencyMismatch => __('Este cupón no aplica para esta moneda.'),
            CouponRejectionReason::NoEligibleProducts => __('Este cupón solo aplica a productos que no están en tu carrito.'),
        };
    }
}
