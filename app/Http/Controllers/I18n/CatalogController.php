<?php

namespace App\Http\Controllers\I18n;

use App\Http\Controllers\Controller;
use App\Support\I18n\CatalogFiles;
use App\Support\I18n\Locales;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `GET /lang/{locale}.json?v={version}` — the catalog the browser loads
 * when the language is not Spanish (docs/renovacion/idiomas.md §Web).
 * Stateless (no session, no cookies) and cacheable: the `v` parameter is
 * the catalog's content hash, so a versioned URL never changes.
 */
class CatalogController extends Controller
{
    public function __invoke(Request $request, string $locale): Response
    {
        abort_unless(Locales::supports($locale) && $locale !== Locales::source(), 404);

        $path = CatalogFiles::web($locale);
        $version = CatalogFiles::version($path) ?? 'empty';

        $response = new JsonResponse([
            'locale' => $locale,
            'version' => $version,
            'messages' => (object) CatalogFiles::read($path),
        ], 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $response->setEtag($version);
        $response->headers->set(
            'Cache-Control',
            $request->query('v') === $version ? 'public, max-age=31536000, immutable' : 'public, max-age=300',
        );
        $response->isNotModified($request);

        return $response;
    }
}
