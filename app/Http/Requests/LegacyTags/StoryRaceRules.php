<?php

namespace App\Http\Requests\LegacyTags;

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
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'event_participant_id.required_if' => 'Elige una de tus carreras.',
            'event_name.required_if' => 'Escribe el nombre de la carrera.',
            'result_time.regex' => 'Escribe el tiempo como 1:45:30.',
            'race_date.date' => 'Revisa la fecha.',
        ];
    }
}
