<?php

namespace App\Queries\Tracking;

use App\Enums\TrackingSessionStatus;
use App\Models\EventEdition;
use App\Models\TrackingSession;
use App\Models\TrackingShare;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Admin event workspace → Tracking tab (read-only, SPEC §9/§75). One row
 * per tracking session of the edition, active first.
 */
class GetEventTrackingOverview
{
    /**
     * @return array{stats: array<string, int>, sessions: LengthAwarePaginator<int, array<string, mixed>>}
     */
    public function handle(EventEdition $edition, int $perPage = 50): array
    {
        $base = TrackingSession::query()
            ->whereHas('eventParticipant', fn ($q) => $q->where('event_edition_id', $edition->id));

        $stats = [
            'active' => (clone $base)->where('status', TrackingSessionStatus::Active)->count(),
            'total' => (clone $base)->count(),
            'shares' => TrackingShare::query()->usable()
                ->whereHas('eventParticipant', fn ($q) => $q->where('event_edition_id', $edition->id))
                ->count(),
        ];

        $sessions = $base
            ->with(['eventParticipant:id,event_race_id,full_name,first_name,last_name,bib_number', 'eventParticipant.eventRace:id,name'])
            ->orderByRaw('case when status = ? then 0 else 1 end', [TrackingSessionStatus::Active->value])
            ->orderByDesc('last_point_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (TrackingSession $s) => $this->row($s));

        return ['stats' => $stats, 'sessions' => $sessions];
    }

    /**
     * @return array<string, mixed>
     */
    private function row(TrackingSession $s): array
    {
        return [
            'uuid' => $s->uuid,
            'participant' => trim((string) ($s->eventParticipant->full_name ?: $s->eventParticipant->first_name.' '.$s->eventParticipant->last_name)),
            'bib_number' => $s->eventParticipant->bib_number,
            'race' => $s->eventParticipant->eventRace?->name,
            'status' => $s->status->value,
            'source' => $s->source->value,
            'distance_m' => $s->distance_m,
            'battery' => $s->last_battery,
            'started_at' => $s->started_at->toIso8601String(),
            'last_point_at' => $s->last_point_at?->toIso8601String(),
            'position' => $s->last_lat !== null && $s->last_lng !== null ? [round($s->last_lat, 5), round($s->last_lng, 5)] : null,
        ];
    }
}
