<?php

namespace App\Actions\Training\Workouts;

use App\Actions\Tracking\StopTrackingSession;
use App\Enums\TrackingSessionStatus;
use App\Enums\TrackingSource;
use App\Exceptions\WorkoutStateException;
use App\Models\TrackingSession;
use App\Models\TrainingWorkout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Makes a running outdoor workout followable by family: opens (or reuses)
 * the TrackingSession linked to it, so the phone can stream points through
 * the existing tracking uploader and the athlete can share it with the
 * existing `tracking/sessions/{uuid}/sharing` endpoints
 * (docs/renovacion/familia.md).
 *
 * Opened only when the athlete taps "Compartir con mi familia" — a workout
 * never uploads its location unless the athlete chose to let someone follow
 * it. Closed (and every share ended) when the workout finishes or is
 * discarded — see close().
 */
class OpenWorkoutTrackingSession
{
    public function __construct(private readonly StopTrackingSession $stop) {}

    public function handle(TrainingWorkout $workout): TrackingSession
    {
        if (! $workout->isInProgress()) {
            throw new WorkoutStateException(__('Solo puedes compartir un entrenamiento en curso.'), 'workout_not_in_progress');
        }

        if (! $workout->uses_gps) {
            throw new WorkoutStateException(__('Este entrenamiento no usa GPS, así que no hay ubicación que compartir.'), 'workout_without_gps');
        }

        return DB::transaction(function () use ($workout) {
            TrainingWorkout::query()->whereKey($workout->id)->lockForUpdate()->first();

            $existing = TrackingSession::query()
                ->where('training_workout_id', $workout->id)
                ->where('status', TrackingSessionStatus::Active)
                ->latest('id')
                ->first();

            return $existing ?? TrackingSession::create([
                'uuid' => (string) Str::uuid(),
                'event_participant_id' => null,
                'user_id' => $workout->user_id,
                'training_workout_id' => $workout->id,
                'source' => TrackingSource::MobileGps,
                'status' => TrackingSessionStatus::Active,
                'started_at' => now(),
                'distance_m' => 0,
                'points_count' => 0,
            ]);
        });
    }

    /** Stops the workout's live session(s); StopTrackingSession ends every share with it. */
    public function close(TrainingWorkout $workout): void
    {
        TrackingSession::query()
            ->where('training_workout_id', $workout->id)
            ->where('status', TrackingSessionStatus::Active)
            ->get()
            ->each(fn (TrackingSession $session) => $this->stop->handle($session));
    }
}
