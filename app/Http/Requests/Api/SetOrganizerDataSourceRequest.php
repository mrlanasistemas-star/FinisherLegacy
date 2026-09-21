<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SetOrganizerDataSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        // `user()` with no guard falls through to the app's default 'web'
        // guard, which on a Sanctum stateful domain can resolve a stale
        // session cookie instead of this request's own Bearer token —
        // 'sanctum' is checked first so a permission check never runs
        // against the wrong identity (see ResolvesAuthenticatedUser).
        return ($this->user('sanctum') ?? $this->user())?->can('eventdata.manage') ?? false;
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
