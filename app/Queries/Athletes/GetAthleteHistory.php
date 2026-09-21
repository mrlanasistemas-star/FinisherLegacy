<?php

namespace App\Queries\Athletes;

use App\Models\Athlete;
use App\Models\EventParticipant;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * "1 Athlete, N events" (docs/adr/0004 §57-60), participation-centric and
 * paginated (product consolidation brief §27-§28: a history screen must
 * never load every event/medal/media/order/product the Athlete has ever
 * had just to show 25 rows). Each EventParticipant here carries summary
 * counts, not the full related collections — a caller that needs the
 * complete list of owned products or orders uses the dedicated
 * GetAthleteOwnedProducts Query or /api/v1/orders, never this one.
 */
class GetAthleteHistory
{
    /**
     * @param  array{
     *     from?: string|null,
     *     to?: string|null,
     *     event_id?: int|null,
     *     sport_id?: int|null,
     *     event_race_id?: int|null,
     *     legacy_plate?: string|null,
     *     athlete_owned_product_id?: int|null,
     * }  $filters
     * @return LengthAwarePaginator<int, EventParticipant>
     */
    public function handle(Athlete $athlete, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return $athlete->eventParticipations()
            ->with(['eventEdition.event', 'eventRace', 'result'])
            ->with(['legacyPlateEntitlements' => fn ($q) => $q->latest('created_at')->limit(1)])
            ->withCount(['medals', 'media', 'gearSelections'])
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->whereHas(
                'eventEdition',
                fn ($edition) => $edition->whereDate('event_date', '>=', $from),
            ))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->whereHas(
                'eventEdition',
                fn ($edition) => $edition->whereDate('event_date', '<=', $to),
            ))
            ->when($filters['event_id'] ?? null, fn ($q, $eventId) => $q->whereHas(
                'eventEdition',
                fn ($edition) => $edition->where('event_id', $eventId),
            ))
            ->when($filters['sport_id'] ?? null, fn ($q, $sportId) => $q->whereHas(
                'eventEdition.event',
                fn ($event) => $event->where('sport_id', $sportId),
            ))
            ->when($filters['event_race_id'] ?? null, fn ($q, $raceId) => $q->where('event_race_id', $raceId))
            ->when($filters['legacy_plate'] ?? null, fn ($q, $status) => $status === 'none'
                ? $q->whereDoesntHave('legacyPlateEntitlements')
                : $q->whereHas('legacyPlateEntitlements', fn ($entitlement) => $entitlement->where('status', $status)))
            ->when($filters['athlete_owned_product_id'] ?? null, fn ($q, $ownedProductId) => $q->whereHas(
                'gearSelections',
                fn ($gear) => $gear->where('athlete_owned_product_id', $ownedProductId),
            ))
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * The one row shape every consumer of this Query renders — Web and
     * API alike — so a participation's summary can't drift between them.
     *
     * @return array<string, mixed>
     */
    public static function summarize(EventParticipant $participant): array
    {
        return [
            'id' => $participant->id,
            'event' => $participant->eventEdition?->event?->name,
            'edition' => $participant->eventEdition?->name,
            'race' => $participant->eventRace?->name,
            'bib_number' => $participant->bib_number,
            'event_date' => $participant->eventEdition?->event_date?->toDateString(),
            'result' => $participant->result ? [
                'official_time' => $participant->result->official_time,
                'pace' => $participant->result->pace,
                'overall_position' => $participant->result->overall_position,
            ] : null,
            'legacy_plate_status' => $participant->legacyPlateEntitlements->first()?->status->value,
            'medal_count' => $participant->medals_count,
            'gear_count' => $participant->gear_selections_count,
            'media_count' => $participant->media_count,
        ];
    }
}
