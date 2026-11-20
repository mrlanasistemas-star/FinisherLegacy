<?php

namespace App\Actions\Tracking;

use App\Enums\TrackingSessionStatus;
use App\Models\TrackingSession;
use App\Models\TrackingShare;

/**
 * Idempotent stop — a stopped session stays exactly as it was. Every
 * explicit share of this session ("hasta que termine" / 2-6-24 h, both
 * family and public link) ends here too, so nobody sees a location after
 * the athlete stops (docs/renovacion/familia.md).
 */
class StopTrackingSession
{
    public function __construct(private readonly EndTrackingShares $end) {}

    public function handle(TrackingSession $session): TrackingSession
    {
        if (! $session->isActive()) {
            return $session;
        }

        $session->update([
            'status' => TrackingSessionStatus::Stopped,
            'stopped_at' => now(),
        ]);

        $this->end->end(TrackingShare::query()->where('tracking_session_id', $session->id)->where('ends_with_session', true));

        return $session;
    }
}
