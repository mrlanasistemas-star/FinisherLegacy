<?php

namespace App\Http\Requests\Training;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST /api/v1/training/workouts/{uuid}/finish` — the phone's final
 * numbers for a live workout. Every GPS-derived field is optional (indoor
 * workouts send only the timer); FinishWorkout drops them when the workout
 * didn't use GPS. `route_polyline` is a Google-encoded polyline (precision 5).
 */
class FinishWorkoutRequest extends FormRequest
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
            'duration_s' => ['required', 'integer', 'min:0', 'max:'.$maxDuration],
            'ended_at' => ['nullable', 'date'],
            'moving_s' => ['nullable', 'integer', 'min:0', 'max:'.$maxDuration],
            'distance_m' => ['nullable', 'integer', 'min:0', 'max:'.config('training.workouts.max_distance_m')],
            'elevation_gain_m' => ['nullable', 'integer', 'min:0', 'max:20000'],
            'best_5k_s' => ['nullable', 'integer', 'min:1', 'max:'.$maxDuration],
            'best_10k_s' => ['nullable', 'integer', 'min:1', 'max:'.$maxDuration],
            'route_polyline' => ['nullable', 'string', 'max:'.config('training.workouts.max_polyline_chars')],
            'route_points_count' => ['nullable', 'integer', 'min:0', 'max:20000'],
            'notes' => ['nullable', 'string', 'max:'.config('training.workouts.max_notes')],
        ];
    }
}
