<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class AddCartItemRequest extends FormRequest
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
            'product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:20'],
            'event_edition_id' => ['nullable', 'integer', 'exists:event_editions,id'],
            'legacy_plate_model_id' => ['nullable', 'integer', 'exists:legacy_plate_models,id'],
        ];
    }
}
