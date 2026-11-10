<?php

namespace App\Services\Iap;

use App\Exceptions\Iap\IapException;
use App\Models\StorePurchase;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Server-side verification of Google Play Billing one-time purchases via
 * the Google Play Developer API (`purchases.products.get`) with a service
 * account (GOOGLE_PLAY_SERVICE_ACCOUNT_JSON, package
 * GOOGLE_PLAY_PACKAGE_NAME). After the capacity is granted the backend
 * acknowledges the purchase (`purchases.products.acknowledge`) so Google
 * never auto-refunds it after 3 days, even if the app dies before it
 * consumes it; the app then consumes it (finishTransaction isConsumable)
 * so the same pack can be bought again.
 */
class GooglePlayVerifier
{
    private const string SCOPE = 'https://www.googleapis.com/auth/androidpublisher';

    public function configured(): bool
    {
        return $this->serviceAccount() !== null && filled(config('iap.google.package_name'));
    }

    /**
     * @throws IapException
     */
    public function purchase(string $productId, string $purchaseToken): VerifiedStoreTransaction
    {
        if (! $this->configured()) {
            throw IapException::notConfigured('android');
        }

        if (preg_match('/^[a-z0-9][a-z0-9._]{0,99}$/', $productId) !== 1 || $purchaseToken === '' || strlen($purchaseToken) > 4096) {
            throw IapException::verificationFailed('input');
        }

        $response = $this->request('get', $this->productUrl($productId, $purchaseToken));

        if ($response->status() === 401 || $response->status() === 403) {
            Log::error('Google Play Developer API rechazó la cuenta de servicio de compras dentro de la app.', ['status' => $response->status()]);

            throw IapException::storeUnavailable();
        }

        if ($response->serverError() || $response->status() === 429) {
            throw IapException::storeUnavailable();
        }

        if (! $response->successful()) {
            throw IapException::verificationFailed('not_found');
        }

        $body = (array) $response->json();
        $state = match ((int) ($body['purchaseState'] ?? -1)) {
            0 => VerifiedStoreTransaction::STATE_PURCHASED,
            2 => VerifiedStoreTransaction::STATE_PENDING,
            default => VerifiedStoreTransaction::STATE_INVALID,
        };

        $orderId = is_string($body['orderId'] ?? null) && $body['orderId'] !== '' ? $body['orderId'] : null;
        $account = $body['obfuscatedExternalAccountId'] ?? null;

        return new VerifiedStoreTransaction(
            platform: 'android',
            transactionKey: StorePurchase::androidKey($purchaseToken),
            transactionId: $orderId,
            originalTransactionId: null,
            // purchases.products.get is per product: the token was checked for THIS id.
            productId: $productId,
            state: $state,
            quantity: max(1, (int) ($body['quantity'] ?? 1)),
            // purchaseType is present only for test (0), promo (1) or rewarded (2) purchases.
            environment: array_key_exists('purchaseType', $body) ? ((int) $body['purchaseType'] === 0 ? 'test' : 'promo') : 'production',
            purchasedAt: is_numeric($body['purchaseTimeMillis'] ?? null) ? CarbonImmutable::createFromTimestampMs((int) $body['purchaseTimeMillis']) : null,
            amountMinor: null,
            currency: null,
            accountToken: is_string($account) && $account !== '' ? strtolower($account) : null,
            acknowledged: (int) ($body['acknowledgementState'] ?? 0) === 1,
            invalidReason: $state === VerifiedStoreTransaction::STATE_INVALID ? 'cancelled' : null,
            metadata: array_filter([
                'region' => $body['regionCode'] ?? null,
                'consumption_state' => $body['consumptionState'] ?? null,
            ], fn ($v) => $v !== null),
        );
    }

    /** Never throws: a failed acknowledgement is logged; the app's consume also acknowledges. */
    public function acknowledge(string $productId, string $purchaseToken): bool
    {
        try {
            $response = $this->request('post', $this->productUrl($productId, $purchaseToken).':acknowledge');
        } catch (IapException) {
            return false;
        }

        if (! $response->successful()) {
            Log::warning('No se pudo confirmar (acknowledge) la compra en Google Play.', ['status' => $response->status()]);

            return false;
        }

        return true;
    }

    private function productUrl(string $productId, string $purchaseToken): string
    {
        return rtrim((string) config('iap.google.api_url'), '/')
            .'/applications/'.rawurlencode((string) config('iap.google.package_name'))
            .'/purchases/products/'.rawurlencode($productId)
            .'/tokens/'.rawurlencode($purchaseToken);
    }

    /** POSTs send an empty JSON object (`{}`), which is what acknowledge expects. */
    private function request(string $method, string $url): Response
    {
        try {
            $client = Http::withToken($this->accessToken())->acceptJson()->timeout(10);

            return $method === 'post' ? $client->withBody('{}', 'application/json')->post($url) : $client->get($url);
        } catch (ConnectionException) {
            throw IapException::storeUnavailable();
        }
    }

    /** OAuth 2.0 service-account flow (JWT bearer grant), cached ~50 min. */
    private function accessToken(): string
    {
        $account = $this->serviceAccount();

        if ($account === null) {
            throw IapException::notConfigured('android');
        }

        $cacheKey = 'iap:google:token:'.md5($account['client_email']);
        $cached = Cache::get($cacheKey);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $now = time();
        $input = AppleJwsVerifier::base64UrlEncode((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT']))
            .'.'.AppleJwsVerifier::base64UrlEncode((string) json_encode([
                'iss' => $account['client_email'],
                'scope' => self::SCOPE,
                'aud' => (string) config('iap.google.token_url'),
                'iat' => $now,
                'exp' => $now + 3600,
            ]));

        $key = openssl_pkey_get_private($account['private_key']);

        if ($key === false || ! openssl_sign($input, $signature, $key, OPENSSL_ALGO_SHA256)) {
            Log::error('GOOGLE_PLAY_SERVICE_ACCOUNT_JSON no contiene una llave privada válida.');

            throw IapException::notConfigured('android');
        }

        try {
            $response = Http::asForm()->timeout(10)->post((string) config('iap.google.token_url'), [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $input.'.'.AppleJwsVerifier::base64UrlEncode((string) $signature),
            ]);
        } catch (ConnectionException) {
            throw IapException::storeUnavailable();
        }

        $token = $response->json('access_token');

        if (! $response->successful() || ! is_string($token) || $token === '') {
            Log::error('Google OAuth rechazó la cuenta de servicio de Google Play.', ['status' => $response->status()]);

            throw IapException::storeUnavailable();
        }

        Cache::put($cacheKey, $token, max(60, (int) $response->json('expires_in', 3600) - 600));

        return $token;
    }

    /**
     * @return array{client_email: string, private_key: string}|null
     */
    private function serviceAccount(): ?array
    {
        $path = config('iap.google.service_account_json');

        if (! is_string($path) || $path === '' || ! is_file($path)) {
            return null;
        }

        $json = json_decode((string) file_get_contents($path), true);

        if (! is_array($json) || ! is_string($json['client_email'] ?? null) || ! is_string($json['private_key'] ?? null)) {
            return null;
        }

        return ['client_email' => $json['client_email'], 'private_key' => $json['private_key']];
    }
}
