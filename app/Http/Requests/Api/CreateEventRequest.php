<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('events.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:150', 'alpha_dash', Rule::unique('events', 'slug')],
            'sport_id' => ['required', 'integer', 'exists:sports,id'],
            'organizer_id' => ['nullable', 'integer', 'exists:organizers,id'],
            'edition_name' => ['required', 'string', 'max:150'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'event_date' => ['required', 'date'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'country' => ['required', 'string', 'max:100'],
            'data_source_type' => ['nullable', 'string', Rule::in(['manual', 'file', 'api'])],
            'data_source_provider_connection_id' => ['nullable', 'integer', 'exists:provider_connections,id'],
            'races' => ['required', 'array', 'min:1'],
            'races.*.name' => ['required', 'string', 'max:100'],
            'races.*.distance_value' => ['nullable', 'numeric', 'min:0'],
            'races.*.distance_unit' => ['nullable', 'string', 'max:10'],
            'races.*.race_type' => ['nullable', 'string', 'max:50'],
        ];
    }
}
