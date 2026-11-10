<?php

namespace App\Http\Requests\Api\Iap;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `POST /api/v1/iap/purchases` — what the app tells us about a store
 * transaction. Only identifiers: the backend re-reads the transaction
 * from Apple / Google before granting anything. `jws` (iOS signed
 * transaction) is accepted for diagnostics but never trusted on its own.
 * `price_amount_micros` + `currency` (what the store SDK showed) are
 * recorded as client-reported only when the store itself reports no price.
 */
class StoreIapPurchaseRequest extends FormRequest
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
            'platform' => ['required', 'string', Rule::in(['ios', 'android'])],
            'product_id' => ['required', 'string', 'max:100'],
            'story_public_id' => ['required', 'string', 'regex:/^[a-z0-9]{8,16}$/'],
            'transaction_id' => ['required_if:platform,ios', 'nullable', 'string', 'regex:/^\d{1,40}$/'],
            'purchase_token' => ['required_if:platform,android', 'nullable', 'string', 'max:4096'],
            'jws' => ['nullable', 'string', 'max:20000'],
            'receipt' => ['nullable', 'string', 'max:20000'],
            'price_amount_micros' => ['nullable', 'integer', 'min:0', 'max:100000000000'],
            'currency' => ['nullable', 'string', 'size:3'],
        ];
    }
}
