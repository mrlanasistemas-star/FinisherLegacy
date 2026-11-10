<?php

namespace App\Support\Commerce;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use Carbon\CarbonInterface;

/**
 * An order's journey in the customer's words (spec §7 "Mis pedidos"):
 * Pendiente de pago → Pagado → Preparando → Enviado / Listo para recoger →
 * Entregado. Derived from the three independent order states (status,
 * payment_status, fulfillment_status) — it never writes anything, it only
 * tells the customer where their order is.
 */
class OrderProgress
{
    /**
     * @return array{
     *     steps: list<array{key: string, label: string, state: 'done'|'current'|'upcoming', at: string|null}>,
     *     closed: array{label: string, description: string, tone: 'muted'|'warning'}|null,
     *     headline: string,
     * }
     */
    public static function for(Order $order, ?CarbonInterface $paidAt = null): array
    {
        $paid = in_array($order->payment_status, [
            OrderPaymentStatus::Paid,
            OrderPaymentStatus::Authorized,
            OrderPaymentStatus::PartiallyRefunded,
        ], true);

        $delivered = $order->fulfillment_status === FulfillmentStatus::Fulfilled
            || $order->status === OrderStatus::Completed;
        $partlyDelivered = $order->fulfillment_status === FulfillmentStatus::PartiallyFulfilled;

        $reached = match (true) {
            $delivered => 4,
            $partlyDelivered => 3,
            $paid => 2,
            default => 0,
        };

        $labels = [
            'pending_payment' => __('Pendiente de pago'),
            'paid' => __('Pagado'),
            'preparing' => __('Preparando'),
            'on_its_way' => self::handoffLabel($order),
            'delivered' => __('Entregado'),
        ];

        $dates = [
            'pending_payment' => $order->created_at,
            'paid' => $paidAt ?? $order->confirmed_at,
            'preparing' => null,
            'on_its_way' => null,
            'delivered' => $order->completed_at,
        ];

        $steps = [];
        foreach (array_keys($labels) as $index => $key) {
            $state = match (true) {
                $index < $reached, $index === $reached && $index === 4 => 'done',
                $index === $reached => 'current',
                default => 'upcoming',
            };

            $steps[] = [
                'key' => $key,
                'label' => $labels[$key],
                'state' => $state,
                'at' => $state === 'done' ? $dates[$key]?->toIso8601String() : null,
            ];
        }

        $closed = match (true) {
            $order->status === OrderStatus::Cancelled => [
                'label' => __('Pedido cancelado'),
                'description' => __('Este pedido se canceló. Si pagaste algo, te contactaremos para el reembolso.'),
                'tone' => 'muted',
            ],
            $order->payment_status === OrderPaymentStatus::Refunded => [
                'label' => __('Reembolsado'),
                'description' => __('Te devolvimos el importe de este pedido.'),
                'tone' => 'muted',
            ],
            $order->payment_status === OrderPaymentStatus::Failed => [
                'label' => __('El pago no se completó'),
                'description' => __('Tu banco no aprobó el pago. Puedes intentarlo de nuevo.'),
                'tone' => 'warning',
            ],
            default => null,
        };

        $current = collect($steps)->firstWhere('state', 'current');

        return [
            'steps' => $steps,
            'closed' => $closed,
            'headline' => $closed['label'] ?? ($delivered ? __('Entregado') : ($current['label'] ?? __('Pendiente de pago'))),
        ];
    }

    private static function handoffLabel(Order $order): string
    {
        return match (data_get($order->customer_snapshot, 'delivery_method')) {
            'pickup', 'event_pickup', 'kit_pickup' => __('Listo para recoger'),
            'shipping', 'delivery' => __('Enviado||pedido enviado por paquetería'),
            default => __('Enviado o listo para recoger'),
        };
    }
}
