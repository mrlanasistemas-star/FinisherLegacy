<?php

namespace App\Services\Iap;

use App\Exceptions\Iap\IapException;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Server-side verification of App Store (StoreKit 2) transactions through
 * the App Store Server API: `GET /inApps/v1/transactions/{transactionId}`
 * authenticated with an ES256 JWT built from the In-App Purchase key
 * (APPLE_IAP_KEY_ID / APPLE_IAP_ISSUER_ID / APPLE_IAP_PRIVATE_KEY /
 * APPLE_BUNDLE_ID). The returned `signedTransactionInfo` is additionally
 * verified against Apple Root CA - G3 (AppleJwsVerifier). What the app
 * sends is only used to know WHICH transaction to look up.
 *
 * Production is tried first; Apple's "TransactionIdNotFound" (4040010)
 * retries on sandbox (TestFlight, App Review, sandbox testers).
 */
class AppStoreVerifier
{
    private const int TRANSACTION_NOT_FOUND = 4040010;

    /** ISO 4217 currencies without minor units (Apple's price is in milliunits). */
    private const array ZERO_DECIMAL_CURRENCIES = ['JPY', 'KRW', 'CLP', 'VND', 'ISK', 'HUF', 'TWD', 'UGX', 'XAF', 'XOF', 'PYG'];

    public function __construct(private readonly AppleJwsVerifier $jws) {}

    public function configured(): bool
    {
        return filled(config('iap.apple.key_id'))
            && filled(config('iap.apple.issuer_id'))
            && filled(config('iap.apple.private_key'))
            && filled(config('iap.apple.bundle_id'));
    }

    /**
     * @throws IapException
     */
    public function transaction(string $transactionId): VerifiedStoreTransaction
    {
        if (! $this->configured()) {
            throw IapException::notConfigured('ios');
        }

        if (preg_match('/^\d{1,40}$/', $transactionId) !== 1) {
            throw IapException::verificationFailed('transaction_id');
        }

        $response = $this->fetch((string) config('iap.apple.production_url'), $transactionId);

        if ($response->status() === 404 && (int) $response->json('errorCode') === self::TRANSACTION_NOT_FOUND && config('iap.apple.allow_sandbox')) {
            $response = $this->fetch((string) config('iap.apple.sandbox_url'), $transactionId);
        }

        if ($response->status() === 401 || $response->status() === 403) {
            Log::error('App Store Server API rechazó las credenciales de compras dentro de la app.', ['status' => $response->status()]);

            throw IapException::storeUnavailable();
        }

        if ($response->serverError() || $response->status() === 429) {
            throw IapException::storeUnavailable();
        }

        if (! $response->successful() || ! is_string($response->json('signedTransactionInfo'))) {
            throw IapException::verificationFailed('not_found');
        }

        try {
            $payload = $this->jws->verify((string) $response->json('signedTransactionInfo'));
        } catch (RuntimeException $e) {
            Log::warning('Transacción de App Store con firma no válida.', ['error' => $e->getMessage()]);

            throw IapException::verificationFailed('signature');
        }

        return $this->normalize($payload);
    }

    /**
     * A JWS-verified JWSTransactionDecodedPayload → our transaction.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws IapException
     */
    public function normalize(array $payload): VerifiedStoreTransaction
    {
        $transactionId = (string) ($payload['transactionId'] ?? '');

        if ($transactionId === '' || ($payload['bundleId'] ?? null) !== config('iap.apple.bundle_id')) {
            throw IapException::verificationFailed('bundle');
        }

        $environment = is_string($payload['environment'] ?? null) ? $payload['environment'] : null;

        if ($environment === 'Sandbox' && ! config('iap.apple.allow_sandbox')) {
            throw IapException::verificationFailed('sandbox');
        }

        $currency = is_string($payload['currency'] ?? null) ? strtoupper($payload['currency']) : null;
        $price = is_numeric($payload['price'] ?? null) ? (int) $payload['price'] : null;
        $revoked = isset($payload['revocationDate']);

        return new VerifiedStoreTransaction(
            platform: 'ios',
            transactionKey: $transactionId,
            transactionId: $transactionId,
            originalTransactionId: isset($payload['originalTransactionId']) ? (string) $payload['originalTransactionId'] : null,
            productId: (string) ($payload['productId'] ?? ''),
            state: $revoked ? VerifiedStoreTransaction::STATE_INVALID : VerifiedStoreTransaction::STATE_PURCHASED,
            quantity: max(1, (int) ($payload['quantity'] ?? 1)),
            environment: $environment,
            purchasedAt: is_numeric($payload['purchaseDate'] ?? null) ? CarbonImmutable::createFromTimestampMs((int) $payload['purchaseDate']) : null,
            amountMinor: $price !== null && $currency !== null ? self::milliunitsToMinor($price, $currency) : null,
            currency: $currency,
            accountToken: is_string($payload['appAccountToken'] ?? null) && $payload['appAccountToken'] !== '' ? strtolower($payload['appAccountToken']) : null,
            acknowledged: true,
            invalidReason: $revoked ? 'revoked:'.($payload['revocationReason'] ?? 'unknown') : null,
            metadata: array_filter([
                'type' => $payload['type'] ?? null,
                'storefront' => $payload['storefront'] ?? null,
                'ownership' => $payload['inAppOwnershipType'] ?? null,
            ]),
        );
    }

    public static function milliunitsToMinor(int $milliunits, string $currency): int
    {
        return in_array($currency, self::ZERO_DECIMAL_CURRENCIES, true)
            ? intdiv($milliunits, 1000)
            : intdiv($milliunits, 10);
    }

    private function fetch(string $baseUrl, string $transactionId): Response
    {
        try {
            return Http::withToken($this->token())
                ->acceptJson()
                ->timeout(10)
                ->get(rtrim($baseUrl, '/')."/inApps/v1/transactions/{$transactionId}");
        } catch (ConnectionException) {
            throw IapException::storeUnavailable();
        }
    }

    /** ES256 JWT for the App Store Server API (max 60 min; we use 10). */
    public function token(): string
    {
        $header = ['alg' => 'ES256', 'kid' => (string) config('iap.apple.key_id'), 'typ' => 'JWT'];
        $now = time();
        $claims = [
            'iss' => (string) config('iap.apple.issuer_id'),
            'iat' => $now,
            'exp' => $now + 600,
            'aud' => 'appstoreconnect-v1',
            'bid' => (string) config('iap.apple.bundle_id'),
        ];

        $input = AppleJwsVerifier::base64UrlEncode((string) json_encode($header))
            .'.'.AppleJwsVerifier::base64UrlEncode((string) json_encode($claims));

        $key = openssl_pkey_get_private($this->privateKey());

        if ($key === false || ! openssl_sign($input, $der, $key, OPENSSL_ALGO_SHA256)) {
            Log::error('APPLE_IAP_PRIVATE_KEY no es una llave .p8 válida.');

            throw IapException::notConfigured('ios');
        }

        return $input.'.'.AppleJwsVerifier::base64UrlEncode(AppleJwsVerifier::derToRawSignature((string) $der));
    }

    private function privateKey(): string
    {
        $value = (string) config('iap.apple.private_key');

        if (! str_contains($value, '-----BEGIN') && is_file($value)) {
            $value = (string) file_get_contents($value);
        }

        return str_replace('\n', "\n", $value);
    }
}
