<?php

namespace App\Actions\Tracking;

use App\Enums\TrackingSessionStatus;
use App\Enums\TrackingSource;
use App\Models\EventParticipant;
use App\Models\TrackingDevice;
use App\Models\TrackingSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Starts live tracking for a participation (SPEC §77). Idempotent: at most
 * one `active` session per participant — a second start (app restart,
 * retry) returns the session already running. Starting fires the support
 * "start" messages.
 */
class StartTrackingSession
{
    public function __construct(private readonly TriggerTrackingSupportMessages $triggers) {}

    /**
     * @return array{session: TrackingSession, created: bool}
     */
    public function handle(
        EventParticipant $participant,
        TrackingSource $source = TrackingSource::MobileGps,
        ?TrackingDevice $device = null,
    ): array {
        $result = DB::transaction(function () use ($participant, $source, $device) {
            // Serialize concurrent starts for the same participation.
            EventParticipant::query()->whereKey($participant->id)->lockForUpdate()->first();

            $existing = TrackingSession::query()
                ->where('event_participant_id', $participant->id)
                ->where('status', TrackingSessionStatus::Active)
                ->latest('id')
                ->first();

            if ($existing !== null) {
                return ['session' => $existing, 'created' => false];
            }

            $session = TrackingSession::create([
                'uuid' => (string) Str::uuid(),
                'event_participant_id' => $participant->id,
                'source' => $source,
                'tracking_device_id' => $device?->id,
                'status' => TrackingSessionStatus::Active,
                'started_at' => now(),
                'distance_m' => 0,
                'points_count' => 0,
            ]);

            return ['session' => $session, 'created' => true];
        });

        if ($result['created']) {
            $this->triggers->handle($result['session']);
        }

        return $result;
    }
}
