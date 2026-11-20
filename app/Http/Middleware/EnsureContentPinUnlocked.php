<?php

namespace App\Http\Middleware;

use App\Models\LegacyStory;
use App\Services\Privacy\ContentPin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Web routes that render a memory (`legacy/{story}`, `mi-legacy/{story}/…`):
 * when it is PIN-protected and the viewer is its owner without a session
 * unlock grant, GETs go to the unlock page and everything else gets 423.
 * Non-owners are not handled here — LegacyStoryPolicy already denies them
 * a protected memory, whatever its visibility.
 */
class EnsureContentPinUnlocked
{
    public function __construct(private readonly ContentPin $pins) {}

    public function handle(Request $request, Closure $next): Response
    {
        $story = $request->route('story');

        if (is_string($story)) {
            $story = LegacyStory::query()->where('public_id', $story)->first();
        }

        if ($story instanceof LegacyStory
            && $story->isPinProtected()
            && $story->isOwnedBy($request->user())
            && ! $this->pins->reveals($story, $request)) {
            if ($request->isMethod('GET') && ! $request->expectsJson()) {
                return redirect()->route('content-pin.show', ['redirect' => $request->getRequestUri()]);
            }

            abort(423, __('Este contenido está protegido con NIP. Desbloquéalo para continuar.'));
        }

        return $next($request);
    }
}
