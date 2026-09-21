<?php

namespace App\Http\Resources\Api\V1;

use App\Actions\Commerce\ResolveCartDiscount;
use App\Models\Cart;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Cart
 */
class CartResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $subtotal = $this->items->sum(fn ($item) => $item->productVariant->base_price_minor * $item->quantity);
        $discount = app(ResolveCartDiscount::class)->handle($this->coupon, $subtotal);

        return [
            'uuid' => $this->uuid,
            'currency' => $this->currency,
            'items' => CartItemResource::collection($this->whenLoaded('items')),
            'subtotal_minor' => $subtotal,
            'discount_minor' => $discount,
            'total_minor' => max($subtotal - $discount, 0),
            'coupon' => $this->coupon !== null ? ['code' => $this->coupon->code, 'name' => $this->coupon->name] : null,
        ];
    }
}
