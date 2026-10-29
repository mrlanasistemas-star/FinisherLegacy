<?php

namespace App\Support\LegacyTags;

use App\Models\LegacyTag;
use RuntimeException;

/**
 * The public code of a physical tag — the only thing the NFC chip carries
 * (https://<domain>/t/{code}) and what a person can type at
 * /activar. CSPRNG (random_int), no ambiguous glyphs (0/O, 1/I/L), never
 * derived from the internal id, a person or an order.
 */
final class TagCode
{
    public const string ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /** Route constraint — lenient on length so future longer codes still route. */
    public const string PATTERN = '[A-Za-z0-9-]{8,24}';

    public static function generate(): string
    {
        $length = max(10, (int) config('legacy.tags.code_length', 12));

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $code = '';

            for ($i = 0; $i < $length; $i++) {
                $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }

            if (! LegacyTag::query()->where('public_code', $code)->exists()) {
                return $code;
            }
        }

        throw new RuntimeException('Unable to generate a unique tag code.');
    }

    /** XXXX-XXXX-XXXX for print and screens. */
    public static function format(string $code): string
    {
        return implode('-', str_split($code, 4));
    }

    /**
     * Accepts what a person or a scanner hands us — "abcd-efgh-jkmn",
     * "FL ABCD EFGH JKMN", a full https://…/t/ABCDEFGHJKMN?src=qr URL — and
     * returns the canonical code, or null when it cannot be one.
     */
    public static function normalize(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $input = trim($input);

        if (preg_match('~/t/([A-Za-z0-9-]+)~', $input, $m) === 1) {
            $input = $m[1];
        }

        $code = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $input));

        if (str_starts_with($code, 'FL') && strlen($code) === (int) config('legacy.tags.code_length', 12) + 2) {
            $code = substr($code, 2);
        }

        if (strlen($code) < 10 || strlen($code) > 20) {
            return null;
        }

        // Codes never contain 0/O/1/I/L, so a code with one is not ours.
        return strspn($code, self::ALPHABET) === strlen($code) ? $code : null;
    }

    public static function serial(int $sequence, ?int $year = null): string
    {
        $prefix = (string) config('legacy.tags.serial_prefix', 'FLT');

        return sprintf('%s-%d-%06d', $prefix, $year ?? (int) now()->format('Y'), $sequence);
    }
}
