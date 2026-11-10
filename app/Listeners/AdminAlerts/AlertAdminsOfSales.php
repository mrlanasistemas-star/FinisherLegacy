<?php

namespace App\Listeners\AdminAlerts;

use App\Actions\Notifications\NotifyAdmins;
use App\Enums\AdminAlertCategory;
use App\Events\Commerce\OrderPaid;
use App\Events\Commerce\OrderRefunded;
use App\Models\Order;

/**
 * Ventas alerts from the payments module's domain events: an order was paid,
 * an order was refunded (App\Events\Commerce\*). Payment failures come from
 * App\Listeners\AdminAlerts\ModelAlerts (Payment model observer).
 */
class AlertAdminsOfSales
{
    public function __construct(private readonly NotifyAdmins $notify) {}

    public function handleOrderPaid(OrderPaid $event): void
    {
        $order = $event->order;

        $this->notify->handle(
            AdminAlertCategory::Ventas,
            'order_paid',
            'Pedido pagado',
            "{$order->order_number} · ".NotifyAdmins::money((int) $order->total_minor, (string) $order->currency).' · '.$this->customer($order),
            route('admin.orders.show', $order, absolute: false),
            ['order_uuid' => $order->uuid],
            level: 'success',
        );
    }

    public function handleOrderRefunded(OrderRefunded $event): void
    {
        $order = $event->order;

        $this->notify->handle(
            AdminAlertCategory::Ventas,
            'order_refunded',
            $event->full ? 'Reembolso total' : 'Reembolso parcial',
            "{$order->order_number} · ".NotifyAdmins::money($event->amountMinor, (string) $order->currency).' reembolsados.',
            route('admin.orders.show', $order, absolute: false),
            ['order_uuid' => $order->uuid],
            level: 'warning',
        );
    }

    private function customer(Order $order): string
    {
        $snapshot = is_array($order->customer_snapshot) ? $order->customer_snapshot : [];

        return (string) ($snapshot['name'] ?? $order->user->name ?? 'Cliente');
    }
}
