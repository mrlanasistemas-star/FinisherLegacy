<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Api\V1\Concerns\ResolvesAuthenticatedUser;
use App\Http\Controllers\Controller;
use App\Support\I18n\CatalogFiles;
use App\Support\I18n\Locales;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * Languages for the mobile app (docs/renovacion/idiomas.md §App):
 *
 * - `GET /api/v1/i18n` — the languages offered, with the version of each
 *   mobile catalog. Public.
 * - `GET /api/v1/i18n/{locale}?v=` — the mobile catalog of one language
 *   (`{locale, version, messages}`), with an ETag: new strings and new
 *   languages reach installed apps without a store release. Public, cached.
 * - `PATCH /api/v1/me/locale` — saves the signed-in person's language
 *   (emails and push are sent in it).
 */
class I18nController extends Controller
{
    use ApiResponses;
    use ResolvesAuthenticatedUser;

    public function index(): JsonResponse
    {
        $locales = [];

        foreach (Locales::enabled() as $code) {
            $locales[] = [
                'code' => $code,
                'name' => Locales::all()[$code],
                'regional' => Locales::regional($code),
                'source' => $code === Locales::source(),
                'version' => $code === Locales::source() ? null : $this->version($code),
            ];
        }

        return $this->respond(['source' => Locales::source(), 'locales' => $locales])
            ->header('Cache-Control', 'public, max-age=300');
    }

    public function show(Request $request, string $locale): Response
    {
        abort_unless(Locales::isEnabled($locale), 404);

        $version = $this->version($locale);

        $response = new JsonResponse([
            'data' => [
                'locale' => $locale,
                'version' => $version,
                // Spanish is the source: the text in the code is the text.
                'messages' => (object) ($locale === Locales::source() ? [] : CatalogFiles::read(CatalogFiles::mobile($locale))),
            ],
            'message' => null,
            'meta' => (object) ['request_id' => $request->attributes->get('request_id')],
        ], 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $response->setEtag($version);
        $response->headers->set(
            'Cache-Control',
            $request->query('v') === $version ? 'public, max-age=31536000, immutable' : 'public, max-age=300',
        );
        $response->isNotModified($request);

        return $response;
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'locale' => ['required', 'string', Rule::in(Locales::enabled())],
        ]);

        $user = $this->sanctumUser($request);
        $user->forceFill(['locale' => $data['locale']])->save();
        app()->setLocale((string) $data['locale']);

        return $this->respond(['locale' => $user->locale], __('Idioma actualizado.'));
    }

    private function version(string $locale): string
    {
        return CatalogFiles::version(CatalogFiles::mobile($locale)) ?? 'empty';
    }
}
