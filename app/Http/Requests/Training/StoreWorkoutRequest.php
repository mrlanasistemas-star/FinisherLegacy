<?php

namespace App\Http\Requests\Training;

use App\Enums\WorkoutSource;
use App\Enums\WorkoutVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `POST /api/v1/training/workouts` — start a live workout (`source=live`)
 * or register a past one by hand (`source=manual`).
 *
 * Live: `sport_id`, `uses_gps`, optional `client_uuid` (idempotency) and
 * `started_at` (a start queued offline, at most 24 h old).
 * Manual: `sport_id`, `started_at` (in the past), `duration_s`, optional
 * `distance_m` / `elevation_gain_m` / `notes`.
 */
class StoreWorkoutRequest extends FormRequest
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
        $manual = $this->input('source') === WorkoutSource::Manual->value;
        $maxDuration = (int) config('training.workouts.max_duration_seconds');

        return [
            'source' => ['required', Rule::enum(WorkoutSource::class)],
            'sport_id' => ['required', 'integer', Rule::exists('sports', 'id')->where('active', true)],
            'client_uuid' => ['nullable', 'uuid'],
            'uses_gps' => ['nullable', 'boolean'],
            'started_at' => $manual
                ? ['required', 'date', 'before_or_equal:now', 'after:'.now()->subYears(5)->toDateString()]
                : ['nullable', 'date', 'before_or_equal:'.now()->addMinutes(5)->toIso8601String(), 'after_or_equal:'.now()->subDay()->toIso8601String()],
            'duration_s' => $manual ? ['required', 'integer', 'min:60', 'max:'.$maxDuration] : ['prohibited'],
            'distance_m' => $manual ? ['nullable', 'integer', 'min:0', 'max:'.config('training.workouts.max_distance_m')] : ['prohibited'],
            'elevation_gain_m' => $manual ? ['nullable', 'integer', 'min:0', 'max:20000'] : ['prohibited'],
            'notes' => ['nullable', 'string', 'max:'.config('training.workouts.max_notes')],
            'visibility' => ['nullable', Rule::enum(WorkoutVisibility::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sport_id.required' => __('Elige una actividad.'),
            'sport_id.exists' => __('Elige una actividad.'),
            'started_at.required' => __('Indica cuándo fue tu actividad.'),
            'started_at.before_or_equal' => __('La fecha no puede estar en el futuro.'),
            'duration_s.required' => __('Indica cuánto duró tu actividad.'),
            'duration_s.min' => __('La duración mínima es de 1 minuto.'),
            'duration_s.max' => __('La duración es demasiado larga.'),
            'distance_m.max' => __('La distancia es demasiado larga.'),
            'notes.max' => __('Tus notas son demasiado largas (máximo :max caracteres).'),
        ];
    }
}
