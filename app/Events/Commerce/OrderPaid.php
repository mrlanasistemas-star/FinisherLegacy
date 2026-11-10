<?php

namespace App\Events\Commerce;

use App\Models\Order;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Domain event: an Order just became fully paid (App\Actions\Commerce\
 * MarkOrderPaid — reached from the OpenPay/Stripe webhooks, SyncOnlinePayment
 * and manual admin payments). Fired only on the transition into `paid`, never
 * for a replay, and only once the surrounding DB transaction has committed.
 *
 * Listeners: customer email (App\Listeners\Commerce\SendOrderPaidEmail),
 * admin alerts / push (other modules). Keep the name and payload stable.
 */
class OrderPaid implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly Order $order) {}
}
