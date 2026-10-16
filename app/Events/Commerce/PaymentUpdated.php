<?php

namespace App\Events\Commerce;

use App\Models\Payment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * `payment.updated` (spec §72) — never carries provider secrets, card data
 * or references; only the state a screen needs to refresh itself.
 */
class PaymentUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Payment $payment) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $userId = $this->payment->order?->user_id;

        return array_values(array_filter([
            $userId !== null ? new PrivateChannel('App.Models.User.'.$userId) : null,
            new PrivateChannel('commerce.admin'),
        ]));
    }

    public function broadcastAs(): string
    {
        return 'payment.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'uuid' => $this->payment->uuid,
            'order_uuid' => $this->payment->order?->uuid,
            'status' => $this->payment->status->value,
            'provider' => $this->payment->provider->value,
        ];
    }
}
