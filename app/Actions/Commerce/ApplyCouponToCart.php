<?php

namespace App\Actions\Commerce;

use App\Models\Cart;
use App\Models\Coupon;
use App\Models\User;
use App\Queries\Commerce\GetCartSummary;

/**
 * Cart-time validation only — a UX convenience so the shopper sees
 * "Cupón aplicado" / the specific rejection reason (brief §42) before
 * checkout. Never authoritative: CheckoutCart re-validates against a
 * locked Coupon row and is the only place a redemption is actually
 * recorded (brief §38-§39).
 */
class ApplyCouponToCart
{
    public function __construct(
        private readonly ValidateCoupon $validateCoupon,
        private readonly GetCartSummary $getCartSummary,
    ) {}

    public function handle(Cart $cart, string $code, ?User $user): Coupon
    {
        // Same resolved-price subtotal CheckoutCart will actually charge
        // (brief item 27/108) — a raw base_price_minor sum here could
        // pass the minimum-order check on a subtotal the real checkout
        // never sees.
        $subtotal = $this->getCartSummary->handle($cart)->subtotalMinor;

        $coupon = $this->validateCoupon->findByCode($code);
        $this->validateCoupon->handle($coupon, $cart->currency, $subtotal, $user);

        $cart->update(['coupon_id' => $coupon->id]);

        return $coupon;
    }
}
