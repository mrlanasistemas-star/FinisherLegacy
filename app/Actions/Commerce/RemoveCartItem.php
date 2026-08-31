<?php

namespace App\Actions\Commerce;

use App\Models\CartItem;

class RemoveCartItem
{
    public function handle(CartItem $item): void
    {
        $item->delete();
    }
}
