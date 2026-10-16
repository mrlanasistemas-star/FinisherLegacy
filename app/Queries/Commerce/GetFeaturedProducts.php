<?php

namespace App\Queries\Commerce;

use App\Enums\ProductAvailability;
use App\Enums\ProductType;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Commerce\InventoryService;
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
            'image_alt' => $galleryImages->first()?->alt_text ?? $product->conceptImageAlt(),
            'image_source' => $product->imageSource(),
            'hover_image_url' => $hoverImage?->url() ?? self::conceptHoverUrl($product, $galleryImages->isEmpty()),
            'variant_count' => $product->variants->count(),
            ...self::merchandising($product),
        ];
    }

    /** Variants at or under this many units read as "Últimas piezas". */
    public const LOW_STOCK_UNITS = 5;

    /** A product published within this many days carries the "Nuevo" badge. */
    public const NEW_FOR_DAYS = 45;

    /**
     * Storefront card extras (owner spec §19): colors, sizes, a visual
     * stock level (never a count) and the NFC / Nuevo / Preventa badges.
     * Reads only already-loaded relations; `has_presale` comes from a
     * `withExists` on the query when the caller wants that badge.
     *
     * @return array<string, mixed>
     */
    public static function merchandising(Product $product): array
    {
        $variants = $product->variants->where('active', true);
        $colors = [];
        $sizes = [];

        foreach ($variants as $variant) {
            $attributes = is_array($variant->attributes) ? $variant->attributes : [];
            $color = trim((string) ($attributes['color'] ?? $attributes['colour'] ?? ''));
            $size = trim((string) ($attributes['size'] ?? $attributes['talla'] ?? ''));

            if ($color !== '' && ! isset($colors[$color])) {
                $colors[$color] = ['name' => $color, 'hex' => $attributes['color_hex'] ?? null];
            }

            if ($size !== '') {
                $sizes[$size] = ($sizes[$size] ?? false) || $variant->isAvailable();
            }
        }

        return [
            'colors' => array_values($colors),
            'sizes' => collect($sizes)->map(fn (bool $inStock, string $label) => ['label' => $label, 'in_stock' => $inStock])->values()->all(),
            'stock_level' => self::stockLevel($product),
            'is_nfc' => (bool) ($product->getAttribute('nfc_capable') ?? $product->qr_capable) || $product->type === ProductType::LegacyPlate,
            'is_new' => $product->created_at !== null && $product->created_at->gt(now()->subDays(self::NEW_FOR_DAYS)),
            'is_presale' => (bool) $product->getAttribute('has_presale'),
        ];
    }

    /**
     * 'out' | 'low' | 'ok' for products that track inventory (null when
     * they don't, or aren't for sale) — a visual hint, never a count.
     */
    public static function stockLevel(Product $product): ?string
    {
        if (! ($product->availability ?? ProductAvailability::Available)->isPurchasable()) {
            return null;
        }

        if (! $product->tracks_inventory) {
            return 'ok';
        }

        $location = app(InventoryService::class)->defaultLocation();
        $available = $product->variants->where('active', true)->sum(function (ProductVariant $variant) use ($location) {
            $level = $variant->relationLoaded('inventoryLevels')
                ? $variant->inventoryLevels->firstWhere('inventory_location_id', $location->id)
                : null;

            return $level === null ? 0 : max(0, $level->availableQuantity());
        });

        return match (true) {
            $available <= 0 => 'out',
            $available <= self::LOW_STOCK_UNITS => 'low',
            default => 'ok',
        };
    }

    /** Second render of the concept gallery as the card's hover angle. */
    private static function conceptHoverUrl(Product $product, bool $noUploads): ?string
    {
        if (! $noUploads || $product->image_path) {
            return null;
        }

        return $product->conceptGallery()[1]['url'] ?? null;
    }
}
