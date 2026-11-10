<?php

namespace App\Events\Commerce;

use App\Models\Order;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Domain event: an admin registered the shipment of an Order (its
 * `shipped_at` went from empty to a date in Pedidos → Envío,
 * App\Http\Controllers\Admin\Commercial\OrderOperationsController).
 * Carrier/tracking number, if captured, are on the Order itself.
 */
class OrderShipped implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly Order $order) {}
}
