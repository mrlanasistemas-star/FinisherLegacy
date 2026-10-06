<?php

namespace App\Queries\Commerce;

use App\Enums\ProductAvailability;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Commerce\PromotionResolver;
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
            ->orderBy('sort_order')
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
        // An image explicitly marked as hover wins; otherwise the second
        // gallery image (admin-managed either way, never hardcoded).
        $hoverImage = $galleryImages->first(fn ($media) => $media->is_hover && ! $media->is_primary) ?? $galleryImages->get(1);
        $availability = $product->availability ?? ProductAvailability::Available;
        $fromPrice = $product->variants->min('base_price_minor');
        // Running "oferta" on the lowest price, for the card's sale badge.
        $promotion = $fromPrice !== null ? app(PromotionResolver::class)->bestFor($product, (int) $fromPrice) : null;

        return [
            'uuid' => $product->uuid,
            'name' => $product->name,
            'slug' => $product->slug,
            'type' => $product->type->value,
            'category' => $product->category?->name,
            'category_slug' => $product->category?->slug,
            'tagline' => $product->tagline,
            'availability' => $availability->value,
            'availability_label' => $availability->label(),
            'from_price_minor' => $promotion !== null ? $fromPrice - $promotion->discountFor((int) $fromPrice) : $fromPrice,
            'compare_at_minor' => $promotion !== null ? $fromPrice : null,
            'promotion_label' => $promotion?->label(),
            'currency' => $firstVariant !== null ? $firstVariant->currency : config('finisher.commerce.default_currency'),
            'in_stock' => $product->variants->contains(fn (ProductVariant $variant) => $variant->isAvailable()),
            'image_url' => $product->primaryImageUrl(),
            'image_alt' => $galleryImages->first()?->alt_text,
            'hover_image_url' => $hoverImage?->url(),
            'variant_count' => $product->variants->count(),
        ];
    }
}
