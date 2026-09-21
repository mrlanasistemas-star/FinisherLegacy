<?php

namespace App\Actions\Commerce;

use App\Models\Cart;

class RemoveCouponFromCart
{
    public function handle(Cart $cart): void
    {
        $cart->update(['coupon_id' => null]);
    }
}
