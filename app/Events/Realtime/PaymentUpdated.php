<?php

namespace App\Events\Realtime;

use App\Models\Payment;
use Illuminate\Broadcasting\PrivateChannel;

/**
 * `payment.updated` — fired by App\Observers\CommerceBroadcastObserver when a
 * payment is created or its status changes. The webhook stays authoritative
 * for "paid"; this is only a hint so the checkout/order screen refreshes
 * without polling.
 */
class PaymentUpdated extends RealtimeEvent
{
    public const string NAME = 'payment.updated';

    /** @var array<string, mixed> */
    public array $payload;

    public ?int $userId;

    public function __construct(Payment $payment)
    {
        $order = $payment->order()->first(['id', 'uuid', 'user_id']);

        $this->userId = $order?->user_id;
        $this->payload = [
            'uuid' => $payment->uuid,
            'order_uuid' => $order?->uuid,
            'status' => $payment->status?->value,
            'provider' => $payment->provider?->value,
            'updated_at' => $payment->updated_at?->toIso8601String(),
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
