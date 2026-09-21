<?php

namespace App\Models;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Finisher Legacy's ecosystem catalog (brief §45-§48): Legacy Plate is the
 * entry point, not the whole product — Trisuit, FAST T1 Socks, Chill Band
 * and Racepack are first-class products too.
 */
#[Fillable([
    'uuid', 'name', 'slug', 'description', 'type', 'category_id', 'brand', 'image_path', 'status',
    'taxable', 'requires_shipping', 'qr_capable', 'tracks_inventory', 'active',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'status' => ProductStatus::class,
            'taxable' => 'boolean',
            'requires_shipping' => 'boolean',
            'qr_capable' => 'boolean',
            'tracks_inventory' => 'boolean',
            'active' => 'boolean',
        ];
    }

    /** @return BelongsTo<ProductCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class);
    }

    /** @return HasMany<ProductVariant, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /** @return HasMany<ProductPriceSchedule, $this> */
    public function priceSchedules(): HasMany
    {
        return $this->hasMany(ProductPriceSchedule::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** @return HasMany<ProductMedia, $this> */
    public function media(): HasMany
    {
        return $this->hasMany(ProductMedia::class)->orderBy('sort_order');
    }

    /** @return HasMany<ProductContentSection, $this> */
    public function contentSections(): HasMany
    {
        return $this->hasMany(ProductContentSection::class)->orderBy('sort_order');
    }
}
