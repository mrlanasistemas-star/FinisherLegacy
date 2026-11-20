<?php

namespace App\Actions\Training\Workouts;

use App\Actions\Social\CreateMoment;
use App\Enums\MomentType;
use App\Enums\MomentVisibility;
use App\Enums\WorkoutStatus;
use App\Exceptions\WorkoutStateException;
use App\Models\LegacyMoment;
use App\Models\TrainingWorkout;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Turns a completed workout into a Legacy Moment — only when the athlete
 * taps "Compartir" (never automatic) and with the visibility they chose
 * (private unless they pick otherwise). Goes through the same CreateMoment
 * action as every other Moment, so moderation, privacy and the community
 * feed rules apply unchanged. One Moment per workout: a second share answers
 * 409 with the existing Moment's uuid.
 */
class ShareWorkoutAsMoment
{
    public function __construct(private readonly CreateMoment $create) {}

    public function handle(User $user, TrainingWorkout $workout, MomentVisibility $visibility, ?string $caption): LegacyMoment
    {
        if ($workout->status !== WorkoutStatus::Completed) {
            throw new WorkoutStateException(__('Termina el entrenamiento antes de compartirlo.'), 'workout_not_completed');
        }

        return DB::transaction(function () use ($user, $workout, $visibility, $caption) {
            $locked = TrainingWorkout::query()->whereKey($workout->id)->lockForUpdate()->firstOrFail();

            $existing = $locked->legacy_moment_id !== null ? LegacyMoment::query()->find($locked->legacy_moment_id) : null;
            if ($existing !== null) {
                throw new WorkoutStateException(__('Ya compartiste este entrenamiento.'), 'workout_already_shared', ['moment_uuid' => $existing->uuid]);
            }

            $sportName = $locked->sport()->value('name');

            $moment = $this->create->handle($user, [
                'type' => MomentType::Training->value,
                'visibility' => $visibility->value,
                'caption' => $caption,
                'metrics' => array_filter([
                    'title' => $sportName !== null ? __($sportName) : __('Entrenamiento'),
                    'distance_km' => $locked->distance_m !== null && $locked->distance_m > 0 ? round($locked->distance_m / 1000, 2) : null,
                    'duration_seconds' => $locked->duration_s > 0 ? $locked->duration_s : null,
                ], fn ($value) => $value !== null),
            ]);

            $locked->forceFill(['legacy_moment_id' => $moment->id])->save();
            $workout->setRawAttributes($locked->getAttributes(), true);

            return $moment;
        });
    }
}
