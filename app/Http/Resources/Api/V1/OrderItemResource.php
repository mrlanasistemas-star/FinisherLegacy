<?php

namespace App\Http\Resources\Api\V1;

use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrderItem
 */
class OrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'sku' => $this->sku,
            'quantity' => $this->quantity,
            'unit_price_minor' => $this->unit_price_minor,
            'line_total_minor' => $this->line_total_minor,
            'currency' => $this->currency,
            'fulfilled' => $this->isFulfilled(),
            // Additive (mobile store). Read from the live catalog only for
            // display; the commercial snapshot above stays the truth.
            'variant_name' => $this->productVariant?->name,
            'image_url' => $this->product?->primaryImageUrl(),
        ];
    }
}
