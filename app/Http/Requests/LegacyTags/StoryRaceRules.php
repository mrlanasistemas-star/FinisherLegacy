<?php

namespace App\Http\Requests\LegacyTags;

use App\Support\LegacyTags\StoryRaceData;
use Illuminate\Validation\Rule;

/**
 * "¿De qué carrera es este recuerdo?" — shared by activation step 4, the
 * story editor and POST /api/v1/tags/{code}/activate. Ownership of the
 * participation is enforced in App\Actions\LegacyTags\UpdateStory.
 */
final class StoryRaceRules
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(bool $required = true): array
    {
        return [
            'race_mode' => [$required ? 'required' : 'nullable', Rule::in(['participation', 'manual', 'later', 'keep'])],
            'event_participant_id' => ['nullable', 'required_if:race_mode,participation', 'integer'],
            'event_name' => ['nullable', 'required_if:race_mode,manual', 'string', 'max:160'],
            'distance' => ['nullable', 'string', 'max:60'],
            'result_time' => ['nullable', 'string', 'max:20', 'regex:/^\d{1,3}:\d{2}(:\d{2})?(\.\d{1,3})?$/'],
            'race_date' => ['nullable', 'date', 'before_or_equal:'.now()->addYear()->toDateString()],
            'location' => ['nullable', 'string', 'max:160'],
        ];
    }

    /**
     * Race data beyond the race_mode block (bib, name, pace, category…) —
     * shared by the web editor and PATCH /api/v1/me/stories/{id}.
     *
     * @return array<string, mixed>
     */
    public static function extraRules(): array
    {
        return [
            'result_position' => ['nullable', 'string', 'max:60'],
            'bib_number' => ['nullable', 'string', 'max:12'],
            'athlete_display_name' => ['nullable', 'string', 'max:80'],
            'pace' => ['nullable', 'string', 'max:20'],
            'category' => ['nullable', 'string', 'max:40'],
            'category_position' => ['nullable', 'string', 'max:20'],
            'gender_position' => ['nullable', 'string', 'max:20'],
            'finish_status' => ['nullable', Rule::in(array_keys(StoryRaceData::FINISH_STATUSES))],
            'elevation_gain_m' => ['nullable', 'integer', 'min:0', 'max:20000'],
            'sport_id' => ['nullable', 'integer', Rule::exists('sports', 'id')->where('active', true)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'bib_number.max' => 'El número de corredor admite hasta 12 caracteres.',
            'athlete_display_name.max' => 'El nombre admite hasta 80 caracteres.',
            'pace.max' => 'Escribe el ritmo como 5:12 min/km.',
            'category.max' => 'La categoría admite hasta 40 caracteres.',
            'elevation_gain_m.integer' => 'Escribe el desnivel en metros, solo números.',
            'finish_status.in' => 'Elige un estado de la lista.',
            'sport_id.exists' => 'Elige un deporte de la lista.',
            'event_participant_id.required_if' => 'Elige una de tus carreras.',
            'event_name.required_if' => 'Escribe el nombre de la carrera.',
            'result_time.regex' => 'Escribe el tiempo como 1:45:30.',
            'race_date.date' => 'Revisa la fecha.',
        ];
    }
}
