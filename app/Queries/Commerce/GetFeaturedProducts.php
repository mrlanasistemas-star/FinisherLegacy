<?php

namespace App\Queries\Commerce;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Collection;

/**
 * Active products for the Home "Tienda" teaser and the storefront catalog
 * — the same row shape (`summarize()`) both render via
 * resources/js/components/shared/ProductCard.vue, so the two views can
 * never drift (same pattern as App\Queries\Athletes\GetAthleteHistory::
 * summarize()). No `featured` column exists yet — ordered newest-first
 * rather than building a whole featured-flag system just for a teaser
 * (product consolidation brief item A5).
 */
class GetFeaturedProducts
{
    /**
     * @return Collection<int, Product>
     */
    public function handle(int $limit = 5): Collection
    {
        return Product::query()
            ->with(['category', 'variants.inventoryLevels', 'media'])
            ->where('active', true)
            ->where('status', 'active')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    public static function summarize(Product $product): array
    {
        // Each variant already belongs to this exact $product instance —
        // wiring the inverse relation in memory means
        // ProductVariant::isAvailable() never lazy-loads it per variant
        // (no N+1, consolidation brief §69).
        $product->variants->each(fn (ProductVariant $variant) => $variant->setRelation('product', $product));

        $firstVariant = $product->variants->first();
        $galleryImages = $product->relationLoaded('media')
            ? $product->media->where('type', 'image')->sortByDesc('is_primary')->values()
            : collect();
        $hoverImage = $galleryImages->get(1);

        return [
            'uuid' => $product->uuid,
            'name' => $product->name,
            'slug' => $product->slug,
            'type' => $product->type->value,
            'category' => $product->category?->name,
            'from_price_minor' => $product->variants->min('base_price_minor'),
            'currency' => $firstVariant !== null ? $firstVariant->currency : config('finisher.commerce.default_currency'),
            'in_stock' => $product->variants->contains(fn (ProductVariant $variant) => $variant->isAvailable()),
            'image_url' => $product->primaryImageUrl(),
            'hover_image_url' => $hoverImage?->url(),
            'variant_count' => $product->variants->count(),
        ];
    }
}
