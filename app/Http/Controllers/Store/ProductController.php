<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductContentSection;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
            'products' => $products->map(fn (Product $product) => $this->summarize($product)),
            'categories' => ProductCategory::query()->where('active', true)->orderBy('name')->get(['name', 'slug']),
            'filters' => ['category' => $request->string('category')->toString()],
        ]);
    }

    public function show(Product $product): Response
    {
        abort_unless($product->active && $product->status->value === 'active', 404);

        $product->loadMissing(['category', 'variants.inventoryLevels', 'media', 'contentSections']);

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
                'image_url' => $product->image_path ? Storage::disk('public')->url($product->image_path) : null,
                'variants' => $product->variants->where('active', true)->values()->map(fn (ProductVariant $variant) => [
                    'id' => $variant->id,
                    'sku' => $variant->sku,
                    'name' => $variant->name,
                    'attributes' => $variant->attributes,
                    'base_price_minor' => $variant->base_price_minor,
                    'currency' => $variant->currency,
                    'in_stock' => ! $product->tracks_inventory || $variant->inventoryLevels->sum(fn ($l) => $l->availableQuantity()) > 0,
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
            'relatedProducts' => $related->map(fn (Product $p) => $this->summarize($p)),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function summarize(Product $product): array
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
