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
    ) {}
}
