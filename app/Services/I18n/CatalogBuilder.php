<?php

namespace App\Services\I18n;

use App\Models\TranslationString;
use App\Support\I18n\CatalogFiles;
use App\Support\I18n\Locales;

/**
 * Writes the catalog files from `translation_strings`
 * (docs/renovacion/idiomas.md §Archivos generados). The table is the
 * source of truth, so a translation edited by a person always wins and the
 * files can be rebuilt at any time.
 */
class CatalogBuilder
{
    /**
     * @param  list<string>|null  $locales  defaults to every target language
     * @return list<string> the files written
     */
    public function build(?array $locales = null): array
    {
        $written = [];

        foreach ($locales ?? Locales::targets() as $locale) {
            if ($locale === Locales::source()) {
                continue;
            }

            $byGroup = ['ui' => [], 'email' => [], 'mobile' => []];

            TranslationString::query()
                ->where('locale', $locale)
                ->whereIn('group', array_keys($byGroup))
                ->whereNull('obsolete_at')
                ->whereNotNull('translated_text')
                ->where('translated_text', '!=', '')
                ->orderBy('id')
                ->each(function (TranslationString $string) use (&$byGroup): void {
                    $byGroup[$string->group][$string->source_text] = (string) $string->translated_text;
                });

            $files = [
                CatalogFiles::laravel($locale) => $byGroup['ui'] + $byGroup['email'],
                CatalogFiles::web($locale) => $byGroup['ui'],
                CatalogFiles::mobile($locale) => $byGroup['mobile'],
            ];

            if (($bundle = CatalogFiles::mobileBundle($locale)) !== null) {
                $files[$bundle] = $byGroup['mobile'];
            }

            foreach ($files as $path => $messages) {
                CatalogFiles::write($path, $messages);
                $written[] = $path;
            }
        }

        // The mobile source list: lets a server WITHOUT the mobile
        // repository (production) keep translating the app's strings.
        $sources = TranslationString::query()
            ->where('group', 'mobile')
            ->whereNull('obsolete_at')
            ->distinct()
            ->pluck('source_text')
            ->all();

        if ($sources !== []) {
            CatalogFiles::write(CatalogFiles::mobile(Locales::source()), array_combine($sources, $sources));
            $written[] = CatalogFiles::mobile(Locales::source());
        }

        // Spanish needs no catalog in the app: the source text is the text.
        if (($bundle = CatalogFiles::mobileBundle(Locales::source())) !== null && ! is_file($bundle)) {
            CatalogFiles::write($bundle, []);
            $written[] = $bundle;
        }

        return $written;
    }
}
