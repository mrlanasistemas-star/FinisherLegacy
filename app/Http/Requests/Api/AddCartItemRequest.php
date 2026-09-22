<?php

namespace App\Http\Requests\Api;

use App\Enums\ProductType;
use App\Models\ProductVariant;
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
        // A Legacy Plate line always needs both — the Action re-validates
        // this authoritatively (App\Actions\Commerce\AddCartItem), this is
        // just a clearer 422 before the DB round-trip.
        $isLegacyPlate = $this->filled('product_variant_id') && ProductVariant::query()
            ->whereKey($this->integer('product_variant_id'))
            ->whereHas('product', fn ($q) => $q->where('type', ProductType::LegacyPlate))
            ->exists();

        return [
            'product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:20'],
            'event_edition_id' => [$isLegacyPlate ? 'required' : 'nullable', 'integer', 'exists:event_editions,id'],
            'legacy_plate_model_id' => [$isLegacyPlate ? 'required' : 'nullable', 'integer', 'exists:legacy_plate_models,id'],
        ];
    }
}
