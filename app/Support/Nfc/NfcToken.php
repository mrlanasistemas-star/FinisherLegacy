<?php

namespace App\Support\Nfc;

use App\Models\NfcTag;
use Illuminate\Support\Str;

/**
 * The public NFC token and the (optional) hardware UID hash. The token is
 * the only thing written to the chip — https://<domain>/n/{token} — so it
 * must be unguessable (≥128 bits, CSPRNG via Str::random → random_bytes),
 * never sequential, never derived from a person. See docs/NFC.md §Token.
 */
final class NfcToken
{
    public const string PATTERN = '[A-Za-z0-9]{24,64}';

    public static function generate(): string
    {
        $length = max(22, (int) config('nfc.token_length', 32));

        do {
            $token = Str::random($length);
        } while (NfcTag::query()->where('token', $token)->exists());

        return $token;
    }

    public static function isWellFormed(string $token): bool
    {
        return preg_match('/^'.self::PATTERN.'$/', $token) === 1;
    }

    /**
     * Accepts a bare token or a full scanned URL (…/n/{token}) — what an NFC
     * reader in keyboard mode or a phone share sheet hands staff.
     */
    public static function extract(string $input): ?string
    {
        $input = trim($input);

        if (preg_match('~/n/('.self::PATTERN.')(?:[/?#].*)?$~', $input, $m) === 1) {
            return $m[1];
        }

        return self::isWellFormed($input) ? $input : null;
    }

    /**
     * Keyed hash of the chip UID — the clear UID is never stored or shown.
     */
    public static function hashUid(string $uid): string
    {
        $normalized = strtoupper((string) preg_replace('/[^0-9A-Fa-f]/', '', $uid));

        return hash_hmac('sha256', 'nfc-uid|'.$normalized, (string) config('app.key'));
    }

    public static function hashIp(?string $ip): ?string
    {
        return $ip === null ? null : hash_hmac('sha256', 'nfc-ip|'.$ip, (string) config('app.key'));
    }

    public static function url(string $token): string
    {
        return route('nfc.tap', $token);
    }
}
