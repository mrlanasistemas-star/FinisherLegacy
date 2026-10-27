<?php

namespace App\Models;

use App\Enums\InventoryLotStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One manufacturing batch of finished tags for a SKU — enough for
 * traceability (supplier, cost, dates, issues), not an ERP purchase order.
 */
#[Fillable([
    'uuid', 'code', 'product_variant_id', 'supplier_id', 'inventory_location_id', 'quantity', 'quantity_received',
    'unit_cost_minor', 'currency', 'status', 'ordered_at', 'received_at', 'reference', 'issues', 'notes', 'created_by_user_id',
])]
class InventoryLot extends Model
{
    protected function casts(): array
    {
        return [
            'status' => InventoryLotStatus::class,
            'quantity' => 'integer',
            'quantity_received' => 'integer',
            'unit_cost_minor' => 'integer',
            'ordered_at' => 'date',
            'received_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<InventoryLocation, $this> */
    public function inventoryLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class);
    }

    /** @return HasMany<LegacyTag, $this> */
    public function tags(): HasMany
    {
        return $this->hasMany(LegacyTag::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
