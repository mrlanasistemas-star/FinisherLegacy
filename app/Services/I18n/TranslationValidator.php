<?php

namespace App\Services\I18n;

use App\Support\I18n\Text;

/**
 * Refuses machine translations that would break the interface
 * (docs/renovacion/idiomas.md §Validación): empty text, a lost or invented
 * placeholder, changed HTML tags, a different number of line breaks or
 * plural forms, a missing brand term, a leaked translator note or an
 * absurd length.
 */
class TranslationValidator
{
    /** Returns null when the translation is acceptable, otherwise why not. */
    public function check(string $source, string $translation): ?string
    {
        if (trim($translation) === '') {
            return 'empty';
        }

        // `Nombre||nombre completo del atleta`: the note is for the
        // translator only — it is not text, and must not come back.
        if (str_contains($translation, Text::NOTE_SEPARATOR)) {
            return 'note';
        }

        $source = Text::stripNote($source);

        if ($this->placeholders($source) !== $this->placeholders($translation)) {
            return 'placeholders';
        }

        if ($this->tags($source) !== $this->tags($translation)) {
            return 'html';
        }

        if (substr_count($source, "\n") !== substr_count($translation, "\n")) {
            return 'line-breaks';
        }

        if (substr_count($source, '|') !== substr_count($translation, '|')) {
            return 'plural-forms';
        }

        /** @var list<string> $glossary */
        $glossary = config('i18n.glossary', []);

        foreach ($glossary as $term) {
            if (substr_count($source, $term) > substr_count($translation, $term)) {
                return 'glossary';
            }
        }

        $sourceLength = mb_strlen(trim($source));
        $length = mb_strlen(trim($translation));

        if ($length > $sourceLength * 4 + 24 || ($sourceLength >= 24 && $length < $sourceLength / 5)) {
            return 'length';
        }

        return null;
    }

    /**
     * Sorted list of the placeholders in a text: `:name`, `{count}`,
     * `{{ value }}`, `%s`.
     *
     * @return list<string>
     */
    public function placeholders(string $text): array
    {
        preg_match_all('~\{\{\s*.*?\s*\}\}|\{[A-Za-z_][\w.]*\}|(?<![\w:/]):[A-Za-z_]\w*|%[sd]~u', $text, $matches);

        $found = array_map(fn (string $placeholder): string => (string) preg_replace('/\s+/', '', $placeholder), $matches[0]);
        sort($found);

        return $found;
    }

    /**
     * Sorted list of the HTML tags (with attributes) in a text.
     *
     * @return list<string>
     */
    private function tags(string $text): array
    {
        preg_match_all('~</?[A-Za-z][^<>]*>~', $text, $matches);

        $found = $matches[0];
        sort($found);

        return $found;
    }
}
