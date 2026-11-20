<?php

namespace App\Enums;

/**
 * How a training workout got into the athlete's history: trained live with
 * the app's timer (and GPS when outdoor), or registered afterwards by hand.
 * Manual workouts never count toward pace records.
 */
enum WorkoutSource: string
{
    case Live = 'live';
    case Manual = 'manual';
}
