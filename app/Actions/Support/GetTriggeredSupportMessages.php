<?php

namespace App\Actions\Support;

use App\Enums\SupportMessageStatus;
use App\Enums\SupportTriggerType;
use App\Models\AthleteSupportMessage;
use App\Models\AthleteSupportSession;
use Illuminate\Support\Collection;

/**
 * Which approved messages should play at a given GPS distance (product UX
 * consolidation brief §36-§38) — the backend never measures GPS itself; a
 * future mobile app sends `current_distance_meters` and this resolves
 * which messages are due, excluding whatever it says is already consumed
 * (idempotent — GPS fluctuation must never double-play a message).
 *
 * `manual`-trigger messages are deliberately excluded here: those play
 * only when the athlete/app explicitly triggers them, never from distance
 * polling — a distance-based sweep including them would play them the
 * moment the activity starts, which isn't "manual" at all.
 */
class GetTriggeredSupportMessages
{
    /**
     * @param  list<int>  $alreadyConsumedIds
     * @return Collection<int, AthleteSupportMessage>
     */
    public function handle(AthleteSupportSession $session, int $currentDistanceMeters, array $alreadyConsumedIds = []): Collection
    {
        $targetDistance = $session->target_distance_meters;

        return $session->messages()
            ->where('status', SupportMessageStatus::Approved)
            ->whereNotIn('id', $alreadyConsumedIds)
            ->where(function ($query) use ($currentDistanceMeters, $targetDistance) {
                $query->where('trigger_type', SupportTriggerType::Start)
                    ->orWhere(fn ($q) => $q->where('trigger_type', SupportTriggerType::Distance)
                        ->where('trigger_distance_meters', '<=', $currentDistanceMeters));

                if ($targetDistance !== null && $currentDistanceMeters >= $targetDistance) {
                    $query->orWhere('trigger_type', SupportTriggerType::Finish);
                }
            })
            ->orderBy('trigger_distance_meters')
            ->get();
    }
}
