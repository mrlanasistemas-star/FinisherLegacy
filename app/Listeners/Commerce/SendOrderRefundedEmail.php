<?php

namespace App\Listeners\Commerce;

use App\Events\Commerce\OrderRefunded;
use App\Mail\Commerce\OrderRefundedMail;
use App\Mail\Support\OrderEmailLedger;

/**
 * OrderRefunded → "Reembolso procesado" email, once per (full/partial,
 * refunded amount): a webhook retry never repeats it, a later, different
 * refund of the same order does get its own email.
 */
class SendOrderRefundedEmail extends SendOrderEmailOnce
{
    public function handle(OrderRefunded $event): void
    {
        $reference = ($event->full ? 'full' : 'partial').':'.$event->amountMinor;

        $this->sendOnce(
            $event->order,
            OrderEmailLedger::REFUNDED,
            new OrderRefundedMail($event->order, $event->amountMinor, $event->full),
            $reference,
        );
    }
}
