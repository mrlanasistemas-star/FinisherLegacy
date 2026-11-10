<?php

namespace App\Services\Iap;

use RuntimeException;

/**
 * Verifies a JWS signed by the App Store (signed transactions, renewal
 * info and App Store Server Notifications V2 `signedPayload`) the way
 * Apple's own app-store-server-library does, with PHP's OpenSSL only:
 *
 *  1. header `alg` = ES256 and an `x5c` chain of three certificates;
 *  2. leaf carries Apple's receipt-signing OID (1.2.840.113635.100.6.11.1)
 *     and the intermediate Apple's WWDR OID (1.2.840.113635.100.6.2.1);
 *  3. leaf ← intermediate ← root signatures, and the root is byte-identical
 *     to a trusted root (Apple Root CA - G3, config('iap.apple.root_certificates'));
 *  4. the JWS signature against the leaf's public key.
 *
 * Throws on any failure; returns the decoded payload otherwise.
 */
class AppleJwsVerifier
{
    private const string LEAF_OID = '1.2.840.113635.100.6.11.1';

    private const string INTERMEDIATE_OID = '1.2.840.113635.100.6.2.1';

    /**
     * @return array<string, mixed>
     */
    public function verify(string $jws): array
    {
        $parts = explode('.', $jws);

        if (count($parts) !== 3) {
            throw new RuntimeException('JWS mal formado.');
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;
        $header = json_decode(self::base64UrlDecode($encodedHeader), true);
        $payload = json_decode(self::base64UrlDecode($encodedPayload), true);

        if (! is_array($header) || ! is_array($payload) || ($header['alg'] ?? null) !== 'ES256') {
            throw new RuntimeException('Encabezado JWS no válido.');
        }

        $chain = array_values(array_filter((array) ($header['x5c'] ?? []), 'is_string'));

        if (count($chain) !== 3) {
            throw new RuntimeException('Cadena x5c incompleta.');
        }

        $pems = array_map(fn (string $der) => self::derToPem((string) base64_decode($der, true)), $chain);
        [$leaf, $intermediate, $root] = $pems;

        if (! $this->hasExtension($leaf, self::LEAF_OID) || ! $this->hasExtension($intermediate, self::INTERMEDIATE_OID)) {
            throw new RuntimeException('Los certificados no son de Apple.');
        }

        if (openssl_x509_verify($leaf, $intermediate) !== 1 || openssl_x509_verify($intermediate, $root) !== 1) {
            throw new RuntimeException('Cadena de certificados no válida.');
        }

        if (! $this->isTrustedRoot((string) base64_decode($chain[2], true))) {
            throw new RuntimeException('Raíz de certificados no confiable.');
        }

        $publicKey = openssl_pkey_get_public($leaf);
        $signature = self::base64UrlDecode($encodedSignature);

        if ($publicKey === false || strlen($signature) !== 64
            || openssl_verify("{$encodedHeader}.{$encodedPayload}", self::rawToDerSignature($signature), $publicKey, OPENSSL_ALGO_SHA256) !== 1) {
            throw new RuntimeException('Firma JWS no válida.');
        }

        return $payload;
    }

    /**
     * Decodes a JWS payload WITHOUT verifying it. Only for data that came
     * straight from Apple's API over TLS, or to read ids before a
     * verification step.
     *
     * @return array<string, mixed>
     */
    public static function decodeUnverified(string $jws): array
    {
        $parts = explode('.', $jws);
        $payload = count($parts) === 3 ? json_decode(self::base64UrlDecode($parts[1]), true) : null;

        return is_array($payload) ? $payload : [];
    }

    private function isTrustedRoot(string $der): bool
    {
        foreach ((array) config('iap.apple.root_certificates', []) as $path) {
            if (! is_string($path) || ! is_file($path)) {
                continue;
            }

            $trusted = (string) file_get_contents($path);

            // Accept DER (.cer as Apple publishes it) or PEM.
            if (str_contains($trusted, '-----BEGIN CERTIFICATE-----')) {
                $trusted = (string) base64_decode(preg_replace('/-----[^-]+-----|\s+/', '', $trusted) ?? '', true);
            }

            if ($trusted !== '' && hash_equals($trusted, $der)) {
                return true;
            }
        }

        return false;
    }

    private function hasExtension(string $pem, string $oid): bool
    {
        $parsed = openssl_x509_parse($pem);

        return is_array($parsed) && array_key_exists($oid, (array) ($parsed['extensions'] ?? []));
    }

    public static function derToPem(string $der): string
    {
        return "-----BEGIN CERTIFICATE-----\n".chunk_split(base64_encode($der), 64, "\n")."-----END CERTIFICATE-----\n";
    }

    /** JWS ES256 signatures are raw R||S (2×32 bytes); OpenSSL wants DER. */
    public static function rawToDerSignature(string $raw): string
    {
        $encode = function (string $int): string {
            $int = ltrim($int, "\x00");
            if ($int === '' || ord($int[0]) > 0x7F) {
                $int = "\x00".$int;
            }

            return "\x02".chr(strlen($int)).$int;
        };

        $sequence = $encode(substr($raw, 0, 32)).$encode(substr($raw, 32, 32));

        return "\x30".chr(strlen($sequence)).$sequence;
    }

    /** DER ECDSA signature → raw R||S, for the ES256 JWTs we sign ourselves. */
    public static function derToRawSignature(string $der, int $partLength = 32): string
    {
        $offset = 2;
        if ((ord($der[1]) & 0x80) !== 0) {
            $offset += ord($der[1]) & 0x7F;
        }

        $parts = [];
        for ($i = 0; $i < 2; $i++) {
            $length = ord($der[$offset + 1]);
            $parts[] = str_pad(ltrim(substr($der, $offset + 2, $length), "\x00"), $partLength, "\x00", STR_PAD_LEFT);
            $offset += 2 + $length;
        }

        return implode('', $parts);
    }

    public static function base64UrlDecode(string $value): string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/').str_repeat('=', (4 - strlen($value) % 4) % 4), true);

        return $decoded === false ? '' : $decoded;
    }

    public static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
