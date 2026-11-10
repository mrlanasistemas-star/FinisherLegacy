<?php

namespace App\Contracts\I18n;

/**
 * A machine translator (docs/renovacion/idiomas.md §Traductor). Drivers:
 * `anthropic` (Claude), `deepl` and `null` — chosen with I18N_TRANSLATOR.
 */
interface Translator
{
    /**
     * Translates a batch of strings.
     *
     * The result keeps the keys of `$strings`. A string the driver could
     * not translate is simply ABSENT from the result (never an empty or
     * made-up value); the caller validates every returned text.
     *
     * @param  array<array-key, string>  $strings
     * @param  array<string, mixed>  $context  hints for the driver: `surface`
     *                                         (web | mobile | email | content), `note`, …
     * @return array<array-key, string>
     */
    public function translateBatch(array $strings, string $from, string $to, array $context = []): array;
}
