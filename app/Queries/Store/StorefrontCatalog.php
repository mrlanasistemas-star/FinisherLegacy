<?php

namespace App\Queries\Store;

use App\Enums\OrderPaymentStatus;
use App\Enums\ProductAvailability;
use App\Enums\ProductPriceType;
use App\Enums\ProductType;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Queries\Commerce\GetFeaturedProducts;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Read side of the public storefront (owner spec §17-§19): the store
 * home's editorial sections and the filterable catalog listing. Every
 * product row is eager loaded with exactly what
 * GetFeaturedProducts::summarize() reads, so a card never triggers a
 * query of its own (no N+1).
 */
class StorefrontCatalog
{
    public const PER_PAGE = 24;

    /** Sort keys the catalog accepts (anything else falls back to "destacados"). */
    public const SORTS = ['destacados', 'novedades', 'precio-asc', 'precio-desc'];

    /**
     * Published, listable products with the relations a card needs.
     *
     * @return Builder<Product>
     */
    public function query(): Builder
    {
        return Product::query()
            ->with(['category', 'variants.inventoryLevels', 'media'])
            ->withExists(['priceSchedules as has_presale' => fn (Builder $q) => $q
                ->where('active', true)
                ->where('price_type', ProductPriceType::EarlyPresale->value)
                ->where(fn (Builder $w) => $w->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
                ->where(fn (Builder $w) => $w->whereNull('ends_at')->orWhere('ends_at', '>=', now())),
            ])
            ->where('active', true)
            ->where('status', 'active')
            ->where('type', '!=', ProductType::DigitalPhoto->value);
    }

    /**
     * The store home's sections. Each one is a small bounded query; a
     * section with nothing real to show comes back empty and the page
     * simply doesn't render it.
     *
     * @return array<string, mixed>
     */
    public function sections(): array
    {
        $featured = $this->query()->orderBy('sort_order')->orderBy('name')->limit(8)->get();

        $newest = $this->query()
            ->where('created_at', '>=', now()->subDays(GetFeaturedProducts::NEW_FOR_DAYS))
            ->latest()
            ->limit(4)
            ->get();

        // Too few genuinely new products → show the latest published ones
        // under a softer title instead of an empty band.
        $newestAreRecent = $newest->count() >= 2;

        if (! $newestAreRecent) {
            $newest = $this->query()->latest()->limit(4)->get();
        }

        $legacy = $this->query()
            ->where(fn (Builder $q) => $q
                ->where('type', ProductType::LegacyPlate->value)
                ->orWhereHas('category', fn (Builder $c) => $c->where('slug', 'like', '%legacy%')))
            ->orderBy('sort_order')
            ->limit(4)
            ->get();

        $nfc = $this->query()
            ->where(fn (Builder $q) => $q->where('qr_capable', true)->orWhere('type', ProductType::LegacyPlate->value))
            ->orderBy('sort_order')
            ->limit(4)
            ->get();

        [$raceFavorites, $bySales] = $this->raceFavorites();

        return [
            'featured' => $this->cards($featured),
            'newest' => $this->cards($newest),
            'newestAreRecent' => $newestAreRecent,
            'legacy' => $this->cards($legacy),
            'nfc' => $this->cards($nfc),
            'raceFavorites' => $this->cards($raceFavorites),
            'raceFavoritesBySales' => $bySales,
        ];
    }

    /**
     * "Más usados en carreras": best sellers by units on PAID orders when
     * there are any, otherwise the featured order (and the page says so —
     * nothing pretends to be a sales ranking when it isn't one).
     *
     * @return array{0: Collection<int, Product>, 1: bool}
     */
    public function raceFavorites(int $limit = 4): array
    {
        $ids = Cache::remember('storefront:best-sellers', now()->addMinutes(10), fn () => DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.payment_status', OrderPaymentStatus::Paid->value)
            ->whereNull('order_items.event_photo_id')
            ->groupBy('order_items.product_id')
            ->orderByRaw('sum(order_items.quantity) desc')
            ->limit(12)
            ->pluck('order_items.product_id')
            ->all());

        if ($ids !== []) {
            $products = $this->query()->whereIn('id', $ids)->get()
                ->sortBy(fn (Product $p) => array_search($p->id, $ids, true))
                ->take($limit)
                ->values();

            if ($products->count() >= 2) {
                return [new Collection($products->all()), true];
            }
        }

        return [
            $this->query()
                ->where('availability', ProductAvailability::Available->value)
                ->orderBy('sort_order')
                ->limit($limit)
                ->get(),
            false,
        ];
    }

    /**
     * The filterable listing behind /tienda/catalogo and each category.
     *
     * @param  array{category?: ?ProductCategory, tipo?: ?string, talla?: ?string, nfc?: bool, disponibles?: bool, orden?: ?string}  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $sort = in_array($filters['orden'] ?? null, self::SORTS, true) ? $filters['orden'] : 'destacados';
        $type = ProductType::tryFrom((string) ($filters['tipo'] ?? ''));

        return $this->query()
            ->when($filters['category'] ?? null, fn (Builder $q, ProductCategory $category) => $q->where('category_id', $category->id))
            ->when($type !== null && $type !== ProductType::DigitalPhoto, fn (Builder $q) => $q->where('type', $type->value))
            ->when($filters['nfc'] ?? false, fn (Builder $q) => $q->where(fn (Builder $w) => $w->where('qr_capable', true)->orWhere('type', ProductType::LegacyPlate->value)))
            ->when($filters['disponibles'] ?? false, fn (Builder $q) => $q->where('availability', ProductAvailability::Available->value))
            ->when($filters['talla'] ?? null, fn (Builder $q, string $size) => $q->whereHas('variants', fn (Builder $v) => $v
                ->where('active', true)
                ->where(fn (Builder $w) => $w->where('attributes->size', $size)->orWhere('attributes->talla', $size))))
            ->withMin(['variants as min_price_minor' => fn (Builder $q) => $q->where('active', true)], 'base_price_minor')
            ->when($sort === 'novedades', fn (Builder $q) => $q->latest())
            ->when($sort === 'precio-asc', fn (Builder $q) => $q->orderByRaw('min_price_minor is null')->orderBy('min_price_minor'))
            ->when($sort === 'precio-desc', fn (Builder $q) => $q->orderByDesc('min_price_minor'))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Product $product) => GetFeaturedProducts::summarize($product));
    }

    /**
     * Sizes offered by any listable product (for the filter chips), in a
     * natural apparel order.
     *
     * @return list<string>
     */
    public function sizeOptions(?ProductCategory $category = null): array
    {
        $order = ['XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', '2XL', '3XL'];

        $sizes = DB::table('product_variants')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->where('products.active', true)
            ->where('products.status', 'active')
            ->where('product_variants.active', true)
            ->when($category, fn ($q, ProductCategory $c) => $q->where('products.category_id', $c->id))
            ->limit(500)
            ->pluck('product_variants.attributes')
            ->map(fn ($json) => is_string($json) ? json_decode($json, true) : null)
            ->map(fn ($attributes) => is_array($attributes) ? ($attributes['size'] ?? $attributes['talla'] ?? null) : null)
            ->filter(fn ($size) => is_string($size) && $size !== '')
            ->unique()
            ->values()
            ->all();

        usort($sizes, function (string $a, string $b) use ($order) {
            $ia = array_search(strtoupper($a), $order, true);
            $ib = array_search(strtoupper($b), $order, true);

            return ($ia === false ? 99 : $ia) <=> ($ib === false ? 99 : $ib) ?: strnatcmp($a, $b);
        });

        return $sizes;
    }

    /**
     * Active categories with their count of listable products.
     *
     * @return list<array<string, mixed>>
     */
    public function categories(): array
    {
        return array_values(ProductCategory::query()
            ->where('active', true)
            ->withCount(['products' => fn (Builder $q) => $q->where('active', true)->where('status', 'active')->where('type', '!=', ProductType::DigitalPhoto->value)])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'description'])
            ->map(fn (ProductCategory $category) => [
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'products_count' => $category->products_count,
            ])
            ->all());
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return list<array<string, mixed>>
     */
    public function cards(Collection $products): array
    {
        return array_values($products->map(fn (Product $product) => GetFeaturedProducts::summarize($product))->all());
    }
}
