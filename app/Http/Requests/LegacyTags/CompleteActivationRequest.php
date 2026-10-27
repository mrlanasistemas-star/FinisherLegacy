<?php

namespace App\Http\Requests\LegacyTags;

use Illuminate\Foundation\Http\FormRequest;

/** Activation step 4: which race this memory is about (or "later"). */
class CompleteActivationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return StoryRaceRules::rules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return StoryRaceRules::messages();
    }
}
