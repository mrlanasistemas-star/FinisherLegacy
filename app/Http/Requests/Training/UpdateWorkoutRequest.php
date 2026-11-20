<?php

namespace App\Http\Requests\Training;

use App\Enums\WorkoutVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `PATCH /api/v1/training/workouts/{uuid}` — two uses:
 *
 * - Heartbeat of a running live workout: `duration_s`, `distance_m`,
 *   `moving_s` (keeps it from being auto-closed and lets another device or
 *   the web see the progress).
 * - Edit of a finished workout: `notes`, `visibility`, `sport_id`; a
 *   manual one may also correct `duration_s`, `distance_m` and `started_at`.
 *
 * The controller decides which fields apply to the workout's state.
 */
class UpdateWorkoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxDuration = (int) config('training.workouts.max_duration_seconds');

        return [
            'duration_s' => ['sometimes', 'integer', 'min:0', 'max:'.$maxDuration],
            'moving_s' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:'.$maxDuration],
            'distance_m' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:'.config('training.workouts.max_distance_m')],
            'started_at' => ['sometimes', 'date', 'before_or_equal:now', 'after:'.now()->subYears(5)->toDateString()],
            'sport_id' => ['sometimes', 'integer', Rule::exists('sports', 'id')->where('active', true)],
            'notes' => ['sometimes', 'nullable', 'string', 'max:'.config('training.workouts.max_notes')],
            'visibility' => ['sometimes', Rule::enum(WorkoutVisibility::class)],
        ];
    }
}
