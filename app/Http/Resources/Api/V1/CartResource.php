<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Cart;
use App\Queries\Commerce\GetCartSummary;
use App\Support\Commerce\CartSummaryItem;
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
        $summary = app(GetCartSummary::class)->handle($this->resource);

        return [
            'uuid' => $this->uuid,
            'currency' => $summary->currency,
            'items' => $summary->items->map(fn (CartSummaryItem $item) => [
                'id' => $item->cartItemId,
                'quantity' => $item->quantity,
                'product_name' => $item->productName,
                'product_slug' => $item->productSlug,
                'variant_name' => $item->variantName,
                'unit_price_minor' => $item->unitPriceMinor,
                'line_total_minor' => $item->lineTotalMinor,
                'currency' => $item->currency,
                'price_type' => $item->priceType,
                'price_available' => $item->priceAvailable,
                'in_stock' => $item->inStock,
                'image_url' => $item->imageUrl,
                'event_edition_name' => $item->eventEditionName,
            ])->values(),
            'subtotal_minor' => $summary->subtotalMinor,
            'discount_minor' => $summary->discountMinor,
            'total_minor' => $summary->totalMinor,
            'coupon' => $summary->coupon !== null ? ['code' => $summary->coupon->code, 'name' => $summary->coupon->name] : null,
        ];
    }
}
