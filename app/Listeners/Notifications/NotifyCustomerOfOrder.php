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
use Closure;
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

        $this->notify(
            $order,
            NotificationType::OrderPaid,
            fn (): string => __('Pago confirmado'),
            fn (): string => __('Recibimos tu pago del pedido :number. Te avisaremos cuando vaya en camino.', ['number' => $order->order_number]),
        );
    }

    public function handleOrderShipped(OrderShipped $event): void
    {
        $order = $event->order;
        $tracking = filled($order->tracking_number)
            ? trim(($order->carrier ? $order->carrier.' ' : '').$order->tracking_number)
            : null;

        $this->notify(
            $order,
            NotificationType::OrderShipped,
            fn (): string => __('Tu pedido va en camino'),
            fn (): string => $tracking !== null
                ? __('Enviamos tu pedido :number. Guía :tracking.', ['number' => $order->order_number, 'tracking' => $tracking])
                : __('Enviamos tu pedido :number.', ['number' => $order->order_number]),
        );
    }

    public function handleOrderDelivered(OrderDelivered $event): void
    {
        $order = $event->order;

        $this->notify(
            $order,
            NotificationType::OrderDelivered,
            fn (): string => __('Pedido entregado'),
            fn (): string => __('Tu pedido :number fue entregado. Si incluye un Finisher Legacy Tag, ya puedes activarlo.', ['number' => $order->order_number]),
        );
    }

    public function handleOrderRefunded(OrderRefunded $event): void
    {
        $order = $event->order;
        $amount = NotifyAdmins::money($event->amountMinor, (string) ($order->currency ?? 'MXN'));

        $this->notify(
            $order,
            NotificationType::OrderRefunded,
            fn (): string => $event->full ? __('Reembolso realizado') : __('Reembolso parcial realizado'),
            fn (): string => __('Reembolsamos :amount de tu pedido :number. Puede tardar unos días en reflejarse en tu banco.', ['amount' => $amount, 'number' => $order->order_number]),
        );
    }

    /**
     * Title and message are closures: SendAthleteNotification renders them
     * in the buyer's language (the event may come from a webhook or an admin).
     *
     * @param  Closure(): string  $title
     * @param  Closure(): string  $message
     */
    private function notify(Order $order, NotificationType $type, Closure $title, Closure $message): void
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
