<?php

namespace App\Queries\Commerce;

use App\Actions\Commerce\IsVariantAvailableForCheckout;
use App\Actions\Commerce\ResolveCartDiscount;
use App\Actions\Commerce\ResolveProductPrice;
use App\Enums\ProductType;
use App\Exceptions\Api\ApiException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use App\Support\Commerce\CartSummary;
use App\Support\Commerce\CartSummaryItem;

/**
 * The one place a Cart's prices/totals are read for display (brief §28-
 * §30) — Web Cart, Web Checkout, and the API CartResource all render this
 * instead of each re-deriving totals from CartItem/ProductVariant.
 * base_price_minor directly, which drifted from what CheckoutCart
 * actually charges (brief §27: a Legacy Plate presale price, an
 * event-specific schedule, or a since-changed base price all differ from
 * the flat variant price). This mirrors CheckoutCart's own per-line
 * App\Actions\Commerce\ResolveProductPrice call — same source of truth,
 * read-only.
 */
class GetCartSummary
{
    public function __construct(
        private readonly ResolveProductPrice $resolvePrice,
        private readonly ResolveCartDiscount $resolveDiscount,
        private readonly IsVariantAvailableForCheckout $availability,
    ) {}

    /**
     * Size/color and the quantity stepper limit (max 20 per line, the
     * cart's own validation bound). `low` flags "últimas piezas" without
     * ever printing a stock count.
     *
     * @return array{size: ?string, color: ?string, max: int, low: bool}
     */
    private function lineExtras(ProductVariant $variant): array
    {
        $available = $this->availability->availableQuantity($variant);
        $isPlate = $variant->product->type === ProductType::LegacyPlate;

        return [
            ...$variant->sizeAndColor(),
            'max' => $isPlate ? 1 : min(20, $available ?? 20),
            'low' => $available !== null && $available > 0 && $available <= 3,
        ];
    }

    public function handle(Cart $cart): CartSummary
    {
        $cart->loadMissing([
            'items.productVariant.product.media',
            'items.productVariant.inventoryLevels',
            'items.eventEdition',
            'coupon',
        ]);

        $subtotal = 0;
        $lineTotalsByProduct = [];

        $items = $cart->items->map(function (CartItem $item) use ($cart, &$subtotal, &$lineTotalsByProduct) {
            $variant = $item->productVariant;
            $product = $variant->product;
            $extras = $this->lineExtras($variant);

            try {
                $price = $this->resolvePrice->handle($product, $variant, $item->eventEdition);
            } catch (ApiException) {
                // A presale window closed, a schedule was removed, etc.
                // since this line was added — never crash the whole cart
                // over one stale line, just flag it (brief item 27's fix
                // must never trade "wrong price" for "broken page").
                return new CartSummaryItem(
                    cartItemId: $item->id,
                    productName: $product->name,
                    productSlug: $product->slug,
                    variantName: $variant->name,
                    quantity: $item->quantity,
                    unitPriceMinor: null,
                    lineTotalMinor: null,
                    currency: $cart->currency,
                    priceType: null,
                    priceAvailable: false,
                    inStock: $variant->isAvailable(),
                    imageUrl: $product->primaryImageUrl(),
                    eventEditionName: $item->eventEdition?->name,
                    size: $extras['size'],
                    color: $extras['color'],
                    maxQuantity: $extras['max'],
                    connected: $product->qr_capable,
                    productType: $product->type->value,
                    requiresShipping: (bool) $product->requires_shipping,
                    lowStock: $extras['low'],
                );
            }

            $lineTotal = $price->amountMinor * $item->quantity;
            $subtotal += $lineTotal;
            $lineTotalsByProduct[$product->id] = ($lineTotalsByProduct[$product->id] ?? 0) + $lineTotal;

            return new CartSummaryItem(
                cartItemId: $item->id,
                productName: $product->name,
                productSlug: $product->slug,
                variantName: $variant->name,
                quantity: $item->quantity,
                unitPriceMinor: $price->amountMinor,
                lineTotalMinor: $lineTotal,
                currency: $price->currency,
                priceType: $price->priceType->value,
                priceAvailable: true,
                inStock: $variant->isAvailable(),
                imageUrl: $product->primaryImageUrl(),
                eventEditionName: $item->eventEdition?->name,
                size: $extras['size'],
                color: $extras['color'],
                maxQuantity: $extras['max'],
                connected: $product->qr_capable,
                productType: $product->type->value,
                requiresShipping: (bool) $product->requires_shipping,
                lowStock: $extras['low'],
            );
        });

        $discount = $this->resolveDiscount->handle($cart->coupon, $subtotal, $cart->coupon?->eligibleSubtotal($lineTotalsByProduct));

        return new CartSummary(
            lineTotalsByProduct: $lineTotalsByProduct,
            items: $items,
            subtotalMinor: $subtotal,
            discountMinor: $discount,
            totalMinor: max($subtotal - $discount, 0),
            currency: $cart->currency,
            coupon: $cart->coupon,
        );
    }
}
