<?php

/*
|--------------------------------------------------------------------------
| Idiomas y traducciones (docs/renovacion/idiomas.md)
|--------------------------------------------------------------------------
|
| Spanish is the source of truth: the Spanish text IS the key (gettext
| style). Adding a language = one entry in `locales` + `php artisan
| i18n:sync`. Nothing else is translated by hand: the sync command extracts
| every string from the web, the backend and the mobile app and sends the
| missing ones to the configured translator.
|
*/

use App\Models\Collection;
use App\Models\CollectionDesign;
use App\Models\CompanyMilestone;
use App\Models\PhotoExpansionPack;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductContentSection;
use App\Models\ProductVariant;
use App\Models\Sport;

$locales = [
    'es' => 'Español',
    'en' => 'English',
];

// Optional override: APP_SUPPORTED_LOCALES="es,en,pt" keeps only (and
// orders) the listed codes; unknown codes are shown with their code as name.
$override = array_values(array_filter(array_map('trim', explode(',', (string) env('APP_SUPPORTED_LOCALES', '')))));

if ($override !== []) {
    $locales = array_combine($override, array_map(fn (string $code): string => $locales[$code] ?? strtoupper($code), $override));
}

return [
    // code => native name (what the language switcher shows).
    'locales' => $locales,

    // The language every string is written in. Never translated.
    'source' => 'es',

    // Language of a request when nothing else decides.
    'default' => env('APP_LOCALE', 'es'),

    // Regional tags used for <html lang>, hreflang, og:locale and Intl.
    'regional' => [
        'es' => 'es-MX',
        'en' => 'en-US',
    ],

    // Cookie that remembers the visitor's explicit choice (1 year, not
    // encrypted — see bootstrap/app.php — so the browser can read it).
    'cookie' => 'fl_locale',

    // Read `Accept-Language` when nothing more explicit is known. Disabled
    // in phpunit.xml: Symfony's test client always sends "en-us,en".
    'detect_browser' => (bool) env('I18N_DETECT_BROWSER', true),

    /*
    | Public URL strategy: Spanish URLs are unchanged, every other locale
    | lives under `/{locale}` and serves the SAME routes (the prefix is
    | stripped before routing — App\Http\Middleware\StripLocalePrefix).
    */
    'prefix' => [
        // A visitor who explicitly chose a language (cookie / account) and
        // opens an unprefixed public page is redirected to its twin.
        'redirect' => (bool) env('I18N_REDIRECT_TO_PREFIX', true),

        // Paths (Str::is patterns, no leading slash) that are never
        // localized: short links, private areas, files and tooling.
        'except' => [
            'admin', 'admin/*', 'dashboard', 'dashboard/*', 'settings', 'settings/*',
            'operator/*', 'imports/*', 'ally/*', 'fotografo', 'fotografo/*',
            'api/*', 'l/*', 't/*', 'n/*', 'media/*', 'storage/*', 'lang/*',
            'support-messages/*', 'preregistrations/*', 'sitemap.xml', 'robots.txt',
            'up', 'horizon', 'horizon/*', '_inertia/*', 'broadcasting/*', 'idioma',
            'login', 'register', 'logout', 'forgot-password', 'reset-password', 'reset-password/*',
            'two-factor-challenge', 'user/*', 'email/*', 'entrar', 'auth/*',
            'mi-legacy', 'mi-legacy/*', 'checkout', 'checkout/*', 'carrito', 'carrito/*',
            '*.svg', '*.png', '*.jpg', '*.pdf', '*.json', '*.xml',
        ],
    ],

    /*
    | Automatic translation. `null` translates nothing (default while no
    | key is configured). The owner chooses the model: cost vs quality.
    */
    'translator' => env('I18N_TRANSLATOR', 'null'),

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        // Exact model id, no date suffix. Alternatives: claude-sonnet-5-5,
        // claude-haiku-5-5 (cheaper, faster).
        'model' => env('I18N_ANTHROPIC_MODEL', 'claude-opus-5-5'),
        // low | medium | high — translating short UI strings needs little.
        'effort' => env('I18N_ANTHROPIC_EFFORT', 'low'),
        'max_tokens' => (int) env('I18N_ANTHROPIC_MAX_TOKENS', 16000),
        // Models that accept the server-side refusal fallback.
        'fallback_models' => ['claude-opus-5-5', 'claude-sonnet-5-5', 'claude-fable-5-2'],
    ],

    'deepl' => [
        'api_key' => env('DEEPL_API_KEY'),
        // Empty = picked from the key (":fx" keys use the free endpoint).
        'url' => env('DEEPL_API_URL'),
        'targets' => ['en' => 'EN-US', 'pt' => 'PT-BR'],
    ],

    // Strings per translator request and attempts for an invalid result.
    'batch_size' => (int) env('I18N_BATCH_SIZE', 40),
    'retries' => 1,

    /*
    | USD per million tokens, used ONLY to estimate what a sync run cost
    | (admin page). Published list prices on 2026-11-03 — check the
    | provider's pricing page before relying on them.
    */
    'pricing' => [
        'claude-opus-5-5' => ['input' => 4.00, 'output' => 20.00, 'cache_read' => 0.20, 'cache_write' => 5.00],
        'claude-sonnet-5-5' => ['input' => 2.00, 'output' => 10.00, 'cache_read' => 0.10, 'cache_write' => 2.50],
        'claude-haiku-5-5' => ['input' => 0.10, 'output' => 0.50, 'cache_read' => 0.01, 'cache_write' => 0.125],
    ],

    // Never translated, in any language (the validator rejects a translation
    // that loses one of these).
    'glossary' => [
        'Finisher Legacy', 'MAKE IT LAST', 'Make it last', 'Legacy Tag', 'Tag', 'Legacy Plate',
        'Legacy ID', 'Legacy Code', 'Legacy', 'Racepack', 'Trisuit', 'Chill Band', 'NFC',
        'OpenPay', 'Strava', 'Garmin', 'Apple', 'Google',
    ],

    /*
    | Preferred translations per language (docs/renovacion/idiomas.md
    | §Glosario): Spanish term => how the brand says it. Given to the
    | automatic translator so every pass uses the same vocabulary as the
    | texts already translated; a person can still word a sentence freely.
    */
    'terms' => [
        'en' => [
            'Recuerdo (la historia de una carrera)' => 'Memory',
            'Recuerdos' => 'Memories',
            'Entrenamientos' => 'Training',
            'Entrenamiento (una sesión)' => 'Workout',
            'Modo entrenamiento' => 'Training mode',
            'Envíame ánimo' => 'Cheer me on',
            'Mensaje de ánimo / mensaje de apoyo' => 'Cheer message',
            'Equipo de apoyo' => 'Cheer crew',
            'Sesión de apoyo' => 'Cheer session',
            'Portarretrato inteligente' => 'Smart Photo Frame',
            'Mensaje de voz' => 'Voice message',
            'Colecciones' => 'Collections',
            'Comunidad' => 'Community',
            'Momento' => 'Moment',
            'Activar' => 'Activate',
            'Dorsal / número de corredor' => 'Bib number ("Bib" on a short label)',
            'Ritmo' => 'Pace',
            'Meta' => 'Finish line',
            'Salida' => 'Start line',
            'Pieza (Tag, prenda o accesorio con NFC)' => 'Piece',
            'Equipo (lo que usa el atleta) / gear' => 'Gear',
            'Mi equipo' => 'My gear',
            'Prerregistro' => 'Pre-registration',
            'Seguimiento en vivo' => 'Live tracking',
            'Acerca tu teléfono' => 'Hold your phone close',
            'Posición general / por categoría / por rama' => 'Overall place / Category place / Gender place',
            'Entrega de kits' => 'Packet pickup',
            'Iniciar sesión / Cerrar sesión' => 'Log in / Log out',
            'Aviso de privacidad' => 'Privacy Notice',
            'Carrito' => 'Cart',
            'Pedido' => 'Order',
            'Agotado' => 'Sold out',
        ],
    ],

    /*
    | Where the strings live.
    */
    'paths' => [
        // group => [directory (relative to base_path) => extensions]
        'ui' => ['resources/js' => ['vue', 'ts']],
        'email' => ['app' => ['php'], 'resources/views' => ['php'], 'routes' => ['php']],
    ],

    // The mobile repository's `src` folder (relative to base_path or
    // absolute). When it does not exist (production), the last synced
    // source list in lang/mobile/es.json is used instead.
    'mobile_path' => env('I18N_MOBILE_PATH', '../FinisherLegacyMobile/src'),

    /*
    | Admin-entered content translated automatically on save
    | (App\Jobs\I18n\TranslateModelFields). User-generated content
    | (stories, comments, cheer messages) must never be listed here.
    */
    'models' => [
        Product::class => ['name', 'tagline', 'description', 'material', 'strap_type', 'dimensions', 'instructions', 'seo_title', 'seo_description'],
        ProductVariant::class => ['name'],
        Collection::class => ['name', 'tagline', 'description', 'discipline', 'tag_discipline_line', 'tag_back_line', 'seo_title', 'seo_description'],
        ProductCategory::class => ['name', 'description'],
        PhotoExpansionPack::class => ['name', 'description'],
        ProductContentSection::class => ['title'],
        CompanyMilestone::class => ['title', 'description'],
        Sport::class => ['name'],
        CollectionDesign::class => ['name'],
    ],
];
