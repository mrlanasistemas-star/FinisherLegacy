<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

/**
 * Rejects community text (moment captions, comments, bios) that contains a
 * term from config('moderation.blocked_terms') — the automatic filter App
 * Store guideline 1.2 and Google Play's UGC policy require before content
 * is posted. Reports, blocks and the admin moderation queue cover the rest.
 *
 * Matching is whole-word on a normalised copy of the text (lower case, no
 * accents, common leetspeak undone, letter runs collapsed), so "computadora"
 * never matches "puta" but "PUUUT4" does.
 */
class NoObjectionableContent implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        if (self::containsBlockedTerm($value)) {
            $fail('Tu texto incluye lenguaje que no está permitido en la comunidad. Revísalo e inténtalo de nuevo.');
        }
    }

    public static function containsBlockedTerm(string $text): bool
    {
        $haystack = ' '.self::normalize($text).' ';

        foreach ((array) config('moderation.blocked_terms', []) as $term) {
            $needle = self::normalize((string) $term);

            if ($needle !== '' && str_contains($haystack, ' '.$needle.' ')) {
                return true;
            }
        }

        return false;
    }

    /** Lower case, ASCII, leetspeak undone, single spaces, letter runs collapsed. */
    private static function normalize(string $text): string
    {
        $text = Str::lower(Str::ascii($text));
        $text = strtr($text, ['0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's', '7' => 't', '@' => 'a', '$' => 's']);
        $text = (string) preg_replace('/[^a-z]+/', ' ', $text);
        // "puuuuta" → "puta"; legitimate double letters (ll, rr, cc, ee, oo)
        // collapse identically in the term list, so matching stays symmetric.
        $text = (string) preg_replace('/([a-z])\1+/', '$1', $text);

        return trim((string) preg_replace('/\s+/', ' ', $text));
    }
}
