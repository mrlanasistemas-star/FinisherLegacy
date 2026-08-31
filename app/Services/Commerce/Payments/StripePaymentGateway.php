<?php

namespace App\Services\Commerce\Payments;

use App\Contracts\Commerce\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Exceptions\InvalidPaymentWebhookSignatureException;
use App\Exceptions\PaymentGatewayNotConfiguredException;
use App\Models\Order;
use App\Support\Commerce\OnlinePaymentIntent;
use App\Support\Commerce\PaymentWebhookOutcome;
use Illuminate\Http\Request;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\PaymentIntent;
use Stripe\StripeClient;
use Stripe\Webhook;
use UnexpectedValueException;

/**
 * Real implementation using the official `stripe/stripe-php` SDK (brief
 * §22/§67/§93). Amount always comes from the already-computed `Order` —
 * never a frontend-supplied value. Both methods throw
 * PaymentGatewayNotConfiguredException when `config('finisher.payments.stripe')`
 * has no secret/webhook secret set — this project has no real Stripe
 * account credentials, so that's the expected path in dev/test/CI; the
 * SDK call itself is real and correct, ready the moment real keys are
 * set via env (brief §71: "no inventar credenciales", not "no usar el
 * SDK real").
 */
class StripePaymentGateway implements PaymentGateway
{
    public function key(): string
    {
        return 'stripe';
    }

    public function createPayment(Order $order): OnlinePaymentIntent
    {
        $secret = $this->secretKey();

        $client = new StripeClient($secret);

        $intent = $client->paymentIntents->create([
            'amount' => $order->total_minor,
            'currency' => strtolower($order->currency),
            'metadata' => [
                'order_uuid' => $order->uuid,
                'order_number' => $order->order_number,
            ],
            'automatic_payment_methods' => ['enabled' => true],
        ]);

        return new OnlinePaymentIntent(
            providerReference: $intent->id,
            clientPayload: [
                'client_secret' => $intent->client_secret,
                'publishable_key' => config('finisher.payments.stripe.key'),
            ],
        );
    }

    public function handleWebhook(Request $request): PaymentWebhookOutcome
    {
        $webhookSecret = $this->webhookSecret();
        $signature = (string) $request->header('Stripe-Signature');

        try {
            $event = Webhook::constructEvent($request->getContent(), $signature, $webhookSecret);
        } catch (UnexpectedValueException|SignatureVerificationException) {
            throw new InvalidPaymentWebhookSignatureException;
        }

        return $this->mapEvent($event);
    }

    public function isConfigured(): bool
    {
        return filled(config('finisher.payments.stripe.secret'))
            && filled(config('finisher.payments.stripe.webhook_secret'));
    }

    private function mapEvent(Event $event): PaymentWebhookOutcome
    {
        /** @var PaymentIntent $intent */
        $intent = $event->data->object;

        $status = match ($event->type) {
            'payment_intent.succeeded' => PaymentStatus::Paid,
            'payment_intent.payment_failed' => PaymentStatus::Failed,
            'payment_intent.canceled' => PaymentStatus::Cancelled,
            default => PaymentStatus::Pending,
        };

        // amount_received reflects what Stripe actually captured; for a
        // failed/pending event that's 0, so fall back to `amount` (what
        // was requested) so ProcessPaymentWebhook's amount check still has
        // something meaningful to compare against the expected Payment.
        $amountMinor = $status === PaymentStatus::Paid ? $intent->amount_received : $intent->amount;

        return new PaymentWebhookOutcome(
            provider: 'stripe',
            eventId: $event->id,
            providerReference: $intent->id,
            status: $status,
            amountMinor: $amountMinor,
            currency: strtoupper($intent->currency),
            rawPayload: $event->toArray(),
        );
    }

    private function secretKey(): string
    {
        $secret = config('finisher.payments.stripe.secret');

        if (blank($secret)) {
            throw new PaymentGatewayNotConfiguredException('stripe');
        }

        return $secret;
    }

    private function webhookSecret(): string
    {
        $secret = config('finisher.payments.stripe.webhook_secret');

        if (blank($secret)) {
            throw new PaymentGatewayNotConfiguredException('stripe');
        }

        return $secret;
    }
}
