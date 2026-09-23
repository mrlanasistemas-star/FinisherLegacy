<?php

namespace App\Http\Requests\Api;

use App\Enums\MomentType;
use App\Enums\MomentVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMomentRequest extends FormRequest
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
        $mimes = implode(',', config('finisher.image.mimes'));
        $maxPhotos = (int) config('finisher.social.moment_max_photos');

        return [
            'type' => ['required', Rule::enum(MomentType::class)],
            'caption' => ['nullable', 'string', 'max:'.config('finisher.social.moment_caption_max')],
            'visibility' => ['required', Rule::enum(MomentVisibility::class)],
            'event_participant_id' => ['nullable', 'integer'],
            'medal_uuid' => ['nullable', 'uuid'],
            'gear_uuid' => ['nullable', 'uuid'],
            'event_media_uuids' => ['nullable', 'array', 'max:'.$maxPhotos],
            'event_media_uuids.*' => ['uuid'],
            'photos' => ['nullable', 'array', 'max:'.$maxPhotos],
            'photos.*' => ['image', "mimes:{$mimes}", 'max:'.config('finisher.social.moment_photo_max_kb')],
            'metrics' => ['nullable', 'array'],
            'metrics.title' => ['nullable', 'string', 'max:80'],
            'metrics.distance_km' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'metrics.duration_seconds' => ['nullable', 'integer', 'min:0', 'max:604800'],
            'metrics.is_personal_record' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'photos.max' => 'Puedes agregar hasta :max fotos.',
            'photos.*.image' => 'Ese archivo no es una imagen válida.',
            'photos.*.max' => 'Una de las fotos es demasiado grande.',
            'caption.max' => 'Tu texto es demasiado largo (máximo :max caracteres).',
        ];
    }
}
