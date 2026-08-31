<?php

namespace App\Http\Resources\Api\V1;

use App\Models\AthleteOwnedProduct;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * `GET /api/v1/gear/{code}` — deliberately minimal, no owner PII (brief
 * §88/§107/§176): no athlete name, no order/purchase info, no email.
 *
 * @mixin AthleteOwnedProduct
 */
class PublicGearResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'product_name' => $this->product->name,
            'variant_name' => $this->productVariant?->name,
            'status' => $this->status->value,
            'claimable' => $this->status->value === 'unclaimed',
        ];
    }
}
