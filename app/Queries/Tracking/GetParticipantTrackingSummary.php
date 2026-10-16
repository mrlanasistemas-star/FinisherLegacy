<?php

namespace App\Queries\Tracking;

use App\Enums\TrackingDeviceStatus;
use App\Models\EventParticipant;
use App\Models\TrackingDevice;
use App\Models\TrackingSession;
use App\Models\TrackingShare;
use App\Support\Tracking\Geo;
use App\Support\Tracking\TrackingSessionPresenter;

/**
 * The athlete's own "Seguimiento en vivo" card data for one participation
 * (web LiveTrackingCard + `GET /api/v1/me/events/{participant}/tracking`).
 * Share tokens are never re-shown — only their metadata.
 */
class GetParticipantTrackingSummary
{
    /** @return array<string, mixed> */
    public function handle(EventParticipant $participant): array
    {
        $session = TrackingSession::query()
            ->where('event_participant_id', $participant->id)
            ->latest('started_at')
            ->latest('id')
            ->first();

        $shares = TrackingShare::query()
            ->where('event_participant_id', $participant->id)
            ->usable()
            ->latest('id')
            ->limit(20)
            ->get();

        $devices = TrackingDevice::query()
            ->where('event_participant_id', $participant->id)
            ->whereIn('status', [TrackingDeviceStatus::Pending, TrackingDeviceStatus::Paired])
            ->latest('id')
            ->limit(5)
            ->get();

        return [
            'session' => $session === null ? null : TrackingSessionPresenter::present($session),
            'race_distance_m' => Geo::raceDistanceMeters($participant->eventRace()->first()),
            'shares' => $shares->map(fn (TrackingShare $share) => [
                'uuid' => $share->uuid,
                'created_at' => $share->created_at?->toIso8601String(),
                'expires_at' => $share->expires_at->toIso8601String(),
                'last_viewed_at' => $share->last_viewed_at?->toIso8601String(),
                'pinned_to_session' => $share->tracking_session_id !== null,
            ])->values()->all(),
            'devices' => $devices->map(fn (TrackingDevice $device) => [
                'uuid' => $device->uuid,
                'name' => $device->public_name,
                'status' => $device->status->value,
                'battery' => $device->battery,
                'last_seen_at' => $device->last_seen_at?->toIso8601String(),
                'pairing_expires_at' => $device->pairing_expires_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
