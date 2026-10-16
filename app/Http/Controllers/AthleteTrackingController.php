<?php

namespace App\Http\Controllers;

use App\Actions\Athletes\EnsureAthleteForUser;
use App\Actions\Tracking\CreateTrackingDevicePairing;
use App\Actions\Tracking\CreateTrackingShare;
use App\Actions\Tracking\RevokeTrackingDevice;
use App\Actions\Tracking\RevokeTrackingShare;
use App\Models\EventParticipant;
use App\Models\TrackingDevice;
use App\Models\TrackingSession;
use App\Models\TrackingShare;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Athlete web side of "Seguimiento en vivo" (SPEC §77-§82), inside the
 * participation detail (Mi legado). Start/stop are deliberately absent:
 * GPS only starts from the app, with explicit consent (SPEC §97).
 * One-time secrets (share link, pairing code) travel back as flash data,
 * never as page props that would be re-sent on reload.
 */
class AthleteTrackingController extends Controller
{
    public function storeShare(Request $request, EventParticipant $participant, CreateTrackingShare $create): RedirectResponse
    {
        Gate::authorize('create', [TrackingSession::class, $participant]);

        $data = $request->validate([
            'expires_in_hours' => ['nullable', 'integer', 'min:1', 'max:'.(int) config('tracking.share_max_ttl_hours', 336)],
        ]);

        $result = $create->handle($participant, $request->user(), null, isset($data['expires_in_hours']) ? (int) $data['expires_in_hours'] : null);

        Inertia::flash('trackingShare', [
            'url' => $result['url'],
            'expires_at' => $result['share']->expires_at->toIso8601String(),
        ]);

        return back();
    }

    public function destroyShare(TrackingShare $share, RevokeTrackingShare $revoke): RedirectResponse
    {
        Gate::authorize('delete', $share);

        $revoke->handle($share);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Enlace desactivado. Ya nadie puede verlo.']);

        return back();
    }

    public function storeDevice(Request $request, EventParticipant $participant, EnsureAthleteForUser $ensureAthlete, CreateTrackingDevicePairing $pairing): RedirectResponse
    {
        Gate::authorize('create', [TrackingSession::class, $participant]);

        $data = $request->validate(['name' => ['nullable', 'string', 'max:80']]);
        $athlete = $ensureAthlete->handle($request->user(), 'tracking_device_pairing');

        $result = $pairing->handle($athlete, $participant, $data['name'] ?? null);

        Inertia::flash('trackingPairing', [
            'code' => $result['code'],
            'expires_at' => $result['device']->pairing_expires_at?->toIso8601String(),
        ]);

        return back();
    }

    public function destroyDevice(TrackingDevice $device, RevokeTrackingDevice $revoke): RedirectResponse
    {
        Gate::authorize('delete', $device);

        $revoke->handle($device);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Dispositivo desconectado.']);

        return back();
    }
}
