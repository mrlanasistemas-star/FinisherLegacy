<?php

namespace App\Listeners\Commerce;

use App\Events\Commerce\OrderPaid;
use App\Mail\Commerce\OrderPaidMail;
use App\Mail\Support\OrderEmailLedger;

/**
 * OrderPaid → "Pago confirmado" email to the buyer, exactly once per order.
 */
class SendOrderPaidEmail extends SendOrderEmailOnce
{
    public function handle(OrderPaid $event): void
    {
        $this->sendOnce($event->order, OrderEmailLedger::PAID, new OrderPaidMail($event->order));
    }
}
