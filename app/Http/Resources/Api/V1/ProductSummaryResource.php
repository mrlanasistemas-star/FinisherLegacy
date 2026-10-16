<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Product;
use App\Models\ProductMedia;
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
            // Additive (mobile store, spec §19/§23): "Producto conectado"
            // badge, editorial flags and the gallery in its one shape.
            'nfc_enabled' => (bool) $this->qr_capable,
            'is_featured' => (bool) $this->is_featured,
            'is_new' => $this->created_at !== null && $this->created_at->gt(now()->subDays(30)),
            'media' => $this->whenLoaded('media', fn () => $this->media->map(fn (ProductMedia $m) => $m->toMediaArray())->values()->all()),
        ];
    }
}
