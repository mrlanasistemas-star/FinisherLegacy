<?php

namespace App\Actions\Training\Workouts;

use App\Enums\WorkoutSource;
use App\Enums\WorkoutStatus;
use App\Enums\WorkoutVisibility;
use App\Models\TrainingWorkout;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Creates a workout: a live one that starts the timer now (`in_progress`),
 * or a past one registered by hand (`manual`, created already `completed`).
 *
 * Idempotent by `client_uuid` — the app's offline queue may send the same
 * start twice; the second call returns the first row. A live start while
 * another live workout is still `in_progress` never creates a second one:
 * the existing workout comes back flagged as a conflict so the app can
 * offer "continuar / terminar" instead.
 */
class StartWorkout
{
    /**
     * @param  array<string, mixed>  $data  Validated StoreWorkoutRequest input.
     * @return array{workout: TrainingWorkout, created: bool, conflict: bool}
     */
    public function handle(User $user, array $data): array
    {
        $source = WorkoutSource::from((string) $data['source']);
        $clientUuid = isset($data['client_uuid']) ? strtolower((string) $data['client_uuid']) : null;

        return DB::transaction(function () use ($user, $data, $source, $clientUuid) {
            // Serializes starts of the same athlete (double taps, queue replays).
            User::query()->whereKey($user->id)->lockForUpdate()->first();

            if ($clientUuid !== null) {
                $existing = TrainingWorkout::query()->where('user_id', $user->id)->where('client_uuid', $clientUuid)->first();
                if ($existing !== null) {
                    return ['workout' => $existing, 'created' => false, 'conflict' => false];
                }
            }

            if ($source === WorkoutSource::Live) {
                $running = TrainingWorkout::query()
                    ->where('user_id', $user->id)
                    ->where('status', WorkoutStatus::InProgress)
                    ->latest('started_at')
                    ->first();

                if ($running !== null) {
                    return ['workout' => $running, 'created' => false, 'conflict' => true];
                }
            }

            $workout = new TrainingWorkout([
                'user_id' => $user->id,
                'athlete_id' => $user->athlete()->value('id'),
                'sport_id' => (int) $data['sport_id'],
                'client_uuid' => $clientUuid,
                'source' => $source,
                'visibility' => WorkoutVisibility::tryFrom((string) ($data['visibility'] ?? '')) ?? WorkoutVisibility::Private,
                'notes' => $data['notes'] ?? null,
            ]);

            if ($source === WorkoutSource::Live) {
                $startedAt = isset($data['started_at']) ? Carbon::parse((string) $data['started_at']) : now();
                $workout->fill([
                    'status' => WorkoutStatus::InProgress,
                    'uses_gps' => (bool) ($data['uses_gps'] ?? false),
                    // A start queued offline keeps the real start time (bounded by the request rules).
                    'started_at' => $startedAt->min(now()),
                    'duration_s' => 0,
                    'last_heartbeat_at' => now(),
                ]);
            } else {
                $startedAt = Carbon::parse((string) $data['started_at']);
                $duration = (int) $data['duration_s'];
                $workout->fill([
                    'status' => WorkoutStatus::Completed,
                    // A manual distance is the athlete's word, not a GPS trace.
                    'uses_gps' => false,
                    'started_at' => $startedAt,
                    'ended_at' => $startedAt->copy()->addSeconds($duration),
                    'duration_s' => $duration,
                    'distance_m' => isset($data['distance_m']) ? (int) $data['distance_m'] : null,
                    'elevation_gain_m' => isset($data['elevation_gain_m']) ? (int) $data['elevation_gain_m'] : null,
                ]);
                $workout->deriveAverages();
            }

            $workout->save();

            return ['workout' => $workout, 'created' => true, 'conflict' => false];
        });
    }
}
