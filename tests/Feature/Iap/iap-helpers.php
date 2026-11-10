<?php

/*
| Fixtures for tests/Feature/Iap — in-app purchases verified with mocked
| store APIs (Http::fake). Everything cryptographic is generated per test
| run: a throw-away "Apple" chain (root → intermediate → leaf carrying
| Apple's OIDs) trusted only through config, an EC .p8-like key for the
| App Store Server API JWT, and an RSA service-account key for Google.
| No real credentials, ids or prices.
*/

use App\Models\PhotoExpansionPack;
use App\Services\Iap\AppleJwsVerifier;

require_once __DIR__.'/../Quotas/quota-helpers.php';

/**
 * @return array{leaf_key: OpenSSLAsymmetricKey, x5c: list<string>, root_path: string}
 */
function fakeAppleChain(): array
{
    static $chain = null;

    if ($chain !== null) {
        return $chain;
    }

    $cnf = tempnam(sys_get_temp_dir(), 'iapcnf');
    file_put_contents($cnf, implode("\n", [
        '[req]', 'distinguished_name=dn', '[dn]',
        '[root_ext]', 'basicConstraints=critical,CA:TRUE',
        '[int_ext]', 'basicConstraints=critical,CA:TRUE', '1.2.840.113635.100.6.2.1=ASN1:NULL',
        '[leaf_ext]', 'basicConstraints=CA:FALSE', '1.2.840.113635.100.6.11.1=ASN1:NULL', '',
    ]));
    $keyOptions = ['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1'];
    $options = ['config' => $cnf, 'digest_alg' => 'sha256'];

    $rootKey = openssl_pkey_new($keyOptions);
    $root = openssl_csr_sign(openssl_csr_new(['commonName' => 'Test Root'], $rootKey, $options), null, $rootKey, 30, $options + ['x509_extensions' => 'root_ext']);
    $intKey = openssl_pkey_new($keyOptions);
    $int = openssl_csr_sign(openssl_csr_new(['commonName' => 'Test Intermediate'], $intKey, $options), $root, $rootKey, 30, $options + ['x509_extensions' => 'int_ext']);
    $leafKey = openssl_pkey_new($keyOptions);
    $leaf = openssl_csr_sign(openssl_csr_new(['commonName' => 'Test Leaf'], $leafKey, $options), $int, $intKey, 30, $options + ['x509_extensions' => 'leaf_ext']);

    $der = function ($cert): string {
        openssl_x509_export($cert, $pem);

        return (string) base64_decode(preg_replace('/-----[^-]+-----|\s+/', '', $pem) ?? '', true);
    };

    $rootPath = tempnam(sys_get_temp_dir(), 'iaproot');
    file_put_contents($rootPath, $der($root));

    return $chain = [
        'leaf_key' => $leafKey,
        'x5c' => [base64_encode($der($leaf)), base64_encode($der($int)), base64_encode($der($root))],
        'root_path' => $rootPath,
    ];
}

/** @param  array<string, mixed>  $payload */
function signAppleJws(array $payload, bool $trusted = true): string
{
    $chain = fakeAppleChain();
    $x5c = $chain['x5c'];
    $key = $chain['leaf_key'];

    if (! $trusted) {
        // Same shape, but signed by a key whose chain is not the trusted one.
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
    }

    $input = AppleJwsVerifier::base64UrlEncode((string) json_encode(['alg' => 'ES256', 'x5c' => $x5c]))
        .'.'.AppleJwsVerifier::base64UrlEncode((string) json_encode($payload));
    openssl_sign($input, $der, $key, OPENSSL_ALGO_SHA256);

    return $input.'.'.AppleJwsVerifier::base64UrlEncode(AppleJwsVerifier::derToRawSignature($der));
}

function configureAppleIap(): void
{
    $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
    openssl_pkey_export($key, $pem);

    config([
        'iap.apple.key_id' => 'TESTKEY123',
        'iap.apple.issuer_id' => '00000000-0000-0000-0000-000000000000',
        'iap.apple.private_key' => $pem,
        'iap.apple.bundle_id' => 'com.finisherlegacy.app',
        'iap.apple.allow_sandbox' => true,
        'iap.apple.root_certificates' => [fakeAppleChain()['root_path']],
    ]);
}

function configureGoogleIap(): void
{
    $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
    openssl_pkey_export($key, $pem);
    $path = tempnam(sys_get_temp_dir(), 'iapsa');
    file_put_contents($path, json_encode(['type' => 'service_account', 'client_email' => 'iap-test@example.iam.gserviceaccount.com', 'private_key' => $pem]));

    config([
        'iap.google.service_account_json' => $path,
        'iap.google.package_name' => 'com.finisherlegacy.app',
        'iap.google.rtdn.token' => 'rtdn-test-secret',
        'iap.google.rtdn.audience' => null,
    ]);
}

/** @param  array<string, mixed>  $overrides */
function appleTransaction(string $transactionId, string $productId, array $overrides = []): array
{
    return [
        'transactionId' => $transactionId,
        'originalTransactionId' => $transactionId,
        'bundleId' => 'com.finisherlegacy.app',
        'productId' => $productId,
        'purchaseDate' => now()->getTimestampMs(),
        'quantity' => 1,
        'type' => 'Consumable',
        'environment' => 'Sandbox',
        'price' => 99000,
        'currency' => 'MXN',
        'inAppOwnershipType' => 'PURCHASED',
        ...$overrides,
    ];
}

/** A sellable pack with store product ids (web price optional). */
function storePack(int $photos = 10, string $apple = 'com.finisherlegacy.app.fotos10', string $google = 'fotos_10', array $attributes = []): PhotoExpansionPack
{
    return PhotoExpansionPack::create([
        'name' => 'Pack de prueba', 'photos' => $photos, 'active' => true,
        'apple_product_id' => $apple, 'google_product_id' => $google, ...$attributes,
    ]);
}
