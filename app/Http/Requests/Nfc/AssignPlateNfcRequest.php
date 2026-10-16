<?php

namespace App\Http\Requests\Nfc;

use App\Models\NfcTag;
use App\Support\Nfc\NfcToken;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * "NFC asignado": staff scan the chip (reader in keyboard mode / phone
 * share → full URL or bare token) or pick an available tag from the list.
 */
class AssignPlateNfcRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('production.manage') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tag' => ['nullable', 'string', 'max:300', 'required_without:nfc_tag_id'],
            'nfc_tag_id' => ['nullable', 'integer', 'required_without:tag'],
            'replace' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tag.required_without' => 'Escanea la etiqueta o elige una disponible.',
            'nfc_tag_id.required_without' => 'Escanea la etiqueta o elige una disponible.',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->resolveTag() === null) {
                $validator->errors()->add('tag', 'No encontramos esa etiqueta NFC.');
            }
        }];
    }

    public function resolveTag(): ?NfcTag
    {
        if ($this->filled('nfc_tag_id')) {
            return NfcTag::query()->find($this->integer('nfc_tag_id'));
        }

        $token = NfcToken::extract((string) $this->input('tag'));

        return $token !== null ? NfcTag::query()->where('token', $token)->first() : null;
    }
}
