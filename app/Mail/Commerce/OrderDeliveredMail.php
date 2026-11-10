<?php

namespace App\Mail\Commerce;

/**
 * "Pedido entregado": sent once when an admin confirms delivery
 * (App\Events\Commerce\OrderDelivered → App\Listeners\Commerce\SendOrderDeliveredEmail).
 */
class OrderDeliveredMail extends OrderMail
{
    protected function subjectLine(): string
    {
        return "Pedido {$this->order->order_number} entregado";
    }

    protected function viewName(): string
    {
        return 'delivered';
    }

    protected function preheader(): string
    {
        return 'Tu pedido ya fue entregado. Actívalo para empezar a guardar tu historia.';
    }
}
