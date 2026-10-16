<?php

namespace App\Http\Requests\Nfc;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class GenerateNfcTagsRequest extends FormRequest
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
            'count' => ['required', 'integer', 'min:1', 'max:'.(int) config('nfc.max_batch', 500)],
            'batch_code' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9 _.\-]+$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['count' => 'cantidad', 'batch_code' => 'lote'];
    }
}
