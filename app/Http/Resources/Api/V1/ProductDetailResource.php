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
            'variants' => ProductVariantResource::collection($this->variants->where('active', true)->values()),
        ];
    }
}
