<?php

namespace App\Services\Auth;

use App\Exceptions\SocialAuthInvalidTokenException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Verifies an OpenID Connect ID token (RS256) issued by Google or Apple to
 * the native app: signature against the provider's published JWKS, then
 * `iss`, `aud` (our client ids), `exp`/`iat`, and — when the app sent one —
 * the `nonce`. Uses PHP's OpenSSL directly (no extra JWT dependency). Keys
 * are cached for an hour and refetched once on an unknown `kid` (key
 * rotation).
 */
class IdTokenVerifier
{
    private const LEEWAY_SECONDS = 60;

    /**
     * @param  array{client_ids: list<string>, jwks_url: string, issuers: list<string>}  $provider
     * @return array<string, mixed> The verified claims.
     */
    public function verify(string $idToken, array $provider, ?string $nonce = null): array
    {
        $parts = explode('.', $idToken);

        if (count($parts) !== 3) {
            throw new SocialAuthInvalidTokenException;
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;

        $header = json_decode($this->base64UrlDecode($encodedHeader), true);
        $claims = json_decode($this->base64UrlDecode($encodedPayload), true);
        $signature = $this->base64UrlDecode($encodedSignature);

        if (! is_array($header) || ! is_array($claims) || ($header['alg'] ?? null) !== 'RS256' || ! is_string($header['kid'] ?? null)) {
            throw new SocialAuthInvalidTokenException;
        }

        $pem = $this->publicKey($provider['jwks_url'], $header['kid']);

        if ($pem === null || openssl_verify("{$encodedHeader}.{$encodedPayload}", $signature, $pem, OPENSSL_ALGO_SHA256) !== 1) {
            throw new SocialAuthInvalidTokenException;
        }

        $now = time();
        $audiences = is_array($claims['aud'] ?? null) ? $claims['aud'] : [$claims['aud'] ?? null];

        $valid = in_array($claims['iss'] ?? null, $provider['issuers'], true)
            && array_intersect($audiences, $provider['client_ids']) !== []
            && is_numeric($claims['exp'] ?? null) && (int) $claims['exp'] + self::LEEWAY_SECONDS >= $now
            && (! isset($claims['iat']) || (int) $claims['iat'] - self::LEEWAY_SECONDS <= $now)
            && is_string($claims['sub'] ?? null) && $claims['sub'] !== '';

        if ($valid && $nonce !== null) {
            // Apple embeds sha256(nonce); Google embeds the raw nonce.
            $valid = in_array($claims['nonce'] ?? null, [$nonce, hash('sha256', $nonce)], true);
        }

        if (! $valid) {
            throw new SocialAuthInvalidTokenException;
        }

        return $claims;
    }

    private function publicKey(string $jwksUrl, string $kid): ?string
    {
        $cacheKey = 'jwks:'.md5($jwksUrl);
        $keys = Cache::get($cacheKey);

        if (! is_array($keys) || ! isset($keys[$kid])) {
            $keys = $this->fetchKeys($jwksUrl);

            // Never cache a failed/empty fetch — that would lock sign-in
            // out for an hour after one network blip.
            if ($keys !== []) {
                Cache::put($cacheKey, $keys, 3600);
            }
        }

        return $keys[$kid] ?? null;
    }

    /**
     * @return array<string, string> kid => PEM
     */
    private function fetchKeys(string $jwksUrl): array
    {
        try {
            $response = Http::timeout(5)->acceptJson()->get($jwksUrl);
        } catch (Throwable) {
            return [];
        }

        $keys = [];

        foreach ((array) $response->json('keys', []) as $jwk) {
            if (($jwk['kty'] ?? null) === 'RSA' && isset($jwk['kid'], $jwk['n'], $jwk['e'])) {
                $keys[$jwk['kid']] = $this->rsaPem($this->base64UrlDecode($jwk['n']), $this->base64UrlDecode($jwk['e']));
            }
        }

        return $keys;
    }

    /**
     * DER-encodes an RSA public key (modulus + exponent) as a PEM
     * SubjectPublicKeyInfo, which is what openssl_verify() accepts.
     */
    private function rsaPem(string $modulus, string $exponent): string
    {
        $rsaPublicKey = $this->derSequence($this->derInteger($modulus).$this->derInteger($exponent));
        $algorithm = "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00";
        $bitString = "\x03".$this->derLength(strlen($rsaPublicKey) + 1)."\x00".$rsaPublicKey;
        $spki = $this->derSequence($algorithm.$bitString);

        return "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($spki), 64, "\n")."-----END PUBLIC KEY-----\n";
    }

    private function derSequence(string $content): string
    {
        return "\x30".$this->derLength(strlen($content)).$content;
    }

    private function derInteger(string $bytes): string
    {
        $bytes = ltrim($bytes, "\x00");

        if ($bytes === '' || ord($bytes[0]) > 0x7F) {
            $bytes = "\x00".$bytes;
        }

        return "\x02".$this->derLength(strlen($bytes)).$bytes;
    }

    private function derLength(int $length): string
    {
        if ($length < 0x80) {
            return chr($length);
        }

        $bytes = ltrim(pack('N', $length), "\x00");

        return chr(0x80 | strlen($bytes)).$bytes;
    }

    private function base64UrlDecode(string $value): string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/').str_repeat('=', (4 - strlen($value) % 4) % 4), true);

        return $decoded === false ? '' : $decoded;
    }
}
