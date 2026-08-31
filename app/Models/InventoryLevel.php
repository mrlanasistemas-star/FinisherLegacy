<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * `quantity_on_hand` / `quantity_reserved` are the current balances —
 * always mutated inside App\Services\Commerce\InventoryService under
 * `lockForUpdate()`, never assigned directly (brief §53-§54).
 */
#[Fillable(['product_variant_id', 'inventory_location_id', 'quantity_on_hand', 'quantity_reserved'])]
class InventoryLevel extends Model
{
    protected function casts(): array
    {
        return [
            'quantity_on_hand' => 'integer',
            'quantity_reserved' => 'integer',
        ];
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /** @return BelongsTo<InventoryLocation, $this> */
    public function inventoryLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class);
    }

    public function availableQuantity(): int
    {
        return $this->quantity_on_hand - $this->quantity_reserved;
    }
}
