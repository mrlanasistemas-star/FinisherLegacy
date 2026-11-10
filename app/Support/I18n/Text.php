<?php

namespace App\Support\I18n;

use NumberFormatter;

/**
 * Small text rules shared by `__()`, the extractor and the validator
 * (docs/renovacion/idiomas.md §Notas para quien traduce, §Ordinales).
 */
class Text
{
    /**
     * Separates a source string from its translator note:
     * `__('Nombre||nombre completo del atleta')`. The note tells the
     * translator WHICH "Nombre" this is; it is part of the key (so two
     * contexts get two translations) and is never shown in any language.
     */
    public const string NOTE_SEPARATOR = '||';

    /** `"Nombre||nombre completo"` → `"Nombre"`. */
    public static function stripNote(string $text): string
    {
        $position = strpos($text, self::NOTE_SEPARATOR);

        return $position === false ? $text : rtrim(substr($text, 0, $position));
    }

    /** The translator note of a source string, or null when it has none. */
    public static function note(string $text): ?string
    {
        $position = strpos($text, self::NOTE_SEPARATOR);

        if ($position === false) {
            return null;
        }

        $note = trim(substr($text, $position + strlen(self::NOTE_SEPARATOR)));

        return $note === '' ? null : $note;
    }

    /**
     * A place as people write it: Spanish "1.º", English "1st". Any other
     * language uses its own rules when `intl` knows them.
     */
    public static function ordinal(int $number, ?string $locale = null): string
    {
        $language = strtolower(substr($locale ?? app()->getLocale(), 0, 2));

        if ($language === 'es') {
            return $number.'.º';
        }

        if ($language === 'en') {
            $tens = abs($number) % 100;
            $suffix = $tens >= 11 && $tens <= 13 ? 'th' : (['th', 'st', 'nd', 'rd'][abs($number) % 10] ?? 'th');

            return $number.$suffix;
        }

        if (class_exists(NumberFormatter::class)) {
            $formatted = (new NumberFormatter($language, NumberFormatter::ORDINAL))->format($number);

            if (is_string($formatted) && $formatted !== '') {
                return $formatted;
            }
        }

        return $number.'.';
    }
}
