<?php

namespace App\Services\Commerce\Payments;

use App\Contracts\Commerce\ResumablePaymentGateway;
use App\Enums\PaymentStatus;
use App\Exceptions\InvalidPaymentWebhookSignatureException;
use App\Exceptions\MissingPaymentTokenException;
use App\Exceptions\PaymentDeclinedException;
use App\Exceptions\PaymentGatewayNotConfiguredException;
use App\Models\Order;
use App\Models\Payment;
use App\Support\Commerce\OnlinePaymentIntent;
use App\Support\Commerce\PaymentWebhookOutcome;
use Illuminate\Http\Request;
use Openpay\Data\OpenpayApiError;
use Openpay\Data\OpenpayApiResourceBase;
use stdClass;

/**
 * Real implementation using the official `openpay/sdk` Composer package.
 * Amount always comes from the already-computed `Order`, never the
 * frontend.
 *
 * Openpay.js tokenizes the card entirely client-side into a `token_id` —
 * this class never sees raw card data, only that token plus the
 * `device_session_id` Openpay's anti-fraud script (openpay-data.js)
 * produces client-side.
 *
 * 3D Secure (OpenPay MX): with `use_3d_secure` + `redirect_url` the charge
 * comes back `charge_pending` with `payment_method.type = redirect` and a
 * `payment_method.url` the shopper must visit; OpenPay then sends them to
 * `redirect_url?id={charge_id}`. When 3DS isn't forced, a charge rejected
 * by the anti-fraud system (error 3005) is retried once with 3DS using the
 * same token — exactly what OpenPay's own official WooCommerce plugin does.
 *
 * Openpay's webhook notifications carry no cryptographic signature — so
 * trust comes from re-fetching the charge from Openpay's API by id
 * (handleWebhook()/fetchOutcome()), never from the webhook body's own
 * amount/status. Same approach as Openpay's official plugins.
 */
class OpenPayPaymentGateway implements ResumablePaymentGateway
{
    /**
     * OpenPay error codes → what the shopper reads. Wording follows
     * OpenPay's own official plugin (OpenpayErrorManager).
     *
     * @var array<int, string>
     */
    private const array DECLINE_MESSAGES = [
        1000 => 'El servicio de pagos no está disponible en este momento. Intenta de nuevo en unos minutos.',
        1004 => 'El servicio de pagos no está disponible en este momento. Intenta de nuevo en unos minutos.',
        1005 => 'El servicio de pagos no está disponible en este momento. Intenta de nuevo en unos minutos.',
        2004 => 'El número de tarjeta no es válido. Revísalo e intenta de nuevo.',
        2005 => 'La fecha de vencimiento de la tarjeta ya pasó.',
        2006 => 'Falta el código de seguridad (CVV) de la tarjeta.',
        2009 => 'El código de seguridad (CVV) no es válido.',
        2010 => 'No pudimos validar la tarjeta con 3D Secure. Intenta con otra tarjeta.',
        2011 => 'Este tipo de tarjeta no es aceptado.',
        3001 => 'Tu banco rechazó la tarjeta. Intenta con otra tarjeta.',
        3002 => 'La tarjeta está vencida. Intenta con otra tarjeta.',
        3003 => 'La tarjeta no tiene fondos suficientes.',
        3004 => 'Tu banco rechazó la tarjeta. Intenta con otra tarjeta.',
        3005 => 'Tu banco rechazó la tarjeta. Intenta con otra tarjeta.',
        3006 => 'La operación no está permitida para esta tarjeta.',
        3008 => 'La tarjeta no está habilitada para compras en línea.',
        3009 => 'La tarjeta fue reportada como perdida. Intenta con otra tarjeta.',
        3010 => 'El banco restringió la tarjeta. Comunícate con tu banco.',
        3011 => 'El banco solicitó retener la tarjeta. Comunícate con tu banco.',
        3012 => 'Tu banco requiere que autorices este pago. Comunícate con tu banco e intenta de nuevo.',
    ];

    public function __construct(private readonly OpenPayClientFactory $factory) {}

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

        $request = [
            'method' => 'card',
            'source_id' => (string) $tokenId,
            'device_session_id' => (string) $deviceSessionId,
            'amount' => round($order->total_minor / 100, 2),
            'currency' => strtoupper($order->currency),
            'description' => "Orden {$order->order_number}",
            // One OpenPay order_id per local Payment attempt — OpenPay
            // requires it to be unique per merchant, and the Order may
            // need several attempts (declined card, abandoned 3DS).
            'order_id' => (string) ($paymentData['payment_uuid'] ?? $order->uuid),
        ];

        if (($customer = $this->customer($order)) !== null) {
            $request['customer'] = $customer;
        }

        $threeDs = [
            'use_3d_secure' => true,
            'redirect_url' => route('store.checkout.confirmation', $order->uuid),
        ];

        $forced = (bool) config('finisher.payments.openpay.use_3d_secure');

        try {
            $charge = $client->charges->create($forced ? [...$request, ...$threeDs] : $request);
        } catch (OpenpayApiError $e) {
            if ($forced || (int) $e->getErrorCode() !== 3005) {
                throw $this->declined($e);
            }

            try {
                $charge = $client->charges->create([...$request, ...$threeDs]);
            } catch (OpenpayApiError $retry) {
                throw $this->declined($retry);
            }
        }

        return new OnlinePaymentIntent(
            providerReference: (string) $charge->id,
            clientPayload: $this->clientPayload($charge),
        );
    }

    public function resumePayment(Payment $payment): ?OnlinePaymentIntent
    {
        $charge = $this->client()->charges->get((string) $payment->provider_reference);
        $payload = $this->clientPayload($charge);

        // Only a 3DS charge still waiting for the shopper can be resumed —
        // a card token is single-use, everything else is terminal.
        if ($charge->status !== 'charge_pending' || $payload['redirect_url'] === null) {
            return null;
        }

        return new OnlinePaymentIntent((string) $charge->id, $payload);
    }

    public function fetchOutcome(Payment $payment): PaymentWebhookOutcome
    {
        $charge = $this->client()->charges->get((string) $payment->provider_reference);

        return $this->outcomeFromCharge($charge, "sync:{$charge->id}:{$charge->status}", ['provider_status' => $charge->status]);
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
        // Openpay's own API is what makes this trustworthy.
        $charge = $this->client()->charges->get($transactionId);

        return $this->outcomeFromCharge($charge, "{$transactionId}:{$type}", is_array($payload) ? $payload : []);
    }

    public function isConfigured(): bool
    {
        return filled(config('finisher.payments.openpay.merchant_id'))
            && filled(config('finisher.payments.openpay.private_key'));
    }

    /**
     * @param  array<string, mixed>  $rawPayload
     */
    private function outcomeFromCharge(OpenpayApiResourceBase|stdClass $charge, string $eventId, array $rawPayload): PaymentWebhookOutcome
    {
        $status = match ($this->field($charge, 'status')) {
            'completed' => PaymentStatus::Paid,
            'failed', 'cancelled' => PaymentStatus::Failed,
            'refunded' => PaymentStatus::Refunded,
            default => PaymentStatus::Pending,
        };

        return new PaymentWebhookOutcome(
            provider: 'openpay',
            eventId: $eventId,
            providerReference: (string) $this->field($charge, 'id'),
            status: $status,
            amountMinor: (int) round(((float) $this->field($charge, 'amount')) * 100),
            currency: strtoupper((string) $this->field($charge, 'currency')),
            rawPayload: $rawPayload,
            // `charge_pending` / `in_progress` → the UI says "procesando".
            providerStatus: in_array($this->field($charge, 'status'), ['charge_pending', 'in_progress'], true) ? 'processing' : null,
        );
    }

    /**
     * Safe for the browser: status and the 3DS redirect, never a key.
     *
     * @return array{status: string, redirect_url: string|null}
     */
    private function clientPayload(OpenpayApiResourceBase|stdClass $charge): array
    {
        // Plain property read on purpose: the SDK's resources implement
        // __get but not __isset, so `??` would always see null.
        $method = $this->field($charge, 'payment_method');
        $type = is_object($method) ? ($method->type ?? null) : (is_array($method) ? ($method['type'] ?? null) : null);
        $url = is_object($method) ? ($method->url ?? null) : (is_array($method) ? ($method['url'] ?? null) : null);

        $redirect = $type === 'redirect' && is_string($url) && str_starts_with($url, 'https://') ? $url : null;

        return [
            'status' => (string) $this->field($charge, 'status'),
            'redirect_url' => $this->field($charge, 'status') === 'completed' ? null : $redirect,
        ];
    }

    /**
     * One reader for charge fields. The SDK's resources expose their data
     * only through __get (and have no __isset), so `??` can't be used.
     */
    private function field(OpenpayApiResourceBase|stdClass $charge, string $key): mixed
    {
        return $charge->{$key};
    }

    /**
     * Customer data helps OpenPay's anti-fraud; only sent when we have it.
     *
     * @return array<string, mixed>|null
     */
    private function customer(Order $order): ?array
    {
        $snapshot = $order->customer_snapshot ?? [];
        $email = $snapshot['email'] ?? null;
        $name = trim((string) ($snapshot['name'] ?? ''));

        if (! is_string($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL) || $name === '') {
            return null;
        }

        $parts = preg_split('/\s+/', $name, 2) ?: [$name];
        $customer = [
            'name' => mb_substr($parts[0], 0, 100),
            'email' => $email,
            'requires_account' => false,
        ];

        if (isset($parts[1]) && $parts[1] !== '') {
            $customer['last_name'] = mb_substr($parts[1], 0, 100);
        }

        $phone = preg_replace('/\D+/', '', (string) ($snapshot['phone'] ?? ''));

        if (is_string($phone) && strlen($phone) === 10) {
            $customer['phone_number'] = $phone;
        }

        return $customer;
    }

    private function declined(OpenpayApiError $e): PaymentDeclinedException
    {
        $code = (int) $e->getErrorCode();

        return new PaymentDeclinedException(
            self::DECLINE_MESSAGES[$code]
                ?? 'No pudimos procesar la tarjeta. Revisa los datos o intenta con otra tarjeta.',
        );
    }

    private function client(): mixed
    {
        if (! $this->isConfigured()) {
            throw new PaymentGatewayNotConfiguredException('openpay');
        }

        return $this->factory->make(
            (string) config('finisher.payments.openpay.merchant_id'),
            (string) config('finisher.payments.openpay.private_key'),
            (string) config('finisher.payments.openpay.country', 'MX'),
            (bool) config('finisher.payments.openpay.production_mode'),
        );
    }
}
