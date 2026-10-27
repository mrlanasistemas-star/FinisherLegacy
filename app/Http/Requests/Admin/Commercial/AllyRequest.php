<?php

namespace App\Http\Requests\Admin\Commercial;

use App\Enums\AllyStatus;
use App\Enums\AllyType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AllyRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:160'],
            'type' => ['required', Rule::enum(AllyType::class)],
            'status' => ['required', Rule::enum(AllyStatus::class)],
            'contact_name' => ['nullable', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'commission_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'commercial_terms' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'commission_percent' => 'comisión',
            'commercial_terms' => 'condiciones comerciales',
        ];
    }
}
