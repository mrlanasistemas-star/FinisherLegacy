<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Full commercial snapshot at purchase time — never re-reads current
 * Product/ProductVariant pricing (brief §58).
 */
#[Fillable([
    'uuid', 'order_id', 'product_id', 'product_variant_id', 'name', 'sku',
    'quantity', 'unit_price_minor', 'line_total_minor', 'currency', 'metadata', 'fulfilled_at',
])]
class OrderItem extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price_minor' => 'integer',
            'line_total_minor' => 'integer',
            'metadata' => 'array',
            'fulfilled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /** @return HasOne<LegacyPlateEntitlement, $this> */
    public function legacyPlateEntitlement(): HasOne
    {
        return $this->hasOne(LegacyPlateEntitlement::class);
    }

    /** @return HasOne<AthleteOwnedProduct, $this> */
    public function ownedProduct(): HasOne
    {
        return $this->hasOne(AthleteOwnedProduct::class);
    }

    public function isFulfilled(): bool
    {
        return $this->fulfilled_at !== null;
    }
}
