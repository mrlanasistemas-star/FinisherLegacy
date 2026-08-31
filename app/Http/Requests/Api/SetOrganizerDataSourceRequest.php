<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SetOrganizerDataSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('eventdata.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(['manual', 'file', 'api'])],
            'provider_connection_id' => ['nullable', 'integer', 'exists:provider_connections,id'],
            'active' => ['nullable', 'boolean'],
        ];
    }
}
