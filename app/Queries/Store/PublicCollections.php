<?php

namespace App\Queries\Store;

use App\Actions\Commerce\IsVariantAvailableForCheckout;
use App\Actions\Commerce\ResolveProductPrice;
use App\Enums\ProductAvailability;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Exceptions\PriceNotAvailableException;
use App\Models\Collection;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Collections\TagDesign;
use App\Support\I18n\ContentTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Read side of the public collections (docs/architecture/public-experience.md):
 * only published collections (`Collection::published()`), only their
 * listable products, prices decided by ResolveProductPrice (online store =
 * no channel) and availability by IsVariantAvailableForCheckout — the
 * same two answers the cart and checkout give. Never exposes stock counts.
 */
class PublicCollections
{
    public function __construct(
        private readonly ResolveProductPrice $prices,
        private readonly IsVariantAvailableForCheckout $availability,
    ) {}

    /**
     * @return Builder<Collection>
     */
    public function query(): Builder
    {
        return Collection::query()
            ->published()
            ->with(['designs', 'products' => fn (Relation $q) => $this->listable($q)])
            ->orderBy('sort_order')
            ->orderByDesc('year')
            ->orderBy('name');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function cards(?int $limit = null): array
    {
        return array_values($this->query()
            ->when($limit, fn (Builder $q, int $l) => $q->limit($l))
            ->get()
            ->tap(fn (EloquentCollection $collections) => $this->preloadTranslations($collections))
            ->map(fn (Collection $collection) => $this->card($collection))
            ->all());
    }

    /**
     * @return array<string, mixed>
     */
    public function card(Collection $collection): array
    {
        $products = $collection->products->map(fn (Product $product) => $this->product($product->setRelation('collection', $collection)));
        $priced = $products->whereNotNull('price_minor');

        return [
            'name' => tr($collection, 'name'),
            'slug' => $collection->slug,
            'tagline' => tr($collection, 'tagline'),
            'discipline' => tr($collection, 'discipline'),
            'destination' => $collection->destination,
            'year' => $collection->year,
            'edition' => $collection->edition,
            'season' => $collection->season,
            'accent_color' => self::safeColor($collection->accent_color),
            'cover_url' => $collection->coverUrl(),
            'hero_url' => $collection->heroUrl(),
            'products_count' => $products->count(),
            'from_price_minor' => $priced->min('price_minor'),
            'currency' => $priced->first()['currency'] ?? config('finisher.commerce.default_currency', 'MXN'),
            'available' => $products->contains(fn (array $p) => $p['available']),
            // Deck pp. 7–8 tag artwork: the primary design + every design.
            'tag_design' => TagDesign::forCollection($collection, null, false),
            'tag_designs' => TagDesign::allForCollection($collection, false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(Collection $collection): array
    {
        $collection->loadMissing(['designs', 'products' => fn (Relation $q) => $this->listable($q)]);
        $this->preloadTranslations([$collection]);

        return [
            ...$this->card($collection),
            'description' => tr($collection, 'description'),
            'seo_title' => tr($collection, 'seo_title'),
            'seo_description' => tr($collection, 'seo_description'),
            'products' => $collection->products->map(fn (Product $product) => $this->product($product->setRelation('collection', $collection)))->values()->all(),
        ];
    }

    /**
     * One product of a collection: cheapest purchasable variant's real
     * price, and whether checkout could actually sell it right now.
     *
     * @return array<string, mixed>
     */
    public function product(Product $product): array
    {
        $variants = $product->variants->where('active', true)->values();
        $variants->each(fn (ProductVariant $variant) => $variant->setRelation('product', $product));

        $best = null;

        foreach ($variants as $variant) {
            try {
                $price = $this->prices->handle($product, $variant);
            } catch (PriceNotAvailableException) {
                continue;
            }

            if ($best === null || $price->amountMinor < $best->amountMinor) {
                $best = $price;
            }
        }

        $availability = $product->availability ?? ProductAvailability::Available;
        $image = $product->media->where('type', 'image')->sortByDesc('is_primary')->first();

        return [
            'uuid' => $product->uuid,
            'name' => tr($product, 'name'),
            'slug' => $product->slug,
            'type' => $product->type->value,
            'tagline' => tr($product, 'tagline'),
            'description' => tr($product, 'description'),
            'image_url' => $product->primaryImageUrl(),
            'image_alt' => $image->alt_text ?? tr($product, 'name'),
            'price_minor' => $best?->amountMinor,
            'compare_at_minor' => $best?->originalAmountMinor,
            'promotion_label' => $best?->promotionLabel,
            'currency' => $best->currency ?? ($variants->first()->currency ?? config('finisher.commerce.default_currency', 'MXN')),
            'is_purchasable' => $product->isPurchasable(),
            'availability' => $availability->value,
            'availability_label' => $availability->label(),
            'available' => $product->isPurchasable() && $variants->contains(fn (ProductVariant $v) => $this->availability->handle($v)),
            'material' => tr($product, 'material'),
            'strap_type' => tr($product, 'strap_type'),
            'dimensions' => tr($product, 'dimensions'),
            'tag_design' => TagDesign::forProduct($product, false),
        ];
    }

    /**
     * Products a guest may see: active, published, never internal photos
     * (constrains the eager load in place).
     *
     * @param  Relation<*, *, *>  $query
     */
    private function listable(Relation $query): void
    {
        $query->with(['design', 'variants.inventoryLevels', 'media'])->where('active', true)
            ->where('status', ProductStatus::Active->value)
            ->where('type', '!=', ProductType::DigitalPhoto->value)
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    /**
     * One query per model class for the translations of the collections
     * and their products (nothing to do in Spanish).
     *
     * @param  iterable<Collection>  $collections
     */
    public function preloadTranslations(iterable $collections): void
    {
        $content = app(ContentTranslations::class);

        foreach ($collections as $collection) {
            $content->preload([$collection]);

            if ($collection->relationLoaded('designs')) {
                $content->preload($collection->designs);
            }

            if ($collection->relationLoaded('products')) {
                $content->preload($collection->products);
            }
        }
    }

    /** Admin-entered accent: only a plain hex color ever reaches a style attribute. */
    public static function safeColor(?string $color): ?string
    {
        return $color !== null && preg_match('/^#[0-9a-fA-F]{3,8}$/', $color) ? $color : null;
    }

    /**
     * Published collections for the sitemap (slug + last change).
     *
     * @return EloquentCollection<int, Collection>
     */
    public function forSitemap(): EloquentCollection
    {
        return Collection::query()->published()->orderBy('sort_order')->get(['id', 'slug', 'updated_at']);
    }
}
