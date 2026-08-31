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
        $available = $this->tracksInventoryAvailability();

        return [
            'uuid' => $this->uuid,
            'sku' => $this->sku,
            'name' => $this->name,
            'attributes' => $this->attributes,
            'base_price_minor' => $this->base_price_minor,
            'currency' => $this->currency,
            'active' => $this->active,
            'in_stock' => $available,
        ];
    }

    /**
     * Never exposes exact stock counts publicly (brief §177) — only
     * whether it's currently purchasable.
     */
    private function tracksInventoryAvailability(): bool
    {
        if (! $this->product->tracks_inventory) {
            return true;
        }

        $level = $this->inventoryLevels->first();

        return $level === null ? false : $level->availableQuantity() > 0;
    }
}
