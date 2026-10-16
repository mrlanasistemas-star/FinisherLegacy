<?php

namespace App\Support\Tracking;

use App\Models\TrackingSession;

/**
 * The owner-facing session shape — shared by the API (start/stop/show)
 * and the athlete web card so both speak the same contract.
 */
class TrackingSessionPresenter
{
    /** @return array<string, mixed> */
    public static function present(TrackingSession $session): array
    {
        return [
            'uuid' => $session->uuid,
            'status' => $session->status->value,
            'source' => $session->source->value,
            'started_at' => $session->started_at->toIso8601String(),
            'stopped_at' => $session->stopped_at?->toIso8601String(),
            'batch_max' => (int) config('tracking.batch_max', 100),
            'distance_m' => $session->distance_m,
            'points_count' => $session->points_count,
            'last_point_at' => $session->last_point_at?->toIso8601String(),
            'last_battery' => $session->last_battery,
        ];
    }
}
