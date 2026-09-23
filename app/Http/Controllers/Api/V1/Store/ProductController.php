<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ProductDetailResource;
use App\Http\Resources\Api\V1\ProductSummaryResource;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public store catalog (brief §109/§111) — only active products, never
 * exact stock counts (brief §176-§177).
 */
class ProductController extends Controller
{
    use ApiResponses;

    public function index(Request $request): JsonResponse
    {
        $search = trim($request->string('q')->toString());
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';

        $products = Product::query()
            ->with(['category', 'variants.inventoryLevels', 'media'])
            ->where('active', true)
            ->where('status', 'active')
            ->when($request->string('category')->toString(), fn ($q, $slug) => $q->whereHas('category', fn ($c) => $c->where('slug', $slug)))
            ->when($request->string('type')->toString(), fn ($q, $type) => $q->where('type', $type))
            ->when($search !== '', fn ($q) => $q->where(fn ($inner) => $inner->where('name', 'like', $like)->orWhere('brand', 'like', $like)))
            ->when(
                $request->string('sort')->toString() === 'newest',
                fn ($q) => $q->orderByDesc('created_at'),
                fn ($q) => $q->orderBy('name'),
            )
            ->paginate(24)
            ->withQueryString();

        return $this->respond(ProductSummaryResource::collection($products), meta: [
            'current_page' => $products->currentPage(),
            'last_page' => $products->lastPage(),
            'total' => $products->total(),
            // Only categories that actually have something for sale — the
            // chips a client shows, filtered by `slug`.
            'categories' => ProductCategory::query()
                ->where('active', true)
                ->whereHas('products', fn ($q) => $q->where('active', true)->where('status', 'active'))
                ->orderBy('name')
                ->get(['name', 'slug']),
        ]);
    }

    public function show(Product $product): JsonResponse
    {
        abort_unless($product->active && $product->status->value === 'active', 404);

        return $this->respond(new ProductDetailResource($product->loadMissing(['category', 'variants.inventoryLevels', 'media'])));
    }
}
