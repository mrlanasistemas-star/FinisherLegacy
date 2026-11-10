<?php

namespace App\Services\I18n\Translators;

use App\Contracts\I18n\Translator;

/**
 * Translates nothing. The default while no translator key is configured:
 * strings stay "missing" and the site keeps showing the Spanish original.
 */
class NullTranslator implements Translator
{
    public function translateBatch(array $strings, string $from, string $to, array $context = []): array
    {
        return [];
    }
}
