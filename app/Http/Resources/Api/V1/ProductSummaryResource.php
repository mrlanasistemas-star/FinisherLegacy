<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ProductSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $activeVariants = $this->variants->where('active', true);
        $firstVariant = $activeVariants->first() ?? $this->variants->first();

        // Wire the inverse relation in memory so isAvailable() never
        // lazy-loads `product` per variant (no N+1).
        $activeVariants->each(fn (ProductVariant $variant) => $variant->setRelation('product', $this->resource));

        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'slug' => $this->slug,
            'type' => $this->type->value,
            'brand' => $this->brand,
            'category' => $this->category?->name,
            'category_slug' => $this->category?->slug,
            // Boolean only, never a stock count (brief §176-§177).
            'in_stock' => $activeVariants->contains(fn (ProductVariant $variant) => $variant->isAvailable()),
            'from_price_minor' => $activeVariants->min('base_price_minor') ?? $this->variants->min('base_price_minor'),
            'currency' => $firstVariant !== null ? $firstVariant->currency : config('finisher.commerce.default_currency'),
            'image_url' => $this->primaryImageUrl(),
        ];
    }
}
