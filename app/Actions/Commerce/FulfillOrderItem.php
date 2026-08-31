<?php

namespace App\Actions\Commerce;

use App\Enums\FulfillmentStatus;
use App\Models\InventoryLocation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Commerce\InventoryService;
use Illuminate\Support\Facades\DB;

/**
 * This project's deliberately simple stand-in for a dedicated Fulfillment
 * model (brief §104-§106): commits the inventory reservation into an
 * actual stock decrease, marks the item fulfilled, and — for a QR-capable
 * product with a known Athlete — mints one AthleteOwnedProduct per unit
 * (brief §90/§109). Idempotent: calling it twice on an already-fulfilled
 * item is a no-op.
 */
class FulfillOrderItem
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly CreateAthleteOwnedProduct $createOwnedProduct,
    ) {}

    public function handle(OrderItem $item, InventoryLocation $location): OrderItem
    {
        if ($item->fulfilled_at !== null) {
            return $item;
        }

        return DB::transaction(function () use ($item, $location) {
            $item->loadMissing('order.athlete', 'product', 'productVariant');

            if ($item->productVariant !== null && $item->product->tracks_inventory) {
                $this->inventory->commitSale($item->productVariant, $location, $item->quantity, OrderItem::class, $item->id);
            }

            $item->update(['fulfilled_at' => now()]);
            $item = $item->fresh(['order', 'product', 'productVariant']);

            $athlete = $item->order->athlete;

            if ($item->product->qr_capable && $athlete !== null) {
                for ($i = 0; $i < $item->quantity; $i++) {
                    $this->createOwnedProduct->handle($athlete, $item);
                }
            }

            $this->syncOrderFulfillmentStatus($item->order);

            return $item->fresh();
        });
    }

    private function syncOrderFulfillmentStatus(Order $order): void
    {
        $order->loadMissing('items');
        $total = $order->items->count();
        $fulfilled = $order->items->filter(fn (OrderItem $i) => $i->fulfilled_at !== null)->count();

        $status = match (true) {
            $fulfilled === 0 => FulfillmentStatus::Unfulfilled,
            $fulfilled === $total => FulfillmentStatus::Fulfilled,
            default => FulfillmentStatus::PartiallyFulfilled,
        };

        $order->update(['fulfillment_status' => $status]);
    }
}
