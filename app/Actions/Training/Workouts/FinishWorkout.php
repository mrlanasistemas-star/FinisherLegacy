<?php

namespace App\Actions\Training\Workouts;

use App\Enums\WorkoutStatus;
use App\Exceptions\WorkoutStateException;
use App\Models\TrainingWorkout;
use Illuminate\Support\Carbon;

/**
 * Closes a live workout with the phone's final numbers.
 *
 * - Idempotent: finishing an already completed workout (an offline queue
 *   replay) returns it unchanged.
 * - A workout the server auto-closed while the phone was offline (see
 *   CloseStaleWorkouts) accepts the late finish: the phone's data is the
 *   real one, so it replaces the server's guess.
 * - A workout the athlete discarded cannot be revived.
 * - A live family share (OpenWorkoutTrackingSession) ends with the workout.
 * - Distance-derived values are only kept when the workout used GPS, and a
 *   5 km / 10 km best effort only when the route really reached it.
 */
class FinishWorkout
{
    public function __construct(private readonly OpenWorkoutTrackingSession $tracking) {}

    /**
     * @param  array<string, mixed>  $data  Validated FinishWorkoutRequest input.
     */
    public function handle(TrainingWorkout $workout, array $data): TrainingWorkout
    {
        $autoClosed = $workout->auto_closed_at !== null;

        if ($workout->status === WorkoutStatus::Completed && ! $autoClosed) {
            return $workout;
        }

        if ($workout->status === WorkoutStatus::Discarded && ! $autoClosed) {
            throw new WorkoutStateException(__('Este entrenamiento ya fue descartado.'), 'workout_discarded');
        }

        $endedAt = isset($data['ended_at']) ? Carbon::parse((string) $data['ended_at']) : now();
        $endedAt = $endedAt->max($workout->started_at)->min(now()->addMinutes(5));

        // The timer can never exceed the wall-clock span (+1 min of clock skew).
        $wall = (int) $workout->started_at->diffInSeconds($endedAt, true);
        $duration = min((int) $data['duration_s'], $wall + 60);

        $workout->fill([
            'status' => WorkoutStatus::Completed,
            'ended_at' => $endedAt,
            'duration_s' => max(0, $duration),
            'auto_closed_at' => null,
            'last_heartbeat_at' => now(),
        ]);

        if (array_key_exists('notes', $data)) {
            $workout->notes = $data['notes'];
        }

        $this->applyGps($workout, $data);
        $workout->deriveAverages();
        $workout->save();

        // Family stops seeing the athlete the moment the workout ends.
        $this->tracking->close($workout);

        return $workout;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyGps(TrainingWorkout $workout, array $data): void
    {
        if (! $workout->uses_gps) {
            $workout->fill([
                'distance_m' => null,
                'moving_s' => null,
                'best_5k_s' => null,
                'best_10k_s' => null,
                'route_polyline' => null,
                'route_points_count' => 0,
            ]);

            return;
        }

        $distance = isset($data['distance_m']) ? (int) $data['distance_m'] : $workout->distance_m;
        $moving = isset($data['moving_s']) ? min((int) $data['moving_s'], $workout->duration_s) : null;

        $workout->fill([
            'distance_m' => $distance,
            'moving_s' => $moving,
            'elevation_gain_m' => isset($data['elevation_gain_m']) ? (int) $data['elevation_gain_m'] : null,
            'best_5k_s' => $this->bestEffort($data['best_5k_s'] ?? null, $distance, 5000, $workout->duration_s),
            'best_10k_s' => $this->bestEffort($data['best_10k_s'] ?? null, $distance, 10000, $workout->duration_s),
            'route_polyline' => $data['route_polyline'] ?? null,
            'route_points_count' => (int) ($data['route_points_count'] ?? 0),
        ]);
    }

    /** A best effort is only plausible inside a route at least that long, and never longer than the workout. */
    private function bestEffort(mixed $seconds, ?int $distance, int $meters, int $duration): ?int
    {
        if (! is_numeric($seconds) || $distance === null || $distance < $meters) {
            return null;
        }

        $value = (int) $seconds;

        return $value > 0 && $value <= max($duration, 1) ? $value : null;
    }
}
