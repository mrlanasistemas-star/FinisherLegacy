<?php

namespace App\Policies;

use App\Models\TrainingWorkout;
use App\Models\User;

/**
 * Training workouts are the athlete's alone: only their owner may read,
 * update, finish, discard, delete or share one. Controllers answer 404 (not
 * 403) to anyone else so a workout's existence never leaks.
 */
class TrainingWorkoutPolicy
{
    public function view(User $user, TrainingWorkout $workout): bool
    {
        return $workout->user_id === $user->id;
    }

    public function update(User $user, TrainingWorkout $workout): bool
    {
        return $workout->user_id === $user->id;
    }

    public function delete(User $user, TrainingWorkout $workout): bool
    {
        return $workout->user_id === $user->id;
    }

    public function share(User $user, TrainingWorkout $workout): bool
    {
        return $workout->user_id === $user->id;
    }
}
