<?php

namespace App\Events\Realtime;

use App\Models\Order;
use Illuminate\Broadcasting\PrivateChannel;

/**
 * `order.updated` — fired by App\Observers\CommerceBroadcastObserver when an
 * order's status / payment_status / fulfillment_status changes.
 */
class OrderUpdated extends RealtimeEvent
{
    public const string NAME = 'order.updated';

    /** @var array<string, mixed> */
    public array $payload;

    public ?int $userId;

    public function __construct(Order $order)
    {
        $this->userId = $order->user_id;
        $this->payload = [
            'uuid' => $order->uuid,
            'order_number' => $order->order_number,
            'status' => $order->status?->value,
            'payment_status' => $order->payment_status?->value,
            'fulfillment_status' => $order->fulfillment_status?->value,
            'updated_at' => $order->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return array_values(array_filter([
            $this->userId !== null ? $this->userChannel($this->userId) : null,
            $this->adminCommerceChannel(),
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
