<?php

namespace App\Mail\Commerce;

/**
 * "Pago confirmado": sent once when an Order becomes paid
 * (App\Events\Commerce\OrderPaid → App\Listeners\Commerce\SendOrderPaidEmail).
 */
class OrderPaidMail extends OrderMail
{
    protected function subjectLine(): string
    {
        return __('Pago confirmado · Pedido :number', ['number' => $this->order->order_number]);
    }

    protected function viewName(): string
    {
        return 'paid';
    }

    protected function preheader(): string
    {
        return __('Recibimos tu pago. Aquí está el resumen de tu pedido y los próximos pasos.');
    }
}
