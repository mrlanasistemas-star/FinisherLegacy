<?php

namespace App\Actions\Tracking;

use App\Actions\Support\GetTriggeredSupportMessages;
use App\Enums\SupportSessionStatus;
use App\Events\SupportMessageTriggered;
use App\Models\AthleteSupportMessage;
use App\Models\AthleteSupportSession;
use App\Models\TrackingSession;
use App\Support\Tracking\Geo;

/**
 * Live tracking → "Mi equipo de apoyo" bridge (SPEC §84). Reuses the
 * existing GetTriggeredSupportMessages resolution (start / distance /
 * finish), then marks each newly-due message `triggered_at` exactly once
 * (conditional update, safe under concurrent ingest) and broadcasts
 * `support.message.triggered` to the athlete's private channel.
 *
 * Finish uses the support session's own target distance, falling back to
 * the race distance when the athlete didn't set one.
 */
class TriggerTrackingSupportMessages
{
    public function __construct(private readonly GetTriggeredSupportMessages $resolver) {}

    /** @return int how many messages were newly triggered */
    public function handle(TrackingSession $session): int
    {
        $participant = $session->eventParticipant()->with(['athlete:id,user_id', 'eventRace'])->first();

        if ($participant === null) {
            return 0;
        }

        $supportSession = AthleteSupportSession::query()
            ->where('event_participant_id', $participant->id)
            ->whereIn('status', [SupportSessionStatus::Open, SupportSessionStatus::Active])
            ->latest('id')
            ->first();

        if ($supportSession === null) {
            return 0;
        }

        // In-memory only (never saved): lets the shared resolver evaluate
        // "finish" against the race distance when no target was set.
        $probe = clone $supportSession;
        if ($probe->target_distance_meters === null) {
            $probe->target_distance_meters = Geo::raceDistanceMeters($participant->eventRace);
        }

        $due = $this->resolver->handle($probe, (int) $session->distance_m)
            ->filter(fn (AthleteSupportMessage $m) => $m->triggered_at === null);

        $userId = $participant->athlete->user_id ?? $participant->user_id;
        $count = 0;

        foreach ($due as $message) {
            $claimed = AthleteSupportMessage::query()
                ->whereKey($message->id)
                ->whereNull('triggered_at')
                ->update(['triggered_at' => now()]);

            if ($claimed !== 1) {
                continue;
            }

            $count++;

            if ($userId !== null) {
                $message->loadMissing('contributor');
                SupportMessageTriggered::dispatch((int) $userId, $message, $session->uuid);
            }
        }

        return $count;
    }
}
