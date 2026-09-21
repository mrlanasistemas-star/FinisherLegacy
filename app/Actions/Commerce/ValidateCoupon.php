<?php

namespace App\Actions\Commerce;

use App\Enums\CouponRejectionReason;
use App\Exceptions\CouponNotApplicableException;
use App\Models\Coupon;
use App\Models\User;

/**
 * The one place a coupon's eligibility is decided (brief §35: "Validar
 * server-side... Nunca frontend only") — used both for the cart-time
 * apply (UX feedback) and again inside CheckoutCart's transaction (the
 * authoritative check, run against a locked row so a concurrent checkout
 * can't double-spend the last use, brief §39).
 */
class ValidateCoupon
{
    public function findByCode(string $code): Coupon
    {
        $coupon = Coupon::query()->where('code', mb_strtoupper(trim($code)))->first();

        if ($coupon === null) {
            throw new CouponNotApplicableException(CouponRejectionReason::NotFound);
        }

        return $coupon;
    }

    public function handle(Coupon $coupon, string $currency, int $subtotalMinor, ?User $user): void
    {
        if (! $coupon->active) {
            throw new CouponNotApplicableException(CouponRejectionReason::Inactive);
        }

        $now = now();

        if ($coupon->starts_at !== null && $now->lt($coupon->starts_at)) {
            throw new CouponNotApplicableException(CouponRejectionReason::NotStarted);
        }

        if ($coupon->ends_at !== null && $now->gt($coupon->ends_at)) {
            throw new CouponNotApplicableException(CouponRejectionReason::Expired);
        }

        if ($coupon->currency !== null && $coupon->currency !== $currency) {
            throw new CouponNotApplicableException(CouponRejectionReason::CurrencyMismatch);
        }

        if ($coupon->minimum_order_minor !== null && $subtotalMinor < $coupon->minimum_order_minor) {
            throw new CouponNotApplicableException(CouponRejectionReason::MinimumOrderNotMet);
        }

        if ($coupon->usage_limit_total !== null && $coupon->redemptions()->count() >= $coupon->usage_limit_total) {
            throw new CouponNotApplicableException(CouponRejectionReason::UsageLimitReached);
        }

        if ($user !== null && $coupon->usage_limit_per_user !== null) {
            $userRedemptions = $coupon->redemptions()->where('user_id', $user->id)->count();

            if ($userRedemptions >= $coupon->usage_limit_per_user) {
                throw new CouponNotApplicableException(CouponRejectionReason::PerUserLimitReached);
            }
        }
    }
}
