<?php

namespace App\Enums;

/**
 * Lifecycle of a training workout. Only one `in_progress` workout per
 * athlete at a time (StartWorkout). `discarded` rows are kept out of the
 * history and stats; stale `in_progress` rows are closed by
 * `training:close-stale-workouts`.
 */
enum WorkoutStatus: string
{
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Discarded = 'discarded';
}
