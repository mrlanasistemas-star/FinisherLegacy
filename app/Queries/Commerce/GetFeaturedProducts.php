<?php

namespace App\Queries\Commerce;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;

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
        $firstVariant = $product->variants->first();
        $galleryImages = $product->relationLoaded('media')
            ? $product->media->where('type', 'image')->sortByDesc('is_primary')->values()
            : collect();
        $primaryImage = $galleryImages->first();
        $hoverImage = $galleryImages->get(1);

        return [
            'uuid' => $product->uuid,
            'name' => $product->name,
            'slug' => $product->slug,
            'type' => $product->type->value,
            'category' => $product->category?->name,
            'from_price_minor' => $product->variants->min('base_price_minor'),
            'currency' => $firstVariant !== null ? $firstVariant->currency : config('finisher.commerce.default_currency'),
            'in_stock' => ! $product->tracks_inventory || $product->variants->contains(
                fn (ProductVariant $variant) => $variant->inventoryLevels->sum(fn ($l) => $l->availableQuantity()) > 0,
            ),
            // The gallery's primary image wins over the legacy image_path
            // when both exist — brief §107 keeps image_path as a fallback,
            // not the source of truth once a gallery is configured.
            'image_url' => $primaryImage?->url() ?? ($product->image_path ? Storage::disk('public')->url($product->image_path) : null),
            'hover_image_url' => $hoverImage?->url(),
            'variant_count' => $product->variants->count(),
        ];
    }
}
