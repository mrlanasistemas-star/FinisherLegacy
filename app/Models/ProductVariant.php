<?php

namespace App\Models;

use App\Actions\Commerce\IsVariantAvailableForCheckout;
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
    'currency', 'cost_minor', 'weight_grams', 'active', 'minimum_stock',
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
            'minimum_stock' => 'integer',
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

    /**
     * "Can this actually be bought right now" — delegates to
     * App\Actions\Commerce\IsVariantAvailableForCheckout, the single
     * source of truth for availability against the exact
     * InventoryLocation CheckoutCart reserves from (consolidation brief
     * §9-§11). Never exposes exact stock counts (brief §176-§177), just
     * this boolean. A thin proxy, not business logic — kept as a model
     * method purely for call-site convenience (`$variant->isAvailable()`
     * reads better than resolving the Action everywhere).
     */
    public function isAvailable(): bool
    {
        return app(IsVariantAvailableForCheckout::class)->handle($this);
    }

    /**
     * Restock threshold (spec §15): the variant's own value, else the
     * product's, else `finisher.commerce.default_minimum_stock`.
     * Expects `product` loaded when called in a loop.
     */
    public function minimumStock(): int
    {
        return (int) ($this->minimum_stock
            ?? $this->product->minimum_stock
            ?? config('finisher.commerce.default_minimum_stock', 5));
    }

    /**
     * Size / color for the inventory matrix and cart — read from the free
     * JSON `attributes` (brief §50), accepting the Spanish keys too.
     *
     * @return array{size: ?string, color: ?string}
     */
    public function sizeAndColor(): array
    {
        $attrs = is_array($this->getAttribute('attributes')) ? $this->getAttribute('attributes') : [];
        $pick = function (array $keys) use ($attrs): ?string {
            foreach ($keys as $key) {
                if (filled($attrs[$key] ?? null) && is_scalar($attrs[$key])) {
                    return (string) $attrs[$key];
                }
            }

            return null;
        };

        return [
            'size' => $pick(['size', 'talla', 'Talla', 'Size']),
            'color' => $pick(['color', 'Color', 'colour']),
        ];
    }
}
