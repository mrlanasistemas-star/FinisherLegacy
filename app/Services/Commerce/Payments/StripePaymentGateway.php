<?php

namespace App\Services\Commerce\Payments;

use App\Contracts\Commerce\ResumablePaymentGateway;
use App\Enums\PaymentStatus;
use App\Exceptions\InvalidPaymentWebhookSignatureException;
use App\Exceptions\PaymentGatewayNotConfiguredException;
use App\Models\Order;
use App\Models\Payment;
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
 * never a frontend-supplied value. Throws PaymentGatewayNotConfiguredException
 * when `config('finisher.payments.stripe')` has no secret set — that's the
 * expected path in dev/test/CI without real keys; the SDK calls themselves
 * are real and ready the moment keys are set via env.
 *
 * This is the gateway the mobile app uses: the returned `client_secret` is
 * consumed by `@stripe/stripe-react-native`'s PaymentSheet, card data goes
 * straight from the device to Stripe and never touches Laravel. The Order
 * is only ever marked paid by the signed webhook or by fetchOutcome()'s
 * server-to-server read — never by the client's own claim.
 */
class StripePaymentGateway implements ResumablePaymentGateway
{
    /**
     * PaymentIntent statuses where the same intent can still be confirmed
     * by the client (after a decline, Stripe returns the intent to
     * `requires_payment_method` — retrying reuses it, no second charge).
     */
    private const RESUMABLE_STATUSES = ['requires_payment_method', 'requires_confirmation', 'requires_action'];

    public function key(): string
    {
        return 'stripe';
    }

    public function createPayment(Order $order, array $paymentData = []): OnlinePaymentIntent
    {
        $intent = $this->client()->paymentIntents->create([
            'amount' => $order->total_minor,
            'currency' => strtolower($order->currency),
            'metadata' => [
                'order_uuid' => $order->uuid,
                'order_number' => $order->order_number,
            ],
            'automatic_payment_methods' => ['enabled' => true],
        ], [
            // Stripe-side idempotency on top of our own Payment reservation:
            // a retried create for the same local Payment row never makes a
            // second intent.
            'idempotency_key' => 'payment-'.($paymentData['payment_uuid'] ?? $order->uuid),
        ]);

        return $this->intentPayload($intent);
    }

    public function resumePayment(Payment $payment): ?OnlinePaymentIntent
    {
        if (blank($payment->provider_reference)) {
            return null;
        }

        $intent = $this->client()->paymentIntents->retrieve((string) $payment->provider_reference);

        if (! in_array($intent->status, self::RESUMABLE_STATUSES, true)) {
            return null;
        }

        return $this->intentPayload($intent);
    }

    public function fetchOutcome(Payment $payment): PaymentWebhookOutcome
    {
        $intent = $this->client()->paymentIntents->retrieve((string) $payment->provider_reference);

        return $this->outcomeFromIntent($intent, "sync:{$intent->id}:{$intent->status}");
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

    /**
     * Maps a PaymentIntent's live status (not a webhook event type) —
     * `processing` stays Pending with `provider_status` recorded so the app
     * can say "tu pago se está procesando" instead of "pendiente".
     */
    public function outcomeFromIntent(PaymentIntent $intent, string $eventId): PaymentWebhookOutcome
    {
        $status = match ($intent->status) {
            'succeeded' => PaymentStatus::Paid,
            'canceled' => PaymentStatus::Cancelled,
            default => PaymentStatus::Pending,
        };

        return new PaymentWebhookOutcome(
            provider: 'stripe',
            eventId: $eventId,
            providerReference: $intent->id,
            status: $status,
            amountMinor: $status === PaymentStatus::Paid ? $intent->amount_received : $intent->amount,
            currency: strtoupper($intent->currency),
            rawPayload: ['provider_status' => $intent->status],
        );
    }

    private function intentPayload(PaymentIntent $intent): OnlinePaymentIntent
    {
        return new OnlinePaymentIntent(
            providerReference: $intent->id,
            clientPayload: [
                'provider' => 'stripe',
                'client_secret' => $intent->client_secret,
                'publishable_key' => config('finisher.payments.stripe.key'),
                'merchant_display_name' => config('finisher.payments.merchant_display_name', 'Finisher Legacy'),
            ],
        );
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

    /**
     * Protected so a test double can override it without real keys or
     * network access.
     */
    protected function client(): StripeClient
    {
        $secret = config('finisher.payments.stripe.secret');

        if (blank($secret)) {
            throw new PaymentGatewayNotConfiguredException('stripe');
        }

        return new StripeClient($secret);
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
