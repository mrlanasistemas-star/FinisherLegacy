<?php

namespace App\Http\Controllers\I18n;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\I18n\Locales;
use App\Support\I18n\LocalizedUrls;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * `POST /idioma` — the language switcher (docs/renovacion/idiomas.md).
 * Remembers the choice in the `fl_locale` cookie and, when signed in, in
 * the account (`users.locale`, also used for emails and push), then sends
 * the person to the SAME page in that language: `/en/…` for public pages,
 * the unchanged URL for private ones. Always a full page load, so the
 * document language, the catalog and the hreflang tags are all fresh.
 */
class LocaleController extends Controller
{
    public function __invoke(Request $request, LocalizedUrls $urls): Response
    {
        $data = $request->validate([
            'locale' => ['required', 'string', Rule::in(Locales::enabled())],
            'url' => ['nullable', 'string', 'max:2000'],
        ]);

        $locale = (string) $data['locale'];
        $user = $request->user();

        if ($user instanceof User && $user->locale !== $locale) {
            $user->forceFill(['locale' => $locale])->save();
        }

        Cookie::queue(Cookie::make((string) config('i18n.cookie', 'fl_locale'), $locale, 60 * 24 * 365, '/', null, null, false, false, 'lax'));

        $root = rtrim($request->root(), '/');
        $from = is_string($data['url'] ?? null) && $data['url'] !== '' ? $data['url'] : url()->previous();

        if (str_starts_with($from, '/') && ! str_starts_with($from, '//')) {
            $from = $root.$from;
        }

        // Never redirect outside this site.
        $target = $from === $root || str_starts_with($from, $root.'/') || str_starts_with($from, $root.'?')
            ? $urls->to($locale, $from)
            : $urls->to($locale, $root);

        return $request->header('X-Inertia') ? Inertia::location($target) : redirect()->to($target);
    }
}
