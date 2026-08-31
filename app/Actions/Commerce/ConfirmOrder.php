<?php

namespace App\Actions\Commerce;

use App\Enums\OrderStatus;
use App\Models\Order;

/**
 * Explicit commercial-lifecycle transition, independent of payment_status
 * (brief §61-§63) — called once an Order is considered accepted (in
 * practice: right after a payment settles, but never inferred from
 * payment_status directly by any other reader).
 */
class ConfirmOrder
{
    public function handle(Order $order): Order
    {
        if ($order->status !== OrderStatus::Pending) {
            throw new \RuntimeException("Solo un pedido pendiente puede confirmarse (estado actual: {$order->status->value}).");
        }

        $order->update(['status' => OrderStatus::Confirmed, 'confirmed_at' => now()]);

        return $order->fresh();
    }
}
