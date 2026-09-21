<?php

namespace App\Queries\Athletes;

use App\Models\Athlete;
use App\Models\AthleteEventMedia;
use App\Models\AthleteOwnedProduct;
use App\Models\EventParticipant;
use App\Models\Medal;
use App\Models\Order;
use App\Models\Plate;
use Illuminate\Database\Eloquent\Collection;

/**
 * Everything a canonical Athlete has done across every event — the query
 * that proves "1 Athlete, N events" visually (docs/adr/0004 §57-60), now
 * also the source `GET /api/v1/me/events` reuses verbatim. Extended for
 * the commerce ecosystem (brief §35/§39/§104): a history that only showed
 * participations/plates/medals and silently dropped photos, video, and
 * physical products wouldn't actually be "todo lo que el Athlete ha
 * hecho" — event media and owned products are now first-class parts of
 * this Query, not a second read model built elsewhere.
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
     * }  $filters  Every key applies only to `participations` — the other
     *              collections stay "everything the Athlete has", since a
     *              filter like `athlete_owned_product_id` has no sensible
     *              meaning against, say, the orders list (product
     *              consolidation brief §23: "no duplicar la Query si
     *              GetAthleteHistory puede aceptar filtro DTO").
     * @return array{
     *     participations: Collection<int, EventParticipant>,
     *     plates: Collection<int, Plate>,
     *     medals: Collection<int, Medal>,
     *     media: Collection<int, AthleteEventMedia>,
     *     owned_products: Collection<int, AthleteOwnedProduct>,
     *     orders: Collection<int, Order>,
     * }
     */
    public function handle(Athlete $athlete, array $filters = []): array
    {
        return [
            'participations' => $athlete->eventParticipations()
                ->with(['eventEdition.event', 'eventRace', 'result', 'legacyPlateEntitlements', 'gearSelections'])
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
                ->get(),
            'plates' => $athlete->plates()
                ->with(['eventEdition.event', 'legacyCode'])
                ->orderByDesc('created_at')
                ->get(),
            'medals' => $athlete->medals()
                ->with(['event', 'eventEdition'])
                ->orderByDesc('event_date')
                ->get(),
            'media' => $athlete->eventMedia()
                ->with('eventParticipant.eventEdition.event')
                ->orderBy('sort_order')
                ->get(),
            'owned_products' => $athlete->ownedProducts()
                ->with(['product', 'productVariant'])
                ->orderByDesc('acquired_at')
                ->get(),
            'orders' => $athlete->orders()
                ->with('items')
                ->orderByDesc('created_at')
                ->get(),
        ];
    }
}
