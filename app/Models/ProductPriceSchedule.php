<?php

namespace App\Models;

use App\Enums\ProductPriceType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One priced window for a product/variant, optionally scoped to an event —
 * resolved by App\Actions\Commerce\ResolveProductPrice (brief §40-§44).
 * Rows with photo_expansion_pack_id price a Legacy story photo expansion
 * (PhotoExpansionPack::priceAt) and are never edited once they started —
 * a new price is always a new row.
 */
#[Fillable([
    'product_id', 'product_variant_id', 'event_edition_id', 'price_type',
    'amount_minor', 'currency', 'starts_at', 'ends_at', 'active', 'sales_channel', 'ally_id',
    'photo_expansion_pack_id', 'tax_rate', 'tax_included', 'created_by',
])]
class ProductPriceSchedule extends Model
{
    protected function casts(): array
    {
        return [
            'price_type' => ProductPriceType::class,
            'amount_minor' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'active' => 'boolean',
            'tax_rate' => 'decimal:4',
            'tax_included' => 'boolean',
        ];
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

    /** @return BelongsTo<PhotoExpansionPack, $this> */
    public function photoExpansionPack(): BelongsTo
    {
        return $this->belongsTo(PhotoExpansionPack::class);
    }

    /** @return BelongsTo<EventEdition, $this> */
    public function eventEdition(): BelongsTo
    {
        return $this->belongsTo(EventEdition::class);
    }

    public function isActiveAt(\DateTimeInterface $at): bool
    {
        if (! $this->active) {
            return false;
        }

        if ($this->starts_at !== null && $at < $this->starts_at) {
            return false;
        }

        if ($this->ends_at !== null && $at > $this->ends_at) {
            return false;
        }

        return true;
    }
}
