<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every request (web + api) gets a correlation id — reuses the caller's
 * `X-Request-ID` if it is a sane token (so the mobile app / a proxy can
 * trace a request end-to-end), otherwise generates a UUID.
 *
 *  - Echoed back on the response header.
 *  - Available as `request()->attributes->get('request_id')` for the API
 *    error envelopes (ApiExceptionRenderer / DeviceExceptionRenderer).
 *  - Added to Laravel's Context, so it is appended to EVERY log line of this
 *    request and automatically carried into queued jobs dispatched from it
 *    (docs/ARCHITECTURE.md §Observability). Never logged with a secret.
 *
 * Incoming ids are validated (charset + length) — a client must not be able
 * to inject newlines or huge strings into our logs.
 */
class AssignRequestId
{
    public const string HEADER = 'X-Request-ID';

    public function handle(Request $request, Closure $next): Response
    {
        $incoming = (string) $request->header(self::HEADER, '');
        $requestId = preg_match('/^[A-Za-z0-9._:\-]{8,128}$/', $incoming) === 1
            ? $incoming
            : (string) Str::uuid();

        $request->attributes->set('request_id', $requestId);
        Context::add('request_id', $requestId);

        $response = $next($request);
        $response->headers->set(self::HEADER, $requestId);

        return $response;
    }
}
