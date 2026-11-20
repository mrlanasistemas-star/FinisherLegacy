<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\DeliveryMethod;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Queries\Commerce\GetCartSummary;
use App\Support\Collections\TagDesign;
use App\Support\Commerce\CartSummaryItem;
use App\Support\Commerce\ShippingRates;
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
            // Checkout (app): delivery choices priced by the server — the
            // same ShippingRates CheckoutCart charges — plus the buyer's last
            // shipping address to prefill the form. Additive fields.
            'requires_shipping' => $summary->items->contains(fn (CartSummaryItem $item) => $item->requiresShipping),
            'delivery_options' => ShippingRates::options($summary->totalMinor),
            'last_shipping_address' => $this->lastShippingAddress(),
        ];
    }

    /**
     * The address of the cart owner's most recent home-delivery order, so a
     * repeat buyer doesn't retype it. Never another person's data: scoped
     * to the cart's own user.
     *
     * @return array<string, string|null>|null
     */
    private function lastShippingAddress(): ?array
    {
        if ($this->user_id === null) {
            return null;
        }

        $address = Order::query()
            ->where('user_id', $this->user_id)
            ->where('delivery_method', DeliveryMethod::Shipping)
            ->whereNotNull('shipping_address')
            ->latest('id')
            ->value('shipping_address');

        $address = is_string($address) ? json_decode($address, true) : $address;

        if (! is_array($address)) {
            return null;
        }

        return collect(['line', 'neighborhood', 'city', 'state', 'postal_code'])
            ->mapWithKeys(fn (string $key) => [$key => isset($address[$key]) ? (string) $address[$key] : null])
            ->all();
    }
}
