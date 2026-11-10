<?php

namespace App\Actions\Iap;

use App\Actions\Quotas\RevokePhotoExpansionEntitlements;
use App\Enums\OrderPaymentStatus;
use App\Enums\PaymentStatus;
use App\Models\MediaEntitlement;
use App\Models\PhotoExpansionPack;
use App\Models\StorePurchase;
use Illuminate\Support\Facades\DB;

/**
 * A store refund / revocation (App Store Server Notification REFUND or
 * REVOKE, Google Play voided purchase or cancelled one-time purchase)
 * removes the capacity that transaction granted — through the same
 * RevokePhotoExpansionEntitlements as a web refund, so photos already
 * uploaded are never deleted. Idempotent. `reinstate()` undoes it when
 * Apple reverses the refund (REFUND_REVERSED).
 */
class RevokeStorePurchase
{
    public function __construct(private readonly RevokePhotoExpansionEntitlements $revoke) {}

    public function handle(StorePurchase $purchase, string $reason): void
    {
        if ($purchase->isRevoked()) {
            return;
        }

        DB::transaction(function () use ($purchase, $reason) {
            $purchase->update([
                'status' => StorePurchase::STATUS_REVOKED,
                'revoked_at' => now(),
                'revocation_reason' => mb_substr($reason, 0, 64),
            ]);

            $order = $purchase->order;

            if ($order === null) {
                return;
            }

            $order->update(['payment_status' => OrderPaymentStatus::Refunded]);
            $order->payments()->where('status', PaymentStatus::Paid)->update(['status' => PaymentStatus::Refunded, 'updated_at' => now()]);

            $this->revoke->handle($order);
        });
    }

    public function reinstate(StorePurchase $purchase): void
    {
        if (! $purchase->isRevoked()) {
            return;
        }

        DB::transaction(function () use ($purchase) {
            $purchase->update(['status' => StorePurchase::STATUS_GRANTED, 'revoked_at' => null, 'revocation_reason' => null]);

            $order = $purchase->order;

            if ($order === null) {
                return;
            }

            $order->update(['payment_status' => OrderPaymentStatus::Paid]);
            $order->payments()->where('status', PaymentStatus::Refunded)->update(['status' => PaymentStatus::Paid, 'updated_at' => now()]);

            MediaEntitlement::query()
                ->where('source', PhotoExpansionPack::ENTITLEMENT_SOURCE)
                ->whereNotNull('revoked_at')
                ->whereIn('order_item_id', $order->items()->select('id'))
                ->update(['revoked_at' => null, 'updated_at' => now()]);
        });
    }
}
