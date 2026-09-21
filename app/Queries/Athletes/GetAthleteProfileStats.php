<?php

namespace App\Queries\Athletes;

use App\Models\Athlete;

/**
 * "Mi Perfil" stats (product consolidation brief §26) — counts only, no
 * collections loaded, since the profile header just needs numbers.
 * `total_distance_km` only sums races recorded in kilometers: mixing km
 * with miles (or with disciplines that have no linear distance, like a
 * triathlon's swim leg) wouldn't be a semantically valid total (brief
 * §26: "solamente si comparar distancias es semánticamente válido").
 */
class GetAthleteProfileStats
{
    /**
     * @return array{
     *     event_count: int,
     *     total_distance_km: float|null,
     *     legacy_plate_count: int,
     *     medal_count: int,
     *     gear_count: int,
     *     media_count: int,
     * }
     */
    public function handle(Athlete $athlete): array
    {
        $totalDistanceKm = $athlete->eventParticipations()
            ->join('event_races', 'event_participants.event_race_id', '=', 'event_races.id')
            ->where('event_races.distance_unit', 'km')
            ->sum('event_races.distance_value');

        return [
            'event_count' => $athlete->eventParticipations()->count(),
            'total_distance_km' => $totalDistanceKm > 0 ? round((float) $totalDistanceKm, 1) : null,
            'legacy_plate_count' => $athlete->plates()->count(),
            'medal_count' => $athlete->medals()->count(),
            'gear_count' => $athlete->ownedProducts()->count(),
            'media_count' => $athlete->eventMedia()->count(),
        ];
    }
}
