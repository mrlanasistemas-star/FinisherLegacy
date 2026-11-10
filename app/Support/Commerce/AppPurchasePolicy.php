<?php

namespace App\Support\Commerce;

use App\Enums\ProductType;
use App\Models\Order;
use App\Models\OrderItem;

/**
 * What the mobile app may sell (App Store Review Guideline 3.1.1 /
 * Google Play Payments policy): physical goods only. Digital goods —
 * event photos (`digital_photo`) and photo-space expansions
 * (`metadata.kind = photo_expansion`) — would need StoreKit / Play
 * Billing, so the app never lists them and the app checkout refuses any
 * Order that contains one. The web keeps selling them through OpenPay.
 *
 * Single source of truth for `OrderResource.app_payable` and
 * CreateAppCheckoutSession.
 */
final class AppPurchasePolicy
{
    /** Order item metadata kinds that are digital goods. */
    public const array DIGITAL_ITEM_KINDS = ['photo_expansion'];

    public static function containsDigitalGoods(Order $order): bool
    {
        $order->loadMissing('items.product');

        return $order->items->contains(fn (OrderItem $item) => self::isDigital($item));
    }

    public static function isDigital(OrderItem $item): bool
    {
        $metadata = $item->metadata ?? [];

        // Event photos carry `event_photo_uuid` (CheckoutEventPhotos).
        if (in_array($metadata['kind'] ?? null, self::DIGITAL_ITEM_KINDS, true) || isset($metadata['event_photo_uuid'])) {
            return true;
        }

        return $item->product?->type === ProductType::DigitalPhoto;
    }
}
