<?php

namespace App\Support\Commerce;

/**
 * One resolved cart line — always built from App\Actions\Commerce\
 * ResolveProductPrice, never CartItem/ProductVariant.base_price_minor
 * directly (that drifted from what CheckoutCart actually charges).
 */
final class CartSummaryItem
{
    public function __construct(
        public readonly int $cartItemId,
        public readonly string $productName,
        public readonly ?string $productSlug,
        public readonly string $variantName,
        public readonly int $quantity,
        public readonly ?int $unitPriceMinor,
        public readonly ?int $lineTotalMinor,
        public readonly string $currency,
        public readonly ?string $priceType,
        public readonly bool $priceAvailable,
        public readonly bool $inStock,
        public readonly ?string $imageUrl,
        public readonly ?string $eventEditionName,
        public readonly ?string $size = null,
        public readonly ?string $color = null,
        public readonly ?int $maxQuantity = null,
        public readonly bool $connected = false,
        public readonly ?string $productType = null,
        public readonly bool $requiresShipping = false,
        public readonly bool $lowStock = false,
    ) {}

    /**
     * The cart/checkout page row (store web).
     *
     * @return array<string, mixed>
     */
    public function toPageArray(): array
    {
        return [
            'id' => $this->cartItemId,
            'quantity' => $this->quantity,
            'product_name' => $this->productName,
            'product_slug' => $this->productSlug,
            'variant_name' => $this->variantName,
            'size' => $this->size,
            'color' => $this->color,
            'unit_price_minor' => $this->unitPriceMinor,
            'line_total_minor' => $this->lineTotalMinor,
            'currency' => $this->currency,
            'price_available' => $this->priceAvailable,
            'image_url' => $this->imageUrl,
            'in_stock' => $this->inStock,
            'low_stock' => $this->lowStock,
            'max_quantity' => $this->maxQuantity,
            'event_edition_name' => $this->eventEditionName,
            'connected' => $this->connected,
            'product_type' => $this->productType,
            'requires_shipping' => $this->requiresShipping,
        ];
    }
}
