<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductContentSection;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Queries\Commerce\GetFeaturedProducts;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public storefront (brief §25-§30/§60-§64) — Web Controller → Model query
 * → Inertia, no REST call from Vue (brief §62/§73/§87-§89: the API is for
 * external clients, the web reuses the same layer directly). Never
 * exposes exact stock counts (brief §64/§176-§177).
 */
class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        $products = Product::query()
            ->with(['category', 'variants.inventoryLevels', 'media'])
            ->where('active', true)
            ->where('status', 'active')
            ->when($request->string('category')->toString(), fn ($q, $slug) => $q->whereHas('category', fn ($c) => $c->where('slug', $slug)))
            ->orderBy('name')
            ->get();

        return Inertia::render('store/Index', [
            'products' => $products->map(fn (Product $product) => GetFeaturedProducts::summarize($product)),
            'categories' => ProductCategory::query()->where('active', true)->orderBy('name')->get(['name', 'slug']),
            'filters' => ['category' => $request->string('category')->toString()],
        ]);
    }

    public function show(Product $product): Response
    {
        abort_unless($product->active && $product->status->value === 'active', 404);

        $product->loadMissing(['category', 'variants.inventoryLevels', 'media', 'contentSections']);
        // Wire the inverse relation in memory so ProductVariant::isAvailable()
        // never lazy-loads `product` per variant (no N+1, brief §69).
        $product->variants->each(fn (ProductVariant $variant) => $variant->setRelation('product', $product));

        $related = Product::query()
            ->where('id', '!=', $product->id)
            ->where('active', true)
            ->where('status', 'active')
            ->when($product->category_id, fn ($q) => $q->where('category_id', $product->category_id))
            ->with(['variants.inventoryLevels', 'media'])
            ->limit(4)
            ->get();

        return Inertia::render('store/Show', [
            'product' => [
                'uuid' => $product->uuid,
                'name' => $product->name,
                'slug' => $product->slug,
                'description' => $product->description,
                'type' => $product->type->value,
                'brand' => $product->brand,
                'category' => $product->category?->name,
                'qr_capable' => $product->qr_capable,
                'requires_shipping' => $product->requires_shipping,
                'image_url' => $product->primaryImageUrl(),
                'variants' => $product->variants->where('active', true)->values()->map(fn (ProductVariant $variant) => [
                    'id' => $variant->id,
                    'sku' => $variant->sku,
                    'name' => $variant->name,
                    'attributes' => $variant->attributes,
                    'base_price_minor' => $variant->base_price_minor,
                    'currency' => $variant->currency,
                    'in_stock' => $variant->isAvailable(),
                ]),
                'gallery' => $product->media->map(fn (ProductMedia $m) => [
                    'id' => $m->id,
                    'type' => $m->type->value,
                    'url' => $m->url(),
                    'poster_url' => $m->posterUrl(),
                    'alt_text' => $m->alt_text,
                    'is_primary' => $m->is_primary,
                ]),
                'contentSections' => $product->contentSections->map(fn (ProductContentSection $s) => [
                    'type' => $s->type->value,
                    'title' => $s->title,
                    'content' => $s->content,
                ]),
            ],
            'relatedProducts' => $related->map(fn (Product $p) => GetFeaturedProducts::summarize($p)),
        ]);
    }
}
