<?php

namespace App\Actions\Commerce;

use App\Models\ProductVariant;
use App\Services\Commerce\InventoryService;

/**
 * The one place "can this actually be bought right now" is decided from
 * the checkout's point of view (consolidation brief §9-§11) — a variant
 * with stock at some other InventoryLocation but none at the location
 * CheckoutCart actually reserves against is NOT available, no matter what
 * ProductVariant::isAvailable()'s cross-location sum says. Store API, Web
 * Store, GetCartSummary, Checkout and ProductVariantResource all call
 * this instead of each re-deriving availability, so the UI never says
 * "Disponible" for something checkout is about to reject.
 */
class IsVariantAvailableForCheckout
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function handle(ProductVariant $variant): bool
    {
        $variant->loadMissing('product');

        if (! $variant->active) {
            return false;
        }

        if (! $variant->product->tracks_inventory) {
            return true;
        }

        $location = $this->inventory->defaultLocation();

        $level = $variant->relationLoaded('inventoryLevels')
            ? $variant->inventoryLevels->firstWhere('inventory_location_id', $location->id)
            : $variant->inventoryLevels()->where('inventory_location_id', $location->id)->first();

        return $level !== null && $level->availableQuantity() > 0;
    }
}
