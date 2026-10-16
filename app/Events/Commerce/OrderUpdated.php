<?php

namespace App\Events\Commerce;

use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * `order.updated` (spec §72) — the order owner (private user channel) and
 * the admin commerce workspace hear about status changes. REST/Inertia
 * stay the truth: the payload is only enough to decide "reload this".
 * Dispatched after commit, so nobody is told about a rolled-back change.
 */
class OrderUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Order $order) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return array_values(array_filter([
            $this->order->user_id !== null ? new PrivateChannel('App.Models.User.'.$this->order->user_id) : null,
            new PrivateChannel('commerce.admin'),
        ]));
    }

    public function broadcastAs(): string
    {
        return 'order.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'uuid' => $this->order->uuid,
            'order_number' => $this->order->order_number,
            'status' => $this->order->status->value,
            'payment_status' => $this->order->payment_status->value,
            'fulfillment_status' => $this->order->fulfillment_status->value,
        ];
    }
}
