<?php

namespace App\Mail\Commerce;

/**
 * "Tu pedido va en camino": sent once when an admin registers the shipment
 * (App\Events\Commerce\OrderShipped → App\Listeners\Commerce\SendOrderShippedEmail).
 */
class OrderShippedMail extends OrderMail
{
    protected function subjectLine(): string
    {
        return __('Tu pedido :number va en camino', ['number' => $this->order->order_number]);
    }

    protected function viewName(): string
    {
        return 'shipped';
    }

    protected function preheader(): string
    {
        return __('Tu pedido salió hacia ti. Aquí tienes los datos de envío.');
    }
}
