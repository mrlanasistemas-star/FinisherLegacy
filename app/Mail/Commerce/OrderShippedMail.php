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
        return "Tu pedido {$this->order->order_number} va en camino";
    }

    protected function viewName(): string
    {
        return 'shipped';
    }

    protected function preheader(): string
    {
        return 'Tu pedido salió hacia ti. Aquí tienes los datos de envío.';
    }
}
