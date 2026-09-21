<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ProductDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'type' => $this->type->value,
            'brand' => $this->brand,
            'category' => $this->category?->name,
            'qr_capable' => $this->qr_capable,
            'requires_shipping' => $this->requires_shipping,
            'image_url' => $this->primaryImageUrl(),
            'gallery' => $this->whenLoaded('media', fn () => $this->media->map(fn ($m) => [
                'type' => $m->type->value,
                'url' => $m->url(),
                'poster_url' => $m->posterUrl(),
                'alt_text' => $m->alt_text,
                'is_primary' => $m->is_primary,
            ])),
            'variants' => ProductVariantResource::collection($this->variants->where('active', true)->values()),
        ];
    }
}
