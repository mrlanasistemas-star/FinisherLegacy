<?php

namespace App\Services\Iap;

use Carbon\CarbonImmutable;

/**
 * A store transaction as the STORE reports it (never as the app reports
 * it), normalised across App Store and Google Play. Built only by
 * AppStoreVerifier / GooglePlayVerifier from server-side data.
 */
final readonly class VerifiedStoreTransaction
{
    public const string STATE_PURCHASED = 'purchased';

    public const string STATE_PENDING = 'pending';

    /** Refunded, revoked or cancelled by the store. */
    public const string STATE_INVALID = 'invalid';

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $platform,
        public string $transactionKey,
        public ?string $transactionId,
        public ?string $originalTransactionId,
        public string $productId,
        public string $state,
        public int $quantity,
        public ?string $environment,
        public ?CarbonImmutable $purchasedAt,
        public ?int $amountMinor,
        public ?string $currency,
        /** iOS appAccountToken / Android obfuscatedExternalAccountId, when the app set one. */
        public ?string $accountToken,
        public bool $acknowledged = false,
        public ?string $invalidReason = null,
        public array $metadata = [],
    ) {}
}
