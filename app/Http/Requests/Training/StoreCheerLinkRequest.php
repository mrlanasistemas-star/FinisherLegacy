<?php

namespace App\Http\Requests\Training;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * New "Envíame ánimo" link — web and API (`{title, expires_in_days?: 7|30|90|null}`).
 * The route is already auth-only; the link always belongs to the caller.
 */
class StoreCheerLinkRequest extends FormRequest
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
        return [
            'title' => ['required', 'string', 'max:80'],
            'expires_in_days' => ['nullable', 'integer', Rule::in(config('training.cheers.expiry_days', [7, 30, 90]))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => __('Ponle un nombre, por ejemplo: Mi maratón de Mérida.'),
            'title.max' => __('El nombre puede tener hasta 80 caracteres.'),
            'expires_in_days.in' => __('Elige 7, 30 o 90 días, o sin vencimiento.'),
        ];
    }

    public function expiresInDays(): ?int
    {
        $days = $this->validated('expires_in_days');

        return $days !== null ? (int) $days : null;
    }
}
