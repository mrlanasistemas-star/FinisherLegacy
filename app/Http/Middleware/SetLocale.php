<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\I18n\Locales;
use App\Support\I18n\LocalizedUrls;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Decides the language of every request (docs/renovacion/idiomas.md).
 *
 * Web (`SetLocale`): explicit `/en` URL prefix (public pages only) →
 * the signed-in user's saved language → `fl_locale` cookie →
 * `Accept-Language` → Spanish.
 *
 * API (`SetLocale:api`): `X-Locale` header → `Accept-Language` → the
 * token owner's saved language → Spanish.
 *
 * Only languages an admin left enabled are ever resolved. A visitor who
 * explicitly chose a language and opens an unprefixed public page is sent
 * to its `/en` twin, so shared links always carry the language.
 */
class SetLocale
{
    public function __construct(private readonly LocalizedUrls $urls) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $channel = 'web'): Response
    {
        if ($channel === 'api') {
            $this->apply($this->forApi($request));

            $response = $next($request);
            $response->headers->set('Content-Language', app()->getLocale());

            return $response;
        }

        $prefix = $this->urls->prefix();

        if ($prefix !== null) {
            // `/en/admin`, a disabled language…: the page has no twin.
            if (! Locales::isEnabled($prefix) || ! $this->urls->isLocalizable()) {
                return redirect()->to($this->urls->to(Locales::source()));
            }

            $this->apply($prefix);
            $this->remember($request, $prefix);

            Inertia::resolveUrlUsing(fn (Request $request): string => Str::start(
                Str::after($this->urls->to($prefix, $request->fullUrl()), $request->getSchemeAndHttpHost()),
                '/',
            ));

            return $next($request);
        }

        [$locale, $explicit] = $this->forWeb($request);

        if ($explicit
            && $locale !== Locales::source()
            && config('i18n.prefix.redirect', true)
            && $this->urls->isLocalizable()
        ) {
            return redirect()->to($this->urls->to($locale));
        }

        $this->apply($locale);

        return $next($request);
    }

    /**
     * @return array{0: string, 1: bool} the language and whether the person chose it
     */
    private function forWeb(Request $request): array
    {
        $user = $request->user();

        if ($user instanceof User && ($saved = Locales::match($user->locale)) !== null) {
            return [$saved, true];
        }

        $cookie = $request->cookie((string) config('i18n.cookie', 'fl_locale'));

        if (is_string($cookie) && ($remembered = Locales::match($cookie)) !== null) {
            return [$remembered, true];
        }

        return [$this->fromBrowser($request) ?? $this->fallback(), false];
    }

    private function forApi(Request $request): string
    {
        $header = $request->header('X-Locale');

        if (is_string($header) && ($asked = Locales::match($header)) !== null) {
            return $asked;
        }

        if (($browser = $this->fromBrowser($request)) !== null) {
            return $browser;
        }

        // The `auth:sanctum` route middleware runs after this group
        // middleware, so the token is resolved here (and reused there).
        if ($request->bearerToken() !== null) {
            $user = $request->user('sanctum');

            if ($user instanceof User && ($saved = Locales::match($user->locale)) !== null) {
                return $saved;
            }
        }

        return $this->fallback();
    }

    private function fromBrowser(Request $request): ?string
    {
        if (! config('i18n.detect_browser', true)) {
            return null;
        }

        return Locales::fromAcceptLanguage($request->header('Accept-Language'));
    }

    private function fallback(): string
    {
        // Config only (no lookup of the admin switches): this is the hot path.
        $default = (string) config('i18n.default');

        return Locales::supports($default) ? $default : Locales::source();
    }

    private function apply(string $locale): void
    {
        app()->setLocale($locale);
        Carbon::setLocale($locale);
    }

    /** Following an `/en` link is an explicit choice: keep it for the next pages. */
    private function remember(Request $request, string $locale): void
    {
        $name = (string) config('i18n.cookie', 'fl_locale');

        if ($request->cookie($name) !== $locale) {
            Cookie::queue(Cookie::make($name, $locale, 60 * 24 * 365, '/', null, null, false, false, 'lax'));
        }
    }
}
