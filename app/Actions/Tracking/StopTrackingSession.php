<?php

namespace App\Actions\Tracking;

use App\Enums\TrackingSessionStatus;
use App\Models\TrackingSession;

/** Idempotent stop — a stopped session stays exactly as it was. */
class StopTrackingSession
{
    public function handle(TrackingSession $session): TrackingSession
    {
        if (! $session->isActive()) {
            return $session;
        }

        $session->update([
            'status' => TrackingSessionStatus::Stopped,
            'stopped_at' => now(),
        ]);

        return $session;
    }
}
