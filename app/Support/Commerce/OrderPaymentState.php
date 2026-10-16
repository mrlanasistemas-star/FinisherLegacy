<?php

namespace App\Support\Commerce;

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Carbon\CarbonInterface;

/**
 * The one server-side derivation of "what should the customer see about
 * the money" — pending | processing | paid | failed | cancelled | refunded
 * — shared by `/api/v1` (OrderResource) and the web checkout's
 * confirmation page, so neither client re-derives it from three statuses
 * and nobody ever infers "paid" from a redirect.
 */
final class OrderPaymentState
{
    public static function for(Order $order, ?Payment $latest): string
    {
        return match (true) {
            $order->payment_status === OrderPaymentStatus::Paid => 'paid',
            in_array($order->payment_status, [OrderPaymentStatus::Refunded, OrderPaymentStatus::PartiallyRefunded], true) => 'refunded',
            $order->status === OrderStatus::Cancelled => 'cancelled',
            $order->payment_status === OrderPaymentStatus::Failed => 'failed',
            $latest === null => 'pending',
            $latest->status === PaymentStatus::Failed => 'failed',
            $latest->status === PaymentStatus::Cancelled => 'failed',
            ($latest->metadata['provider_status'] ?? null) === 'processing' => 'processing',
            $latest->status === PaymentStatus::Authorized => 'processing',
            default => 'pending',
        };
    }

    public static function expiresAt(Order $order): ?CarbonInterface
    {
        return $order->status === OrderStatus::Pending && $order->payment_status !== OrderPaymentStatus::Paid
            ? $order->created_at->copy()->addMinutes((int) config('finisher.commerce.order_payment_expiry_minutes', 60))
            : null;
    }

    public static function isPayable(Order $order): bool
    {
        if ($order->payment_status === OrderPaymentStatus::Paid) {
            return false;
        }

        if (in_array($order->status, [OrderStatus::Cancelled, OrderStatus::Completed], true)) {
            return false;
        }

        $expiresAt = self::expiresAt($order);

        return $expiresAt === null || $expiresAt->isFuture();
    }
}
