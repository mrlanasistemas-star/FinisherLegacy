<?php

namespace App\Actions\Commerce;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use RuntimeException;

/**
 * Terminal success state — requires the Order to already be confirmed and
 * fulfilled (brief §61/§104-§107); never set as a side effect of payment
 * alone.
 */
class CompleteOrder
{
    public function handle(Order $order): Order
    {
        if ($order->status !== OrderStatus::Confirmed) {
            throw new RuntimeException('Solo un pedido confirmado puede completarse.');
        }

        if ($order->fulfillment_status !== FulfillmentStatus::Fulfilled) {
            throw new RuntimeException('El pedido debe estar completamente surtido antes de completarse.');
        }

        $order->update(['status' => OrderStatus::Completed, 'completed_at' => now()]);

        return $order->fresh();
    }
}
