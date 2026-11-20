<?php

namespace App\Support\Commerce;

use App\Enums\DeliveryMethod;

/**
 * The one place that prices delivery (config `finisher.commerce.shipping`).
 * CheckoutCart freezes the result on the Order, and the web and app
 * checkouts show the same numbers before the order exists, so what the
 * buyer sees is what OpenPay charges.
 */
final class ShippingRates
{
    /**
     * Shipping charged for a delivery method, given the subtotal after the
     * coupon discount (the "free over" threshold looks at what is paid for
     * the products, not their list price).
     */
    public static function feeFor(DeliveryMethod|string|null $method, int $subtotalAfterDiscountMinor): int
    {
        $method = $method instanceof DeliveryMethod ? $method : DeliveryMethod::tryFrom((string) $method);

        if ($method !== DeliveryMethod::Shipping) {
            return 0;
        }

        $fee = max(0, (int) config('finisher.commerce.shipping.fee_minor', 0));
        $freeOver = self::freeOverMinor();

        if ($freeOver !== null && $subtotalAfterDiscountMinor >= $freeOver) {
            return 0;
        }

        return $fee;
    }

    public static function freeOverMinor(): ?int
    {
        $value = config('finisher.commerce.shipping.free_over_minor');

        return $value === null ? null : max(0, (int) $value);
    }

    /**
     * Delivery choices a checkout offers, priced for this subtotal.
     *
     * @return list<array{value: string, label: string, fee_minor: int, free_over_minor: int|null}>
     */
    public static function options(int $subtotalAfterDiscountMinor): array
    {
        return [
            [
                'value' => DeliveryMethod::Pickup->value,
                'label' => DeliveryMethod::Pickup->label(),
                'fee_minor' => 0,
                'free_over_minor' => null,
            ],
            [
                'value' => DeliveryMethod::Shipping->value,
                'label' => DeliveryMethod::Shipping->label(),
                'fee_minor' => self::feeFor(DeliveryMethod::Shipping, $subtotalAfterDiscountMinor),
                'free_over_minor' => self::freeOverMinor(),
            ],
        ];
    }
}
