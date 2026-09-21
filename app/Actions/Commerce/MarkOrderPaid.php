<?php

namespace App\Actions\Commerce;

use App\Enums\CouponRedemptionStatus;
use App\Enums\LegacyPlateEntitlementStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Models\CouponRedemption;
use App\Models\Order;

/**
 * Shared by RegisterManualPayment and ProcessPaymentWebhook so "what
 * happens once an Order is fully paid" is defined in exactly one place
 * (brief §83: compose, no God Action) — flips payment_status, confirms the
 * Order if it's still pending, moves any Legacy Plate entitlement this
 * Order funded from pending_payment to paid (brief §81/§100: paid enables
 * production, it never triggers it), and — if this Order used a coupon —
 * promotes its CouponRedemption from `reserved` to `redeemed` (brief
 * §35: a coupon is only actually spent once the Order is really paid).
 */
class MarkOrderPaid
{
    public function __construct(private readonly ConfirmOrder $confirmOrder) {}

    public function handle(Order $order): Order
    {
        $order->update(['payment_status' => OrderPaymentStatus::Paid]);
        $order = $order->fresh();

        if ($order->status === OrderStatus::Pending) {
            $order = $this->confirmOrder->handle($order);
        }

        $order->loadMissing('items.legacyPlateEntitlement');

        foreach ($order->items as $item) {
            $entitlement = $item->legacyPlateEntitlement;

            if ($entitlement === null || $entitlement->status !== LegacyPlateEntitlementStatus::PendingPayment) {
                continue;
            }

            $entitlement->update([
                'status' => $entitlement->event_participant_id !== null
                    ? LegacyPlateEntitlementStatus::Linked
                    : LegacyPlateEntitlementStatus::Paid,
                'paid_at' => now(),
            ]);
        }

        CouponRedemption::query()
            ->where('order_id', $order->id)
            ->where('status', CouponRedemptionStatus::Reserved)
            ->update(['status' => CouponRedemptionStatus::Redeemed, 'redeemed_at' => now()]);

        return $order->fresh();
    }
}
