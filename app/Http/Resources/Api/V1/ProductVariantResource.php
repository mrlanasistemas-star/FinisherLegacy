<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductVariant
 */
class ProductVariantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'sku' => $this->sku,
            'name' => $this->name,
            'attributes' => $this->attributes,
            'base_price_minor' => $this->base_price_minor,
            'currency' => $this->currency,
            'active' => $this->active,
            'in_stock' => $this->resource->isAvailable(),
        ];
    }
}
