<?php

namespace App\Actions\Training\Workouts;

use App\Enums\WorkoutStatus;
use App\Models\TrainingWorkout;
use Illuminate\Support\Facades\Log;

/**
 * Safety net for workouts left `in_progress` by accident (the app was
 * killed, the phone died, the athlete forgot to tap Terminar). Any live
 * workout whose last heartbeat is older than `training.workouts.stale_after_hours`
 * is closed at that last heartbeat:
 *
 * - with at least `stale_min_completed_seconds` of timer → `completed`
 *   (keeps the last numbers the phone reported);
 * - otherwise → `discarded` (an accidental tap is not a workout).
 *
 * `auto_closed_at` marks the guess, so a late finish from the phone's
 * offline queue may still replace it with the real data (FinishWorkout).
 */
class CloseStaleWorkouts
{
    public function handle(): int
    {
        $hours = (int) config('training.workouts.stale_after_hours', 12);
        $minSeconds = (int) config('training.workouts.stale_min_completed_seconds', 60);
        $cutoff = now()->subHours($hours);
        $closed = 0;

        TrainingWorkout::query()
            ->where('status', WorkoutStatus::InProgress)
            ->where(fn ($q) => $q->where('last_heartbeat_at', '<', $cutoff)
                ->orWhere(fn ($q) => $q->whereNull('last_heartbeat_at')->where('started_at', '<', $cutoff)))
            ->chunkById(200, function ($workouts) use ($minSeconds, &$closed) {
                foreach ($workouts as $workout) {
                    /** @var TrainingWorkout $workout */
                    $lastSeen = $workout->last_heartbeat_at ?? $workout->started_at;
                    $workout->fill([
                        'status' => $workout->duration_s >= $minSeconds ? WorkoutStatus::Completed : WorkoutStatus::Discarded,
                        'ended_at' => $lastSeen->max($workout->started_at),
                        'auto_closed_at' => now(),
                    ]);
                    $workout->deriveAverages();
                    $workout->save();
                    $closed++;
                }
            });

        if ($closed > 0) {
            Log::info('training.workouts_auto_closed', ['count' => $closed]);
        }

        return $closed;
    }
}
