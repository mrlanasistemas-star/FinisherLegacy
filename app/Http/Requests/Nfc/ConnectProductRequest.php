<?php

namespace App\Http\Requests\Nfc;

use App\Models\AthleteOwnedProduct;
use App\Models\NfcTag;
use App\Models\ProductVariant;
use App\Support\Nfc\NfcToken;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * "Productos conectados" → Conectar producto: a tag plus either a catalog
 * product (+ variant) for a unit without owner yet, or an existing unit
 * already in someone's Mi equipo (by its uuid).
 */
class ConnectProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('nfc.manage') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tag' => ['required', 'string', 'max:300'],
            'owned_product_uuid' => ['nullable', 'uuid'],
            'product_id' => ['nullable', 'integer', 'exists:products,id', 'required_without:owned_product_uuid'],
            'product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['product_id.required_without' => 'Elige el producto que vas a conectar.'];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->resolveTag() === null) {
                $validator->errors()->add('tag', 'No encontramos esa etiqueta NFC.');
            }

            if ($this->filled('owned_product_uuid') && $this->ownedProduct() === null) {
                $validator->errors()->add('owned_product_uuid', 'No encontramos ese producto.');
            }

            $variant = $this->variant();

            if ($this->filled('product_variant_id') && ($variant === null || $variant->product_id !== $this->integer('product_id'))) {
                $validator->errors()->add('product_variant_id', 'La variante no pertenece a ese producto.');
            }
        }];
    }

    public function resolveTag(): ?NfcTag
    {
        $token = NfcToken::extract((string) $this->input('tag'));

        return $token !== null ? NfcTag::query()->where('token', $token)->first() : null;
    }

    public function ownedProduct(): ?AthleteOwnedProduct
    {
        return $this->filled('owned_product_uuid')
            ? AthleteOwnedProduct::query()->where('uuid', $this->input('owned_product_uuid'))->first()
            : null;
    }

    public function variant(): ?ProductVariant
    {
        return $this->filled('product_variant_id') ? ProductVariant::query()->find($this->integer('product_variant_id')) : null;
    }
}
