<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResolveIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('incidents.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'resolution_type' => ['required', 'string', Rule::in(['fixed', 'reprinted', 'refunded', 'no_action_needed', 'escalated', 'other'])],
            'notes' => ['nullable', 'string', 'max:1000'],
            'before_data' => ['nullable', 'array'],
            'after_data' => ['nullable', 'array'],
        ];
    }
}
