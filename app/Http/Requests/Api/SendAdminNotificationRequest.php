<?php

namespace App\Http\Requests\Api;

use App\Enums\NotificationType;
use App\Rules\RelativeInternalUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendAdminNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        // See SetOrganizerDataSourceRequest — 'sanctum' first so a stale
        // 'web' session cookie can never outrank this request's own
        // Bearer token for a permission check.
        return ($this->user('sanctum') ?? $this->user())?->can('notifications.send') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['action_url' => $this->filled('action_url') ? $this->string('action_url')->toString() : null]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::enum(NotificationType::class)->only(NotificationType::adminSendable())],
            'title' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:1000'],
            'action_url' => ['nullable', 'string', 'max:255', new RelativeInternalUrl],
            'push' => ['boolean'],
        ];
    }
}
