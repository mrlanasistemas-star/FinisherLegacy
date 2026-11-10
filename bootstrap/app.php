<?php

use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnsureApiIdempotencyKey;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureUserToken;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\StripLocalePrefix;
use App\Support\Api\ApiExceptionRenderer;
use App\Support\I18n\I18nProps;
use App\Support\I18n\Locales;
use App\Support\I18n\LocalizedUrls;
use App\Support\Observability\OperationalLog;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state', 'fl_locale']);

        $middleware->web(append: [
            HandleAppearance::class,
            // Language of the request — before Inertia shares it (docs/renovacion/idiomas.md).
            SetLocale::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            EnsureUserIsActive::class,
        ]);

        // Suspended/blocked accounts lose API access immediately (403).
        $middleware->api(
            // Language first (docs/renovacion/idiomas.md): `auth:sanctum` and the
            // throttle are sorted ahead of anything appended, and their 401 / 429
            // messages must already come out in the language the client asked for.
            prepend: [SetLocale::class.':api'],
            append: [EnsureUserIsActive::class],
        );

        // Correlation id on every request (web + api) — docs/ARCHITECTURE.md §Observability.
        $middleware->prepend(AssignRequestId::class);

        // `/en/...` serves the same routes as `/...`: the language prefix is
        // removed before routing. Must stay the outermost middleware.
        $middleware->prepend(StripLocalePrefix::class);

        // Baseline throttle for every /api route (limiter `api`,
        // AppServiceProvider) — security audit A1: before this the `api`
        // limiter existed but was never applied.
        $middleware->throttleApi();

        // Route-level aliases: only the routes in routes/api.php that need
        // them opt in.
        $middleware->alias([
            'user.token' => EnsureUserToken::class,
            'api.idempotent' => EnsureApiIdempotencyKey::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Payment incidents → `payments` log channel (default reporting continues).
        $exceptions->report(function (Throwable $e) {
            OperationalLog::reportPaymentIncident($e, app()->bound('request') ? request() : null);
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Every `/api/*` path (auth, medals, events, integrations, …) uses
        // the unified `{"error": {...}}` envelope (docs/api/v1.md §Errores).
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return app(ApiExceptionRenderer::class)->render($e);
        });

        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return $response;
            }

            if ($response->getStatusCode() === 419) {
                return back()->with(['message' => __('Tu sesión expiró. Intenta de nuevo.')]);
            }

            if (! app()->environment(['local', 'testing']) && in_array($response->getStatusCode(), [404, 403, 500, 503, 429], true)) {
                // An unknown URL never reaches SetLocale (no route, no `web`
                // group): keep the language of the address (/en/…) or the
                // visitor's saved choice, so the error page is not in Spanish.
                if (app()->getLocale() === Locales::source()) {
                    $cookie = $request->cookies->get((string) config('i18n.cookie', 'fl_locale'));
                    $locale = Locales::match(app(LocalizedUrls::class)->prefix() ?? (is_string($cookie) ? $cookie : null));

                    if ($locale !== null) {
                        app()->setLocale($locale);
                    }
                }

                // …and no HandleInertiaRequests either: the page still needs
                // its language props (catalog, switcher).
                return Inertia::render('errors/Error', ['status' => $response->getStatusCode(), ...I18nProps::shared()])
                    ->toResponse($request)
                    ->setStatusCode($response->getStatusCode());
            }

            return $response;
        });
    })->create();
