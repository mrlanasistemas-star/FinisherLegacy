<?php

namespace App\Events\Commerce;

use App\Models\Order;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Domain event: an admin confirmed that an Order reached its buyer
 * (`delivered_at` set by OrderOperationsController::markDelivered). Every
 * shipped Finisher Legacy Tag is now `delivered`, i.e. ready to activate.
 */
class OrderDelivered implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly Order $order) {}
}
