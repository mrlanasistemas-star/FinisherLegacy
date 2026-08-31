<?php

namespace App\Actions\Commerce;

use App\Enums\LegacyPlateEntitlementStatus;
use App\Enums\OrderStatus;
use App\Models\InventoryLocation;
use App\Models\Order;
use App\Services\Commerce\InventoryService;
use Illuminate\Support\Facades\DB;

/**
 * Releases every reserved unit back to available stock and cancels any
 * LegacyPlateEntitlement this Order created — never touches a completed
 * Order (brief §151: history is never destroyed, only new state recorded).
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

            $order->update(['status' => OrderStatus::Cancelled, 'cancelled_at' => now()]);

            return $order->fresh();
        });
    }
}
