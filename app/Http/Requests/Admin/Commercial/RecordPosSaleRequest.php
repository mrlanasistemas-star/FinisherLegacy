<?php

namespace App\Http\Requests\Admin\Commercial;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Venta rápida payload. Location scoping (which locations this user may
 * sell from) is enforced in PosController::allowedLocation — not here —
 * so an unauthorized location answers 403, not a validation error.
 */
class RecordPosSaleRequest extends FormRequest
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
            'location_id' => ['required', 'integer'],
            'codes' => ['nullable', 'array', 'max:100'],
            'codes.*' => ['nullable', 'string', 'max:300'],
            'quantities' => ['nullable', 'array', 'max:50'],
            'quantities.*' => ['integer', 'min:0', 'max:100'],
            'payment_method' => ['required', Rule::in([PaymentMethod::Cash->value, PaymentMethod::MercadoPagoTerminal->value])],
            'payment_reference' => ['nullable', 'string', 'max:120'],
            'customer' => ['nullable', 'array'],
            'customer.name' => ['nullable', 'string', 'max:120'],
            'customer.email' => ['nullable', 'email', 'max:190'],
            'customer.phone' => ['nullable', 'string', 'max:40'],
            'started_at' => ['nullable', 'string', 'max:40'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'payment_method.required' => 'Elige cómo te pagaron: efectivo o terminal.',
            'customer.email.email' => 'El correo del comprador no es válido.',
        ];
    }
}
