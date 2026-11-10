<?php

namespace App\Listeners\Notifications;

use App\Actions\Notifications\NotifyAdmins;
use App\Actions\Notifications\SendAthleteNotification;
use App\Enums\NotificationType;
use App\Events\Commerce\OrderDelivered;
use App\Events\Commerce\OrderPaid;
use App\Events\Commerce\OrderRefunded;
use App\Events\Commerce\OrderShipped;
use App\Models\Order;
use App\Models\User;
use Throwable;

/**
 * Customer side of the order lifecycle events (App\Events\Commerce\*, owned
 * by the payments module): in-app row + push to the buyer, category Pedidos.
 * The emails for the same events are sent by App\Listeners\Commerce\*; this
 * listener never sends mail. Never throws — a notification must not break
 * the payment flow that dispatched the event.
 */
class NotifyCustomerOfOrder
{
    public function __construct(private readonly SendAthleteNotification $send) {}

    public function handleOrderPaid(OrderPaid $event): void
    {
        $order = $event->order;

        $this->notify($order, NotificationType::OrderPaid, 'Pago confirmado', "Recibimos tu pago del pedido {$order->order_number}. Te avisaremos cuando vaya en camino.");
    }

    public function handleOrderShipped(OrderShipped $event): void
    {
        $order = $event->order;
        $tracking = filled($order->tracking_number)
            ? ' Guía '.trim(($order->carrier ? $order->carrier.' ' : '').$order->tracking_number).'.'
            : '';

        $this->notify($order, NotificationType::OrderShipped, 'Tu pedido va en camino', "Enviamos tu pedido {$order->order_number}.{$tracking}");
    }

    public function handleOrderDelivered(OrderDelivered $event): void
    {
        $order = $event->order;

        $this->notify($order, NotificationType::OrderDelivered, 'Pedido entregado', "Tu pedido {$order->order_number} fue entregado. Si incluye un Finisher Legacy Tag, ya puedes activarlo.");
    }

    public function handleOrderRefunded(OrderRefunded $event): void
    {
        $order = $event->order;
        $amount = NotifyAdmins::money($event->amountMinor, (string) ($order->currency ?? 'MXN'));

        $this->notify(
            $order,
            NotificationType::OrderRefunded,
            $event->full ? 'Reembolso realizado' : 'Reembolso parcial realizado',
            "Reembolsamos {$amount} de tu pedido {$order->order_number}. Puede tardar unos días en reflejarse en tu banco.",
        );
    }

    private function notify(Order $order, NotificationType $type, string $title, string $message): void
    {
        try {
            $recipient = $this->recipient($order);

            if ($recipient === null) {
                return;
            }

            $this->send->handle(
                $recipient,
                $title,
                $message,
                $type,
                route('store.orders.show', $order, absolute: false),
                ['kind' => 'order', 'order_uuid' => $order->uuid],
                push: true,
            );
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function recipient(Order $order): ?User
    {
        $order->loadMissing('user', 'athlete.user');

        return $order->user ?? $order->athlete?->user;
    }
}
