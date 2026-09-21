<?php

namespace App\Actions\Commerce;

use App\Enums\CouponRedemptionStatus;
use App\Enums\LegacyPlateEntitlementStatus;
use App\Enums\OrderStatus;
use App\Models\CouponRedemption;
use App\Models\InventoryLocation;
use App\Models\Order;
use App\Services\Commerce\InventoryService;
use Illuminate\Support\Facades\DB;

/**
 * Releases every reserved unit back to available stock and cancels any
 * LegacyPlateEntitlement this Order created — never touches a completed
 * Order (brief §151: history is never destroyed, only new state recorded).
 *
 * A coupon this Order reserved is only released if it was never actually
 * redeemed (i.e. the Order was cancelled before payment cleared, brief
 * §36) — an already-`redeemed` (paid) coupon stays spent even if the
 * Order is later cancelled, so a buy/cancel/rebuy cycle can't be used to
 * reuse a single-use code.
 */
class CancelOrder
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function handle(Order $order, InventoryLocation $location): Order
    {
        if ($order->status === OrderStatus::Completed) {
            throw new \RuntimeException('Un pedido completado no puede cancelarse.');
        }

        return DB::transaction(function () use ($order, $location) {
            $order->loadMissing('items.productVariant', 'items.legacyPlateEntitlement');

            foreach ($order->items as $item) {
                if ($item->productVariant !== null && $item->fulfilled_at === null) {
                    $this->inventory->release($item->productVariant, $location, $item->quantity, Order::class, $order->id);
                }

                $item->legacyPlateEntitlement?->update(['status' => LegacyPlateEntitlementStatus::Cancelled]);
            }

            CouponRedemption::query()
                ->where('order_id', $order->id)
                ->where('status', CouponRedemptionStatus::Reserved)
                ->update(['status' => CouponRedemptionStatus::Released, 'released_at' => now()]);

            $order->update(['status' => OrderStatus::Cancelled, 'cancelled_at' => now()]);

            return $order->fresh();
        });
    }
}
