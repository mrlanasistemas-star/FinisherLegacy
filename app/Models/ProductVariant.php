<?php

namespace App\Models;

use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * `attributes` (size, color, ...) is free-form JSON — brief §50: no
 * dedicated column per attribute. `base_price_minor` is the fallback
 * App\Actions\Commerce\ResolveProductPrice uses when no
 * ProductPriceSchedule applies.
 */
#[Fillable([
    'uuid', 'product_id', 'sku', 'name', 'attributes', 'base_price_minor',
    'currency', 'cost_minor', 'weight_grams', 'active',
])]
class ProductVariant extends Model
{
    /** @use HasFactory<ProductVariantFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'attributes' => 'array',
            'base_price_minor' => 'integer',
            'cost_minor' => 'integer',
            'weight_grams' => 'integer',
            'active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return HasMany<InventoryLevel, $this> */
    public function inventoryLevels(): HasMany
    {
        return $this->hasMany(InventoryLevel::class);
    }

    /** @return HasMany<InventoryMovement, $this> */
    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    /** @return HasMany<ProductPriceSchedule, $this> */
    public function priceSchedules(): HasMany
    {
        return $this->hasMany(ProductPriceSchedule::class);
    }
}
