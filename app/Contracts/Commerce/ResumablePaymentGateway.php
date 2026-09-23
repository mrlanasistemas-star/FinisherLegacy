<?php

namespace App\Contracts\Commerce;

use App\Models\Payment;
use App\Support\Commerce\OnlinePaymentIntent;
use App\Support\Commerce\PaymentWebhookOutcome;

/**
 * A gateway whose payment attempt lives on the provider side and can be
 * (a) handed back to the client again — so a user who closes the payment
 * sheet, loses connection, or has a card declined retries the SAME
 * attempt instead of creating a second charge — and (b) re-read
 * server-to-server, so the app can confirm a payment without waiting for
 * the webhook and without ever trusting what the client says happened.
 */
interface ResumablePaymentGateway extends PaymentGateway
{
    /**
     * The client payload for an attempt that can still be completed, or
     * null when the provider-side attempt is terminal/unusable.
     */
    public function resumePayment(Payment $payment): ?OnlinePaymentIntent;

    /**
     * The provider's current, authoritative status for this attempt.
     */
    public function fetchOutcome(Payment $payment): PaymentWebhookOutcome;
}
