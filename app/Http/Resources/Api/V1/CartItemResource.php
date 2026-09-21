<?php

namespace App\Http\Resources\Api\V1;

use App\Models\CartItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CartItem
 */
class CartItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'quantity' => $this->quantity,
            'event_edition_id' => $this->event_edition_id,
            'variant' => new ProductVariantResource($this->whenLoaded('productVariant')),
            'product_name' => $this->productVariant?->product?->name,
            'line_total_minor' => $this->productVariant !== null ? $this->productVariant->base_price_minor * $this->quantity : null,
            'image_url' => $this->productVariant?->product?->primaryImageUrl(),
        ];
    }
}
