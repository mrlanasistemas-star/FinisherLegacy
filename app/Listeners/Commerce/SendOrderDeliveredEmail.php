<?php

namespace App\Listeners\Commerce;

use App\Events\Commerce\OrderDelivered;
use App\Mail\Commerce\OrderDeliveredMail;
use App\Mail\Support\OrderEmailLedger;

/**
 * OrderDelivered → "Pedido entregado" email, exactly once per order.
 */
class SendOrderDeliveredEmail extends SendOrderEmailOnce
{
    public function handle(OrderDelivered $event): void
    {
        $this->sendOnce($event->order, OrderEmailLedger::DELIVERED, new OrderDeliveredMail($event->order));
    }
}
