<?php

namespace App\Actions\Commerce;

use App\Enums\LegacyPlateEntitlementStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Models\Order;

/**
 * Shared by RegisterManualPayment and ProcessPaymentWebhook so "what
 * happens once an Order is fully paid" is defined in exactly one place
 * (brief §83: compose, no God Action) — flips payment_status, confirms the
 * Order if it's still pending, and moves any Legacy Plate entitlement this
 * Order funded from pending_payment to paid (brief §81/§100: paid enables
 * production, it never triggers it).
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

        return $order->fresh();
    }
}
