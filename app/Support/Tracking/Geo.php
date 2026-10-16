<?php

namespace App\Support\Tracking;

use App\Models\EventRace;

class Geo
{
    private const EARTH_RADIUS_M = 6371008.8;

    /** Great-circle distance in meters. */
    public static function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * self::EARTH_RADIUS_M * asin(min(1.0, sqrt($a)));
    }

    /** An EventRace's distance in meters, or null when unknown. */
    public static function raceDistanceMeters(?EventRace $race): ?int
    {
        if ($race === null || $race->distance_value === null || (float) $race->distance_value <= 0) {
            return null;
        }

        $factor = match (strtolower((string) ($race->distance_unit ?: 'km'))) {
            'm', 'mts', 'metros' => 1.0,
            'mi', 'mile', 'miles', 'millas' => 1609.344,
            default => 1000.0,
        };

        return (int) round((float) $race->distance_value * $factor);
    }
}
