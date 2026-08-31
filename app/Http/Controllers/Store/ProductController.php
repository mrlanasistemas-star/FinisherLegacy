<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCategory;
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
            ->with(['category', 'variants.inventoryLevels'])
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

        $product->loadMissing(['category', 'variants.inventoryLevels']);

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
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function summarize(Product $product): array
    {
        $firstVariant = $product->variants->first();

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
            'image_url' => $product->image_path ? Storage::disk('public')->url($product->image_path) : null,
        ];
    }
}
