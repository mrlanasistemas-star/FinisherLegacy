<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Cart;
use App\Models\Product;
use App\Queries\Commerce\GetCartSummary;
use App\Support\Collections\TagDesign;
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
        // One query for every line: product type + tag design, so the
        // mobile cart draws each tag without a product request per item.
        $products = Product::query()->with(['collection', 'design'])
            ->whereIn('slug', $summary->items->map(fn (CartSummaryItem $item) => $item->productSlug)->filter()->unique()->values())
            ->get()
            ->keyBy('slug');

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
                // Additive (mobile store).
                'max_quantity' => $item->maxQuantity,
                'size' => $item->size,
                'color' => $item->color,
                'nfc_enabled' => $item->connected,
                'low_stock' => $item->lowStock,
                'product_type' => $products->get($item->productSlug)?->type->value,
                'tag_design' => ($product = $products->get($item->productSlug)) !== null ? TagDesign::forProduct($product) : null,
            ])->values(),
            'subtotal_minor' => $summary->subtotalMinor,
            'discount_minor' => $summary->discountMinor,
            'total_minor' => $summary->totalMinor,
            'coupon' => $summary->coupon !== null ? ['code' => $summary->coupon->code, 'name' => $summary->coupon->name] : null,
        ];
    }
}
