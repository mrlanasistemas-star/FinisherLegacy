<?php

namespace App\Actions\Commerce;

use App\Enums\ProductType;
use App\Exceptions\LegacyPlateQuantityInvalidException;
use App\Exceptions\ProductOutOfStockException;
use App\Models\CartItem;

/**
 * Quantity change of a cart line (0 removes it). Raising the quantity is
 * checked against what checkout could actually reserve, so the cart never
 * promises units that CheckoutCart will reject — the reservation itself
 * still happens (under lock) only at checkout.
 */
class UpdateCartItem
{
    public function __construct(private readonly IsVariantAvailableForCheckout $availability) {}

    public function handle(CartItem $item, int $quantity): ?CartItem
    {
        if ($quantity <= 0) {
            $item->delete();

            return null;
        }

        $item->loadMissing('productVariant.product');
        $variant = $item->productVariant;

        if ($variant->product->type === ProductType::LegacyPlate && $quantity !== 1) {
            throw new LegacyPlateQuantityInvalidException;
        }

        if ($quantity > $item->quantity) {
            $available = $this->availability->availableQuantity($variant);

            if ($available !== null && $quantity > $available) {
                throw new ProductOutOfStockException;
            }
        }

        $item->update(['quantity' => $quantity]);

        return $item->fresh();
    }
}
