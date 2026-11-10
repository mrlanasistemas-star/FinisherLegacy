<?php

namespace App\Events\Commerce;

use App\Models\Order;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Domain event: a payment of this Order was refunded by the provider
 * (App\Actions\Commerce\ProcessPaymentWebhook::applyRefund), fully or
 * partially. `amountMinor` is the refunded amount in the Order's currency
 * (cumulative, as the provider reports it; the whole payment for a full
 * refund without a reported figure). Dispatched after commit.
 *
 * Listeners: customer email (App\Listeners\Commerce\SendOrderRefundedEmail),
 * admin alerts / push (other modules). Keep the name and payload stable.
 */
class OrderRefunded implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly Order $order,
        public readonly int $amountMinor,
        public readonly bool $full,
    ) {}
}
