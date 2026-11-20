<?php

namespace App\Http\Controllers;

use App\Models\TrackingShare;
use App\Queries\Tracking\GetLiveTrackingSnapshot;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * `GET /live/{token}` (route `live.show`) — the family's live page
 * (SPEC §81-§83). Public, no account, throttled `live-view`. The token is
 * looked up by its SHA-256. Unknown/revoked render a friendly page with
 * HTTP 404, ended/expired one with HTTP 410 (`reason` tells the page which
 * copy to show) — never any athlete data. Polling uses Inertia partial
 * reloads of `snapshot`.
 */
class LiveTrackingPageController extends Controller
{
    public function show(Request $request, string $token, GetLiveTrackingSnapshot $snapshot): Response
    {
        $share = TrackingShare::findByToken($token);
        // Family shares are read signed-in through the API, never by token.
        $reason = $share === null || $share->audience !== TrackingShare::AUDIENCE_LINK ? 'revoked' : $share->unavailableReason();

        if ($reason !== null) {
            return Inertia::render('live/Show', [
                'available' => false,
                'reason' => $reason === 'revoked' ? 'unavailable' : 'ended',
                'snapshot' => null,
                'pollSeconds' => 0,
            ])->toResponse($request)->setStatusCode($reason === 'revoked' ? 404 : 410);
        }

        if ($share->last_viewed_at === null || $share->last_viewed_at->lt(now()->subMinute())) {
            $share->forceFill(['last_viewed_at' => now()])->saveQuietly();
        }

        return Inertia::render('live/Show', [
            'available' => true,
            'reason' => null,
            'appUrl' => sprintf('%s://live/%s', config('nfc.app.scheme', 'finisherlegacy'), $token),
            'snapshot' => fn () => $snapshot->handle($share),
            'pollSeconds' => (int) config('tracking.live_poll_seconds', 20),
            'map' => config('tracking.map'),
        ])->toResponse($request);
    }
}
