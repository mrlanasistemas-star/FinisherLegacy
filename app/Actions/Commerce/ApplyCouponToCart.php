<?php

namespace App\Actions\Commerce;

use App\Models\Cart;
use App\Models\Coupon;
use App\Models\User;

/**
 * Cart-time validation only — a UX convenience so the shopper sees
 * "Cupón aplicado" / the specific rejection reason (brief §42) before
 * checkout. Never authoritative: CheckoutCart re-validates against a
 * locked Coupon row and is the only place a redemption is actually
 * recorded (brief §38-§39).
 */
class ApplyCouponToCart
{
    public function __construct(private readonly ValidateCoupon $validateCoupon) {}

    public function handle(Cart $cart, string $code, ?User $user): Coupon
    {
        $cart->loadMissing('items.productVariant');
        $subtotal = $cart->items->sum(fn ($item) => $item->productVariant->base_price_minor * $item->quantity);

        $coupon = $this->validateCoupon->findByCode($code);
        $this->validateCoupon->handle($coupon, $cart->currency, $subtotal, $user);

        $cart->update(['coupon_id' => $coupon->id]);

        return $coupon;
    }
}
