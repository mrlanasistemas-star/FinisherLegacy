<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterManualPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // See SetOrganizerDataSourceRequest — 'sanctum' first so a stale
        // 'web' session cookie can never outrank this request's own
        // Bearer token for a permission check. Falls back to the default
        // guard because App\Http\Controllers\Admin\Store\PaymentController
        // also reuses this same Form Request from a session-based route.
        return ($this->user('sanctum') ?? $this->user())?->can('payments.record_manual') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'method' => ['required', 'string', Rule::in(['cash', 'mercado_pago_terminal'])],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
