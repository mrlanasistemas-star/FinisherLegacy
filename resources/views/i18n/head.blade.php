{{--
    Language tags of the document head (docs/renovacion/idiomas.md §SEO),
    included by resources/views/app.blade.php.

    - hreflang alternates + x-default, only on public pages that exist in
      every language (never on private or admin pages).
    - The active locale / catalog version for resources/js/i18n, so the
      browser can fetch the catalog before the app mounts.
--}}
@php
    $i18nUrls = app(\App\Support\I18n\LocalizedUrls::class);
    $i18nLocale = app()->getLocale();
    $i18nSource = \App\Support\I18n\Locales::source();
    $i18nVersion = $i18nLocale === $i18nSource
        ? null
        : (\App\Support\I18n\CatalogFiles::version(\App\Support\I18n\CatalogFiles::web($i18nLocale)) ?? 'empty');
@endphp
<meta name="fl-locale" content="{{ $i18nLocale }}">
@if ($i18nVersion !== null)
    <meta name="fl-catalog" content="{{ url('lang/'.$i18nLocale.'.json') }}?v={{ $i18nVersion }}">
    <link rel="preload" as="fetch" crossorigin="anonymous" href="{{ url('lang/'.$i18nLocale.'.json') }}?v={{ $i18nVersion }}">
@endif
@if ($i18nUrls->isLocalizable())
    @foreach ($i18nUrls->alternates() as $i18nHreflang => $i18nHref)
        <link rel="alternate" hreflang="{{ $i18nHreflang }}" href="{{ $i18nHref }}">
    @endforeach
@endif
