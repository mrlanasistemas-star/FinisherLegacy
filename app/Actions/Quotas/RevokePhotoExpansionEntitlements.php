<?php

namespace App\Actions\Quotas;

use App\Models\MediaEntitlement;
use App\Models\Order;
use App\Models\PhotoExpansionPack;

/**
 * Called by App\Actions\Commerce\ProcessPaymentWebhook when an Order is
 * fully refunded (Stripe/OpenPay refund webhook or the server-to-server
 * status sync, which share that path). Sets `revoked_at` on every photo
 * expansion right the Order granted, so the story's limit drops back.
 * Photos already uploaded are NEVER deleted: a story above its new limit
 * keeps showing all of them and simply cannot add more. Idempotent.
 */
class RevokePhotoExpansionEntitlements
{
    public function handle(Order $order): int
    {
        return MediaEntitlement::query()
            ->where('source', PhotoExpansionPack::ENTITLEMENT_SOURCE)
            ->whereNull('revoked_at')
            ->whereIn('order_item_id', $order->items()->select('id'))
            ->update(['revoked_at' => now(), 'updated_at' => now()]);
    }
}
