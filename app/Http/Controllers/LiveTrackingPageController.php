<?php

namespace App\Http\Controllers;

use App\Models\TrackingShare;
use App\Queries\Tracking\GetLiveTrackingSnapshot;
use Inertia\Inertia;
use Inertia\Response;

/**
 * `GET /live/{token}` (route `live.show`) — the family's live page
 * (SPEC §81-§83). Public, no account, throttled `live-view`. The token is
 * looked up by its SHA-256; revoked/expired/unknown all render the same
 * friendly page. Polling uses Inertia partial reloads of `snapshot`.
 */
class LiveTrackingPageController extends Controller
{
    public function show(string $token, GetLiveTrackingSnapshot $snapshot): Response
    {
        $share = TrackingShare::findByToken($token);

        if ($share === null || ! $share->isUsable()) {
            return Inertia::render('live/Show', ['available' => false, 'snapshot' => null, 'pollSeconds' => 0]);
        }

        if ($share->last_viewed_at === null || $share->last_viewed_at->lt(now()->subMinute())) {
            $share->forceFill(['last_viewed_at' => now()])->saveQuietly();
        }

        return Inertia::render('live/Show', [
            'available' => true,
            'snapshot' => fn () => $snapshot->handle($share),
            'pollSeconds' => (int) config('tracking.live_poll_seconds', 20),
            'map' => config('tracking.map'),
        ]);
    }
}
