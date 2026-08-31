<?php

namespace App\Models;

use App\Enums\InventoryMovementType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable audit-trail row (brief §52-§54) — `updated_at` doesn't exist on
 * this table on purpose. `quantity` is a signed delta appropriate to
 * `type`: Receive/Sale/Return/Adjustment affect InventoryLevel::
 * quantity_on_hand, Reserve/Release affect quantity_reserved — the two
 * balances on InventoryLevel are the authoritative current state, always
 * mutated under `lockForUpdate()` by App\Services\Commerce\InventoryService.
 */
#[Fillable([
    'product_variant_id', 'inventory_location_id', 'type', 'quantity',
    'reference_type', 'reference_id', 'actor_id', 'notes',
])]
class InventoryMovement extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'type' => InventoryMovementType::class,
            'quantity' => 'integer',
            'created_at' => 'datetime',
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

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
