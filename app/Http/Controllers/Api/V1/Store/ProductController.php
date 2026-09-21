<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ProductDetailResource;
use App\Http\Resources\Api\V1\ProductSummaryResource;
use App\Models\Product;
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
        $products = Product::query()
            ->with(['category', 'variants', 'media'])
            ->where('active', true)
            ->where('status', 'active')
            ->when($request->string('category')->toString(), fn ($q, $slug) => $q->whereHas('category', fn ($c) => $c->where('slug', $slug)))
            ->when($request->string('type')->toString(), fn ($q, $type) => $q->where('type', $type))
            ->orderBy('name')
            ->paginate(24)
            ->withQueryString();

        return $this->respond(ProductSummaryResource::collection($products), meta: [
            'current_page' => $products->currentPage(),
            'last_page' => $products->lastPage(),
            'total' => $products->total(),
        ]);
    }

    public function show(Product $product): JsonResponse
    {
        abort_unless($product->active && $product->status->value === 'active', 404);

        return $this->respond(new ProductDetailResource($product->loadMissing(['category', 'variants.inventoryLevels', 'media'])));
    }
}
