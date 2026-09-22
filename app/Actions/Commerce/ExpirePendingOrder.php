<?php

namespace App\Actions\Commerce;

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\Commerce\InventoryService;
use Illuminate\Support\Facades\DB;

/**
 * A pending, unpaid Order can't hold its inventory/coupon reservation
 * forever (consolidation brief §15-§21) — this is the ONE place that
 * decides an Order is too old to still be waiting for payment, and the
 * ONE place that actually releases what it was holding. Reuses
 * App\Actions\Commerce\CancelOrder (inventory release, entitlement
 * cancellation) instead of re-implementing it — there is no separate
 * "Expired" status: an expired Order is a Cancelled Order, distinguished
 * only by the fact that it was never paid (brief §16: no new enum just
 * for this).
 */
class ExpirePendingOrder
{
    public function __construct(
        private readonly CancelOrder $cancelOrder,
        private readonly InventoryService $inventory,
    ) {}

    /**
     * Locks the Order row before re-checking eligibility so two
     * overlapping runs (or a manual trigger racing the scheduler) can
     * never both cancel/release the same Order (consolidation brief §69:
     * no double inventory release).
     */
    public function handle(Order $order): ?Order
    {
        return DB::transaction(function () use ($order) {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->first();

            if ($locked === null || ! $this->isExpired($locked)) {
                return null;
            }

            return $this->cancelOrder->handle($locked, $this->inventory->defaultLocation());
        });
    }

    public function isExpired(Order $order): bool
    {
        if ($order->status !== OrderStatus::Pending) {
            return false;
        }

        if ($order->payment_status === OrderPaymentStatus::Paid) {
            return false;
        }

        $minutes = (int) config('finisher.commerce.order_payment_expiry_minutes', 60);

        return $order->created_at->addMinutes($minutes)->isPast();
    }
}
