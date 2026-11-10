<?php

namespace App\Support;

use App\Models\EventRace;

/**
 * The single place an EventRace's distance_value/distance_unit turns into
 * the display string a plate's `distance` field freezes — so "42.195 km"
 * vs "10 km" vs "70.3 mi" formatting never gets reinvented (and drifts)
 * across the athlete history, previews, and any future caller.
 */
class RaceDistanceFormatter
{
    public static function format(?EventRace $race): ?string
    {
        if ($race === null || $race->distance_value === null) {
            return null;
        }

        $value = (float) $race->distance_value;
        $unit = $race->distance_unit ?: 'km';

        // Trim to at most 3 decimals, but never show trailing zeros
        // ("42.195 km" keeps them, "10.000 km" becomes "10 km").
        $formatted = rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');

        return "{$formatted} {$unit}";
    }
}
