<?php

namespace App\Actions\Commerce;

use App\Models\CartItem;

class UpdateCartItem
{
    public function handle(CartItem $item, int $quantity): ?CartItem
    {
        if ($quantity <= 0) {
            $item->delete();

            return null;
        }

        $item->update(['quantity' => $quantity]);

        return $item->fresh();
    }
}
