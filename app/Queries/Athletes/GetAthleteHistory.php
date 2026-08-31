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
     * @return array{
     *     participations: Collection<int, EventParticipant>,
     *     plates: Collection<int, Plate>,
     *     medals: Collection<int, Medal>,
     *     media: Collection<int, AthleteEventMedia>,
     *     owned_products: Collection<int, AthleteOwnedProduct>,
     *     orders: Collection<int, Order>,
     * }
     */
    public function handle(Athlete $athlete): array
    {
        return [
            'participations' => $athlete->eventParticipations()
                ->with(['eventEdition.event', 'eventRace', 'result'])
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
