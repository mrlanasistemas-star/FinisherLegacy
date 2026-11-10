<?php

namespace App\Listeners\Commerce;

use App\Events\Commerce\OrderShipped;
use App\Mail\Commerce\OrderShippedMail;
use App\Mail\Support\OrderEmailLedger;

/**
 * OrderShipped → "Tu pedido va en camino" email, exactly once per order.
 */
class SendOrderShippedEmail extends SendOrderEmailOnce
{
    public function handle(OrderShipped $event): void
    {
        $this->sendOnce($event->order, OrderEmailLedger::SHIPPED, new OrderShippedMail($event->order));
    }
}
