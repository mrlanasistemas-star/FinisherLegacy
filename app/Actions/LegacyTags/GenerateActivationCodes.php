<?php

namespace App\Actions\LegacyTags;

use App\Models\LegacyTag;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Proof of purchase for self-activated NFC tags: the secret activation code
 * printed on the card inside the packaging (docs/renovacion/manual-activacion-nfc.md).
 *
 * - Format XXXX-XXXX-XXXX over Crockford base32 (no I, L, O, U), CSPRNG,
 *   ≈ 60 bits — separate from (and unrelated to) the public code on the chip.
 * - Only an HMAC-SHA256 keyed by APP_KEY is stored (activation_code_hash)
 *   plus the last 4 characters for support. The code in clear exists only
 *   in the return value / CSV produced at generation time.
 * - Fills ONLY tags that have no code yet (atomic `whereNull` update), so it
 *   is safe to re-run and never rotates a code already printed.
 *
 * The same keyed hash is used for chip UIDs (nfcUidHash): a UID is never
 * stored in clear.
 */
class GenerateActivationCodes
{
    public const string ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public const int LENGTH = 12;

    /**
     * @param  iterable<LegacyTag>  $tags
     * @return list<array{tag: LegacyTag, code: string}> the codes in clear — show/print once, never store
     */
    public function handle(iterable $tags): array
    {
        $generated = [];

        foreach ($tags as $tag) {
            if ($tag->activation_code_hash !== null) {
                continue;
            }

            $code = $this->uniqueCode();
            $hash = self::hash($code);

            $updated = LegacyTag::query()
                ->whereKey($tag->id)
                ->whereNull('activation_code_hash')
                ->update(['activation_code_hash' => $hash, 'activation_code_last4' => substr($code, -4)]);

            if ($updated === 1) {
                $tag->forceFill(['activation_code_hash' => $hash, 'activation_code_last4' => substr($code, -4)])->syncOriginal();
                $generated[] = ['tag' => $tag, 'code' => $code];
            }
        }

        return $generated;
    }

    /**
     * Writes the printable CSV (codes in clear) to the private disk and
     * returns its path relative to storage/app/private. Delete it once the
     * cards are printed.
     *
     * @param  list<array{tag: LegacyTag, code: string}>  $rows
     */
    public function writeCsv(array $rows, string $label): string
    {
        $path = 'exports/activation-codes/'.preg_replace('/[^A-Za-z0-9_-]+/', '-', $label).'-'.now()->format('Ymd-His').'-'.Str::lower(Str::random(6)).'.csv';

        $stream = fopen('php://temp', 'w+');

        if ($stream === false) {
            throw new RuntimeException('Unable to open a temporary stream for the CSV.');
        }

        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, ['serial', 'public_code', 'display_code', 'activation_code', 'lot_id']);

        foreach ($rows as $row) {
            fputcsv($stream, [
                $row['tag']->serial,
                $row['tag']->public_code,
                $row['tag']->displayCode(),
                self::format($row['code']),
                $row['tag']->inventory_lot_id,
            ]);
        }

        rewind($stream);
        Storage::disk('local')->put($path, (string) stream_get_contents($stream));
        fclose($stream);

        return $path;
    }

    public static function generateCode(): string
    {
        $code = '';

        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $code;
    }

    /** XXXX-XXXX-XXXX for print and screens. */
    public static function format(string $code): string
    {
        return implode('-', str_split($code, 4));
    }

    /**
     * What a person types → canonical code, or null when it cannot be one.
     * Crockford decoding: case-insensitive, O→0, I/L→1, separators ignored.
     */
    public static function normalize(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $code = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $input));
        $code = strtr($code, ['O' => '0', 'I' => '1', 'L' => '1']);

        if (strlen($code) !== self::LENGTH) {
            return null;
        }

        return strspn($code, self::ALPHABET) === self::LENGTH ? $code : null;
    }

    /** Keyed hash stored on legacy_tags.activation_code_hash. */
    public static function hash(string $normalizedCode): string
    {
        return hash_hmac('sha256', 'legacy-activation-code|'.$normalizedCode, self::key());
    }

    /**
     * Chip UID as the phone reports it ("04:A2:2B:…" / "04a22b…") → upper
     * hex, or null when it is not a plausible NFC UID (4, 7 or 10 bytes).
     */
    public static function normalizeUid(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $hex = strtoupper((string) preg_replace('/[^A-Fa-f0-9]/', '', $input));

        return in_array(strlen($hex), [8, 14, 20], true) ? $hex : null;
    }

    /** Keyed hash of a normalized chip UID (never stored in clear). */
    public static function nfcUidHash(string $normalizedUid): string
    {
        return hash_hmac('sha256', 'legacy-nfc-uid|'.$normalizedUid, self::key());
    }

    private function uniqueCode(): string
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $code = self::generateCode();

            if (! LegacyTag::query()->where('activation_code_hash', self::hash($code))->exists()) {
                return $code;
            }
        }

        throw new RuntimeException('Unable to generate a unique activation code.');
    }

    private static function key(): string
    {
        $key = (string) config('app.key');

        if ($key === '') {
            throw new RuntimeException('APP_KEY is required to hash activation codes.');
        }

        return $key;
    }
}
