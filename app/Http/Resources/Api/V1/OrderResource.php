<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * `payment_state` is the one field a client needs to render payment UX —
 * derived here, server-side, so no client re-derives it from three
 * separate statuses: pending | processing | paid | failed | cancelled |
 * refunded. "Order created" and "payment paid" stay distinct: `status`
 * is the order, `payment_state` is the money.
 *
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $latest = $this->relationLoaded('latestPayment') ? $this->latestPayment : null;
        $expiresAt = $this->status === OrderStatus::Pending && $this->payment_status !== OrderPaymentStatus::Paid
            ? $this->created_at->addMinutes((int) config('finisher.commerce.order_payment_expiry_minutes', 60))
            : null;

        return [
            'uuid' => $this->uuid,
            'order_number' => $this->order_number,
            'status' => $this->status->value,
            'payment_status' => $this->payment_status->value,
            'payment_state' => $this->paymentState($latest),
            'payable' => $this->isPayable($expiresAt),
            'fulfillment_status' => $this->fulfillment_status->value,
            'subtotal_minor' => $this->subtotal_minor,
            'discount_minor' => $this->discount_minor,
            'tax_minor' => $this->tax_minor,
            'total_minor' => $this->total_minor,
            'currency' => $this->currency,
            'coupon_code' => $this->coupon_code,
            'created_at' => $this->created_at->toIso8601String(),
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'expires_at' => $expiresAt?->toIso8601String(),
            'payment' => $latest === null ? null : [
                'status' => $latest->status->value,
                'provider' => $latest->provider->value,
                'method' => $latest->method->value,
                'paid_at' => $latest->paid_at?->toIso8601String(),
                'failed_at' => $latest->failed_at?->toIso8601String(),
            ],
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
        ];
    }

    private function paymentState(?Payment $latest): string
    {
        return match (true) {
            $this->payment_status === OrderPaymentStatus::Paid => 'paid',
            in_array($this->payment_status, [OrderPaymentStatus::Refunded, OrderPaymentStatus::PartiallyRefunded], true) => 'refunded',
            $this->status === OrderStatus::Cancelled => 'cancelled',
            $this->payment_status === OrderPaymentStatus::Failed => 'failed',
            $latest === null => 'pending',
            $latest->status === PaymentStatus::Failed => 'failed',
            $latest->status === PaymentStatus::Cancelled => 'failed',
            ($latest->metadata['provider_status'] ?? null) === 'processing' => 'processing',
            $latest->status === PaymentStatus::Authorized => 'processing',
            default => 'pending',
        };
    }

    private function isPayable(mixed $expiresAt): bool
    {
        if ($this->payment_status === OrderPaymentStatus::Paid) {
            return false;
        }

        if (in_array($this->status, [OrderStatus::Cancelled, OrderStatus::Completed], true)) {
            return false;
        }

        return $expiresAt === null || $expiresAt->isFuture();
    }
}
