<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CollectionResource;
use App\Models\Collection;
use App\Models\Product;
use App\Queries\Store\PublicCollections;
use Illuminate\Http\JsonResponse;

/**
 * Public collections for the mobile store — the same published-only rule
 * as /colecciones (PublicCollections): a draft is a 404, never a teaser.
 */
class CollectionController extends Controller
{
    use ApiResponses;

    public function index(PublicCollections $collections): JsonResponse
    {
        $items = $collections->query()->limit(50)->get();
        $collections->preloadTranslations($items);

        return $this->respond(CollectionResource::collection($items));
    }

    public function show(Collection $collection, PublicCollections $collections): JsonResponse
    {
        abort_unless($collection->isPublished(), 404);

        $collection = $collections->query()->whereKey($collection->id)->firstOrFail();
        $collection->products->load('category')->each(fn (Product $product) => $product->setRelation('collection', $collection));
        $collections->preloadTranslations([$collection]);

        return $this->respond(new CollectionResource($collection));
    }
}
