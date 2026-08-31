<?php

namespace App\Support\Commerce;

use App\Enums\PaymentStatus;

/**
 * What a PaymentGateway::handleWebhook() implementation hands back once
 * it's verified the request's signature and parsed the provider's event —
 * App\Actions\Commerce\ProcessPaymentWebhook never touches raw HTTP or
 * provider payloads directly, which is what makes its idempotency/amount-
 * check logic testable without a real gateway (brief §69-§70/§77-§78).
 */
final class PaymentWebhookOutcome
{
    /**
     * @param  array<string, mixed>  $rawPayload
     */
    public function __construct(
        public readonly string $provider,
        public readonly string $eventId,
        public readonly string $providerReference,
        public readonly PaymentStatus $status,
        public readonly int $amountMinor,
        public readonly string $currency,
        public readonly array $rawPayload = [],
    ) {}
}
