<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResolveIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // See SetOrganizerDataSourceRequest — 'sanctum' first so a stale
        // 'web' session cookie can never outrank this request's own
        // Bearer token for a permission check.
        return ($this->user('sanctum') ?? $this->user())?->can('incidents.manage') ?? false;
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
