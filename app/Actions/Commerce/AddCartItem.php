<?php

namespace App\Actions\Commerce;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\EventEdition;
use App\Models\ProductVariant;

class AddCartItem
{
    /**
     * @param  array<string, mixed>  $metadata  For a Legacy Plate line, carries `legacy_plate_model_id`.
     */
    public function handle(Cart $cart, ProductVariant $variant, int $quantity = 1, ?EventEdition $eventEdition = null, array $metadata = []): CartItem
    {
        $item = $cart->items()
            ->where('product_variant_id', $variant->id)
            ->where('event_edition_id', $eventEdition?->id)
            ->first();

        if ($item !== null) {
            $item->update(['quantity' => $item->quantity + $quantity]);

            return $item->fresh();
        }

        return $cart->items()->create([
            'product_variant_id' => $variant->id,
            'quantity' => $quantity,
            'event_edition_id' => $eventEdition?->id,
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
    }
}
