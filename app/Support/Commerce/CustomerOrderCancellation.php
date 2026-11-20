<?php

namespace App\Support\Commerce;

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;

/**
 * When the buyer may cancel their own order (app "Cancelar pedido").
 *
 * Only an order that was never paid, and has no card attempt that could
 * still complete: an OpenPay charge waiting on 3-D Secure can settle up to
 * the in-app checkout session length after it started, so cancelling under
 * it could leave money charged on a cancelled order. Paid orders are
 * refunded by the team (admin), never self-cancelled.
 */
final class CustomerOrderCancellation
{
    public static function allowed(Order $order, ?Payment $latest): bool
    {
        if ($order->status !== OrderStatus::Pending) {
            return false;
        }

        if (in_array($order->payment_status, [OrderPaymentStatus::Paid, OrderPaymentStatus::Authorized, OrderPaymentStatus::PartiallyRefunded, OrderPaymentStatus::Refunded], true)) {
            return false;
        }

        return ! self::paymentInFlight($latest);
    }

    public static function paymentInFlight(?Payment $latest): bool
    {
        if ($latest === null || ! in_array($latest->status, [PaymentStatus::Pending, PaymentStatus::Authorized], true)) {
            return false;
        }

        $minutes = max(1, (int) config('finisher.payments.app_checkout.session_minutes', 30));

        return ($latest->updated_at ?? $latest->created_at)?->gt(now()->subMinutes($minutes)) ?? false;
    }
}
