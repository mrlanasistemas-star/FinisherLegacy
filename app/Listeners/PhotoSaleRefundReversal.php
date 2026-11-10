<?php

namespace App\Listeners;

use App\Actions\Photos\ReversePhotoSale;
use App\Events\Commerce\OrderRefunded;

/**
 * Refunds reverse the photographer's net: when an Order is FULLY refunded
 * (App\Events\Commerce\OrderRefunded, dispatched after commit by
 * ProcessPaymentWebhook) every PhotoSale of that Order is reversed. A
 * partial refund does not say which items were refunded, so it reverses
 * nothing automatically — an admin reverses the affected sale from
 * Administración → Fotógrafos (docs/renovacion/comisiones-fotografos.md).
 * Auto-discovered (app/Listeners). Idempotent.
 */
class PhotoSaleRefundReversal
{
    public function __construct(private readonly ReversePhotoSale $reverse) {}

    public function handle(OrderRefunded $event): void
    {
        if (! $event->full) {
            return;
        }

        $this->reverse->forOrder($event->order, 'Reembolso total del pedido '.$event->order->order_number);
    }
}
