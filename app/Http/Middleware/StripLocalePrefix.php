<?php

namespace App\Http\Middleware;

use App\Support\I18n\Locales;
use App\Support\I18n\LocalizedUrls;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `/en/colecciones` is served by the very same route as `/colecciones`
 * (docs/renovacion/idiomas.md §URLs): this GLOBAL middleware runs before
 * routing, removes the language prefix from the request path and remembers
 * it in a request attribute. No route is registered twice, route names and
 * Wayfinder stay untouched; App\Http\Middleware\SetLocale then decides
 * whether that page really exists in that language.
 *
 * Only GET/HEAD requests are rewritten: forms always post to the
 * unprefixed URL. It must stay the OUTERMOST global middleware (it
 * re-initializes the request).
 */
class StripLocalePrefix
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return $next($request);
        }

        $path = $request->getPathInfo();
        $segments = explode('/', ltrim($path, '/'), 2);

        if (! in_array($segments[0], Locales::targets(), true)) {
            return $next($request);
        }

        $query = $request->server->get('QUERY_STRING');
        $stripped = '/'.($segments[1] ?? '');

        $server = $request->server->all();
        $server['REQUEST_URI'] = $request->getBaseUrl().$stripped.(is_string($query) && $query !== '' ? '?'.$query : '');
        unset($server['PATH_INFO'], $server['ORIG_PATH_INFO'], $server['UNENCODED_URL'], $server['HTTP_X_ORIGINAL_URL'], $server['HTTP_X_REWRITE_URL']);

        $request->initialize(
            $request->query->all(),
            $request->request->all(),
            [...$request->attributes->all(), LocalizedUrls::PREFIX_ATTRIBUTE => $segments[0]],
            $request->cookies->all(),
            $request->files->all(),
            $server,
            $request->getContent(),
        );

        return $next($request);
    }
}
