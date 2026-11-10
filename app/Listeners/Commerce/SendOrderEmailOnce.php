<?php

namespace App\Listeners\Commerce;

use App\Mail\Commerce\OrderMail;
use App\Mail\Support\OrderEmailLedger;
use App\Mail\Support\OrderSummary;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Shared "claim, then queue" step of the SendOrder*Email listeners: claims
 * the (order, kind, reference) row in the idempotency ledger and only the
 * first claimer queues the Mailable. If queueing itself fails the claim is
 * released so a later retry can still send; a mail problem never breaks the
 * payment/fulfillment flow that fired the event.
 */
abstract class SendOrderEmailOnce
{
    protected function sendOnce(Order $order, string $kind, OrderMail $mail, string $reference = ''): void
    {
        $recipient = OrderSummary::recipient($order);

        if ($recipient === null || ! OrderEmailLedger::claim($order, $kind, $recipient, $reference)) {
            return;
        }

        try {
            // The address is a plain string (guest checkouts have no account),
            // so the language is set explicitly; it travels with the queued mail.
            Mail::to($recipient, $order->customer_snapshot['name'] ?? null)
                ->locale(OrderSummary::locale($order))
                ->send($mail);
        } catch (Throwable $e) {
            DB::table('order_email_deliveries')
                ->where('order_id', $order->id)
                ->where('kind', $kind)
                ->where('reference', $reference)
                ->delete();

            report($e);
        }
    }
}
