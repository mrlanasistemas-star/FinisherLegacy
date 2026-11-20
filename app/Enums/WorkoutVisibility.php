<?php

namespace App\Enums;

/**
 * Who may see a workout once the athlete decides to show it. Private by
 * default; sharing to the community always goes through an explicit Legacy
 * Moment the athlete creates (never auto-published).
 */
enum WorkoutVisibility: string
{
    case Private = 'private';
    case Followers = 'followers';
    case Public = 'public';
}
