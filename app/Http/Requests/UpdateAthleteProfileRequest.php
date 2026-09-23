<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAthleteProfileRequest extends FormRequest
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
        // 'sanctum' first — see App\Http\Controllers\Api\V1\Concerns\
        // ResolvesAuthenticatedUser. Falls back to the default guard
        // because App\Http\Controllers\AthleteProfileController (Web,
        // session-based) also reuses this same Form Request.
        $currentProfileId = ($this->user('sanctum') ?? $this->user())->athleteProfile?->id;
        $mimes = implode(',', config('finisher.image.mimes'));

        return [
            'username' => [
                'required',
                'string',
                'min:3',
                'max:30',
                'regex:/^[a-z0-9_.]+$/i',
                Rule::unique('athlete_profiles', 'username')->ignore($currentProfileId),
            ],
            'bio' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'main_sport_id' => ['nullable', 'integer', 'exists:sports,id'],
            'profile_visibility' => ['required', 'string', 'in:public,private'],
            'remove_profile_photo' => ['nullable', 'boolean'],
            'remove_cover_photo' => ['nullable', 'boolean'],
            'profile_photo' => ['nullable', 'image', "mimes:{$mimes}", 'max:'.config('finisher.profile.avatar.max_kb')],
            'cover_photo' => ['nullable', 'image', "mimes:{$mimes}", 'max:'.config('finisher.profile.cover.max_kb')],
        ];
    }
}
