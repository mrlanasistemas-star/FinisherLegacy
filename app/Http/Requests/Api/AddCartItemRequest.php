<?php

namespace App\Http\Requests\Api;

use App\Enums\ProductType;
use App\Models\LegacyPlateModel;
use App\Models\ProductVariant;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Public clients (mobile) identify a variant by its `uuid` — the only
 * identifier ProductVariantResource exposes. `product_variant_id` (the
 * integer PK) is still accepted for backwards compatibility, but is never
 * required. Same for the Legacy Plate model.
 */
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
        $variant = $this->resolveVariant();
        $isLegacyPlate = $variant !== null && $variant->product()->where('type', ProductType::LegacyPlate)->exists();

        return [
            'product_variant_uuid' => ['required_without:product_variant_id', 'nullable', 'uuid', 'exists:product_variants,uuid'],
            'product_variant_id' => ['required_without:product_variant_uuid', 'nullable', 'integer', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:20'],
            'event_edition_id' => [$isLegacyPlate ? 'required' : 'nullable', 'integer', 'exists:event_editions,id'],
            'legacy_plate_model_uuid' => [$isLegacyPlate ? 'required_without:legacy_plate_model_id' : 'nullable', 'nullable', 'uuid', 'exists:legacy_plate_models,uuid'],
            'legacy_plate_model_id' => [$isLegacyPlate ? 'required_without:legacy_plate_model_uuid' : 'nullable', 'nullable', 'integer', 'exists:legacy_plate_models,id'],
        ];
    }

    /**
     * Resolves the variant by uuid first, falling back to the legacy integer
     * id. The integer PK stays internal — it is never echoed back.
     */
    public function resolveVariant(): ?ProductVariant
    {
        $uuid = $this->input('product_variant_uuid');

        if (is_string($uuid) && $uuid !== '') {
            return ProductVariant::query()->where('uuid', $uuid)->first();
        }

        if ($this->filled('product_variant_id')) {
            return ProductVariant::query()->find($this->integer('product_variant_id'));
        }

        return null;
    }

    public function resolveLegacyPlateModelId(): ?int
    {
        $uuid = $this->input('legacy_plate_model_uuid');

        if (is_string($uuid) && $uuid !== '') {
            $id = LegacyPlateModel::query()->where('uuid', $uuid)->value('id');

            return $id === null ? null : (int) $id;
        }

        return $this->filled('legacy_plate_model_id') ? $this->integer('legacy_plate_model_id') : null;
    }
}
