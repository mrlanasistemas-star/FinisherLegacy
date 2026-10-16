<?php

namespace App\Http\Requests\Store;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Web checkout "datos básicos + entrega" (spec §28). Amounts are never
 * accepted here — CheckoutCart recomputes everything server-side.
 */
class StoreCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Contact fields default to the account's own data, so a one-click
     * "pickup" checkout (and older clients) keep working.
     */
    protected function prepareForValidation(): void
    {
        $user = $this->user();

        $this->merge([
            'name' => $this->filled('name') ? $this->input('name') : $user?->name,
            'email' => $this->filled('email') ? $this->input('email') : $user?->email,
            'phone' => $this->filled('phone') ? $this->input('phone') : $user?->phone,
            'delivery_method' => $this->input('delivery_method', 'pickup'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $shipping = $this->input('delivery_method') === 'shipping';

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180'],
            'phone' => [Rule::requiredIf($shipping), 'nullable', 'string', 'min:8', 'max:30', 'regex:/^[0-9 +()\-]+$/'],
            'delivery_method' => ['required', Rule::in(['pickup', 'shipping'])],
            'address_line' => [Rule::requiredIf($shipping), 'nullable', 'string', 'max:200'],
            'address_neighborhood' => ['nullable', 'string', 'max:120'],
            'address_city' => [Rule::requiredIf($shipping), 'nullable', 'string', 'max:120'],
            'address_state' => [Rule::requiredIf($shipping), 'nullable', 'string', 'max:120'],
            'address_postal_code' => [Rule::requiredIf($shipping), 'nullable', 'string', 'regex:/^[0-9]{5}$/'],
            'notes' => ['nullable', 'string', 'max:300'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'email' => 'correo',
            'phone' => 'teléfono',
            'delivery_method' => 'método de entrega',
            'address_line' => 'calle y número',
            'address_city' => 'ciudad',
            'address_state' => 'estado',
            'address_postal_code' => 'código postal',
        ];
    }

    /**
     * Frozen on the Order (`customer_snapshot`) — the order keeps what the
     * customer typed even if their profile changes later.
     *
     * @return array<string, mixed>
     */
    public function customerSnapshot(): array
    {
        $data = $this->validated();

        return [
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'delivery' => [
                'method' => $data['delivery_method'],
                'address' => $data['delivery_method'] === 'shipping' ? [
                    'line' => $data['address_line'] ?? null,
                    'neighborhood' => $data['address_neighborhood'] ?? null,
                    'city' => $data['address_city'] ?? null,
                    'state' => $data['address_state'] ?? null,
                    'postal_code' => $data['address_postal_code'] ?? null,
                ] : null,
            ],
            'notes' => $data['notes'] ?? null,
        ];
    }
}
