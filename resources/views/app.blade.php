<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Finisher Legacy transforma cada logro deportivo en una historia que puedes conservar, revivir y compartir.">

        <meta name="application-name" content="Finisher Legacy">
        <meta name="apple-mobile-web-app-title" content="Finisher Legacy">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="theme-color" content="#F8F7F3">
        <meta name="msapplication-TileColor" content="#F8F7F3">

        {{-- Open Graph — this is the site-wide default. Any page can
             override og:title/og:description/og:image via its own
             Inertia <Head>; Inertia dedupes on matching property/name, so
             a page-level tag replaces this one instead of duplicating it. --}}
        <meta property="og:site_name" content="Finisher Legacy">
        <meta property="og:type" content="website">
        <meta property="og:locale" content="es_MX">
        <meta property="og:title" content="Finisher Legacy — Tu esfuerzo merece una historia">
        <meta property="og:description" content="Finisher Legacy transforma cada logro deportivo en una historia que puedes conservar, revivir y compartir.">
        <meta property="og:image" content="{{ asset('images/brand/og-finisher-legacy.png') }}">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta property="og:image:type" content="image/png">
        <meta property="og:image:alt" content="Finisher Legacy — Tu historia. Tu legado.">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="Finisher Legacy — Tu esfuerzo merece una historia">
        <meta name="twitter:description" content="Finisher Legacy transforma cada logro deportivo en una historia que puedes conservar, revivir y compartir.">
        <meta name="twitter:image" content="{{ asset('images/brand/og-finisher-legacy.png') }}">
        <meta name="twitter:image:alt" content="Finisher Legacy — Tu historia. Tu legado.">

        {{-- Organization + WebSite structured data — site-wide, safe to
             repeat on every page (Google just reads the first valid one
             per type it finds; duplicate identical JSON-LD across pages
             is normal, not a penalty). Page-specific structured data
             (SportsEvent, ProfilePage) is added per-page via Inertia
             <Head> and is additive, not a replacement for this. --}}
        {{-- '@@context' (not '@context'): Blade has a real @context
             directive registered in this app (unrelated to JSON-LD) that
             silently rewrites the literal text "@context" anywhere in this
             file, including inside this PHP string — '@@' is Blade's
             escape for a literal '@'. Confirmed by curling the rendered
             page; don't "clean this up" back to '@context', it breaks the
             JSON output. --}}
        <script type="application/ld+json">
            {!! json_encode([
                '@@context' => 'https://schema.org',
                '@graph' => [
                    [
                        '@type' => 'Organization',
                        '@id' => rtrim(config('app.url'), '/').'/#organization',
                        'name' => 'Finisher Legacy',
                        'url' => config('app.url'),
                        'logo' => asset('images/brand/logo/logo-mark-gold.png'),
                        'description' => 'Finisher Legacy transforma cada logro deportivo en una historia que puedes conservar, revivir y compartir.',
                    ],
                    [
                        '@type' => 'WebSite',
                        '@id' => rtrim(config('app.url'), '/').'/#website',
                        'name' => 'Finisher Legacy',
                        'url' => config('app.url'),
                        'inLanguage' => 'es-MX',
                        'publisher' => ['@id' => rtrim(config('app.url'), '/').'/#organization'],
                        'potentialAction' => [
                            '@type' => 'SearchAction',
                            'target' => [
                                '@type' => 'EntryPoint',
                                'urlTemplate' => rtrim(config('app.url'), '/').'/events?q={search_term_string}',
                            ],
                            'query-input' => 'required name=search_term_string',
                        ],
                    ],
                ],
            ], JSON_UNESCAPED_SLASHES) !!}
        </script>

        {{-- Light is the only theme: paint the warm paper background before
             CSS loads so there is never a dark/white flash. --}}
        <meta name="color-scheme" content="light">
        <style>
            html {
                background-color: #f8f7f3;
            }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="icon" href="/favicon-32x32.png" sizes="32x32" type="image/png">
        <link rel="icon" href="/favicon-16x16.png" sizes="16x16" type="image/png">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <link rel="manifest" href="/site.webmanifest">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Finisher Legacy') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
