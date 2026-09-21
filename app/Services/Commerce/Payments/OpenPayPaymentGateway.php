<?php

namespace App\Services\Commerce\Payments;

use App\Contracts\Commerce\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Exceptions\InvalidPaymentWebhookSignatureException;
use App\Exceptions\MissingPaymentTokenException;
use App\Exceptions\PaymentGatewayNotConfiguredException;
use App\Models\Order;
use App\Support\Commerce\OnlinePaymentIntent;
use App\Support\Commerce\PaymentWebhookOutcome;
use Illuminate\Http\Request;
use Openpay\Data\Openpay;

/**
 * Real implementation using the official `openpay/sdk` Composer package
 * (brief §50-§52: "usar SOLO documentación/API oficial actual"). Amount
 * always comes from the already-computed `Order`, never the frontend.
 *
 * Openpay.js tokenizes the card entirely client-side into a `token_id`
 * (brief §143: "no exponer private key") — this class never sees raw card
 * data, only that token plus the `device_session_id` Openpay's fraud
 * detection JS also produces client-side.
 *
 * Openpay's webhook notifications carry no cryptographic signature (its
 * own docs describe only a one-time dashboard "verification code"
 * handshake, handled by App\Http\Controllers\Api\Webhooks\
 * OpenPayWebhookController before this class is ever called) — so unlike
 * Stripe, trust here comes from re-fetching the charge from Openpay's API
 * by id inside handleWebhook(), never from the webhook body's own
 * amount/status. This mirrors Openpay's own official Magento/WooCommerce
 * plugin source, which does the same re-fetch rather than trusting the
 * POSTed payload.
 */
class OpenPayPaymentGateway implements PaymentGateway
{
    public function key(): string
    {
        return 'openpay';
    }

    public function createPayment(Order $order, array $paymentData = []): OnlinePaymentIntent
    {
        // Unreachable credentials is an environment/ops problem — checked
        // before the request's own token, which is only ever a client bug.
        $client = $this->client();

        $tokenId = $paymentData['token_id'] ?? null;
        $deviceSessionId = $paymentData['device_session_id'] ?? null;

        if (blank($tokenId) || blank($deviceSessionId)) {
            throw new MissingPaymentTokenException;
        }

        $charge = $client->charges->create([
            'method' => 'card',
            'source_id' => $tokenId,
            'device_session_id' => $deviceSessionId,
            'amount' => $order->total_minor / 100,
            'currency' => strtoupper($order->currency),
            'description' => "Orden {$order->order_number}",
            'order_id' => $order->uuid,
        ]);

        return new OnlinePaymentIntent(
            providerReference: $charge->id,
            clientPayload: [
                'status' => $charge->status,
                'public_key' => config('finisher.payments.openpay.public_key'),
                'merchant_id' => config('finisher.payments.openpay.merchant_id'),
            ],
        );
    }

    public function handleWebhook(Request $request): PaymentWebhookOutcome
    {
        $payload = json_decode($request->getContent(), true);
        $transactionId = $payload['transaction']['id'] ?? null;
        $type = $payload['type'] ?? null;

        if (! is_string($transactionId) || $transactionId === '' || ! is_string($type)) {
            throw new InvalidPaymentWebhookSignatureException;
        }

        // The webhook body is never trusted for amount/status — Openpay
        // sends no signature, so re-fetching the charge by id from
        // Openpay's own API is what makes this trustworthy (see class
        // docblock).
        $charge = $this->client()->charges->get($transactionId);

        $status = match ($charge->status) {
            'completed' => PaymentStatus::Paid,
            'failed', 'cancelled' => PaymentStatus::Failed,
            'refunded' => PaymentStatus::Refunded,
            default => PaymentStatus::Pending,
        };

        return new PaymentWebhookOutcome(
            provider: 'openpay',
            eventId: "{$transactionId}:{$type}",
            providerReference: $charge->id,
            status: $status,
            amountMinor: (int) round(((float) $charge->amount) * 100),
            currency: strtoupper((string) $charge->currency),
            rawPayload: is_array($payload) ? $payload : [],
        );
    }

    public function isConfigured(): bool
    {
        return filled(config('finisher.payments.openpay.merchant_id'))
            && filled(config('finisher.payments.openpay.private_key'));
    }

    /**
     * Untyped on purpose — Openpay\Data\OpenpayApiResourceBase exposes
     * `charges`/`customers`/etc. only through `__get`, so PHPStan can't
     * know they exist from the class declaration alone. `mixed` reflects
     * that honestly instead of suppressing the resulting property-access
     * warning.
     */
    private function client(): mixed
    {
        if (! $this->isConfigured()) {
            throw new PaymentGatewayNotConfiguredException('openpay');
        }

        $client = Openpay::getInstance(
            config('finisher.payments.openpay.merchant_id'),
            config('finisher.payments.openpay.private_key'),
            config('finisher.payments.openpay.country'),
            request()->ip(),
        );

        Openpay::setProductionMode((bool) config('finisher.payments.openpay.production_mode'));

        return $client;
    }
}
