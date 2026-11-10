<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Collection;
use App\Queries\Store\PublicCollections;
use App\Support\Collections\TagDesign;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A published collection for the mobile store: identity, the tag design
 * the app draws (front art + header lines + back phrase, deck pp. 7–8)
 * and, on the detail endpoint, its listable products.
 *
 * @mixin Collection
 */
class CollectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => tr($this->resource, 'name'),
            'slug' => $this->slug,
            'tagline' => tr($this->resource, 'tagline'),
            'description' => tr($this->resource, 'description'),
            'discipline' => tr($this->resource, 'discipline'),
            'destination' => $this->destination,
            'year' => $this->year,
            'accent_color' => PublicCollections::safeColor($this->accent_color),
            'cover_url' => TagDesign::absoluteUrl($this->cover_path),
            'tag_design' => TagDesign::forCollection($this->resource),
            'tag_designs' => $this->whenLoaded('designs', fn () => TagDesign::allForCollection($this->resource)),
            'products_count' => $this->whenLoaded('products', fn () => $this->products->count()),
            'products' => $this->when(
                $this->relationLoaded('products') && $request->routeIs('api.v1.store.collections.show'),
                fn () => ProductSummaryResource::collection($this->products),
            ),
        ];
    }
}
