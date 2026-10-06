<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Finisher Legacy has a single light theme — the stored `appearance`
 * cookie from older builds is ignored on purpose. Still shares the
 * variable so any view that reads it keeps rendering.
 */
class HandleAppearance
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        View::share('appearance', 'light');

        return $next($request);
    }
}
