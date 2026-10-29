<?php

namespace App\Http\Requests\Admin;

use App\Services\Commerce\Payments\PaymentSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * /admin/configuracion/pagos. The private key is optional on purpose: a
 * blank field keeps the key already saved (the browser never receives it
 * back, so it can't resend it).
 */
class UpdatePaymentSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('payments.configure') === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'openpay_merchant_id' => is_string($this->input('openpay_merchant_id')) ? trim($this->input('openpay_merchant_id')) : null,
            'openpay_public_key' => is_string($this->input('openpay_public_key')) ? trim($this->input('openpay_public_key')) : null,
            'openpay_private_key' => is_string($this->input('openpay_private_key')) ? trim($this->input('openpay_private_key')) : null,
            'openpay_production_mode' => $this->boolean('openpay_production_mode'),
            'openpay_use_3d_secure' => $this->boolean('openpay_use_3d_secure'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'default_gateway' => ['required', 'string', Rule::in(PaymentSettings::GATEWAYS)],
            'openpay_merchant_id' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9]+$/i'],
            'openpay_public_key' => ['nullable', 'string', 'max:191', 'starts_with:pk_'],
            'openpay_private_key' => ['nullable', 'string', 'max:191', 'starts_with:sk_'],
            'openpay_country' => ['required', 'string', Rule::in(PaymentSettings::OPENPAY_COUNTRIES)],
            'openpay_production_mode' => ['boolean'],
            'openpay_use_3d_secure' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'openpay_merchant_id.regex' => 'El Merchant ID solo lleva letras y números (cópialo tal cual del panel de OpenPay).',
            'openpay_public_key.starts_with' => 'La llave pública de OpenPay empieza con "pk_".',
            'openpay_private_key.starts_with' => 'La llave privada de OpenPay empieza con "sk_".',
        ];
    }

    /**
     * Choosing OpenPay as the active gateway needs a complete set of keys
     * (from this form, a key already saved, or .env).
     *
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->input('default_gateway') !== 'openpay') {
                    return;
                }

                $hasPrivate = filled($this->input('openpay_private_key'))
                    || filled(config('finisher.payments.openpay.private_key'));

                if (blank($this->input('openpay_merchant_id')) && blank(config('finisher.payments.openpay.merchant_id'))) {
                    $validator->errors()->add('openpay_merchant_id', 'Para activar OpenPay captura el Merchant ID.');
                }

                if (blank($this->input('openpay_public_key')) && blank(config('finisher.payments.openpay.public_key'))) {
                    $validator->errors()->add('openpay_public_key', 'Para activar OpenPay captura la llave pública.');
                }

                if (! $hasPrivate) {
                    $validator->errors()->add('openpay_private_key', 'Para activar OpenPay captura la llave privada.');
                }
            },
        ];
    }
}
