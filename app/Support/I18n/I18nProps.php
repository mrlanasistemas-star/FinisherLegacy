<?php

namespace App\Support\I18n;

/**
 * What every Inertia page knows about languages
 * (docs/renovacion/idiomas.md §Web) — merged into the shared props by
 * App\Http\Middleware\HandleInertiaRequests:
 *
 * - `locale`   the language of this page ("en"),
 * - `locales`  the languages offered to the public, with the URL of THIS
 *              page in each one (what <LocaleSwitcher> renders),
 * - `i18n`     the source language, the regional tag and the version of the
 *              active web catalog (`/lang/{locale}.json?v={version}`).
 */
class I18nProps
{
    /**
     * @return array<string, mixed>
     */
    public static function shared(): array
    {
        $locale = app()->getLocale();
        $source = Locales::source();

        return [
            'locale' => $locale,
            'locales' => function (): array {
                $urls = app(LocalizedUrls::class);
                $names = Locales::all();
                $offered = [];

                foreach (Locales::enabled() as $code) {
                    $offered[] = [
                        'code' => $code,
                        'name' => $names[$code],
                        'regional' => Locales::regional($code),
                        'url' => $urls->to($code),
                    ];
                }

                return $offered;
            },
            'i18n' => [
                'source' => $source,
                'regional' => Locales::regional($locale),
                'version' => $locale === $source ? null : (CatalogFiles::version(CatalogFiles::web($locale)) ?? 'empty'),
            ],
        ];
    }
}
