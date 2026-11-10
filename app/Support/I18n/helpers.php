<?php

/**
 * Global i18n helpers (docs/renovacion/idiomas.md), loaded by
 * App\Providers\I18nServiceProvider.
 */

use App\Support\I18n\ContentTranslations;
use App\Support\I18n\LocalizedUrls;
use Illuminate\Database\Eloquent\Model;

if (! function_exists('localized_url')) {
    /**
     * The current page (or `$url`) in another language:
     * `localized_url('en')` → https://…/en/colecciones. Spanish URLs carry
     * no prefix. Private pages are returned unprefixed.
     */
    function localized_url(?string $locale = null, ?string $url = null): string
    {
        return app(LocalizedUrls::class)->to($locale ?? app()->getLocale(), $url);
    }
}

if (! function_exists('localized_route')) {
    /**
     * `route()` in the active (or given) language.
     *
     * @param  array<array-key, mixed>|mixed  $parameters
     */
    function localized_route(string $name, mixed $parameters = [], ?string $locale = null): string
    {
        return localized_url($locale, route($name, $parameters));
    }
}

if (! function_exists('tr')) {
    /**
     * An admin-entered field in the active language, falling back to the
     * Spanish original: `tr($product, 'name')`.
     */
    function tr(Model $model, string $field, ?string $locale = null): mixed
    {
        return app(ContentTranslations::class)->get($model, $field, $locale);
    }
}
