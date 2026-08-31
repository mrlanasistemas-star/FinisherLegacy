<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UploadAthleteEventMediaRequest extends FormRequest
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
            // Real MIME/size limits are enforced in
            // App\Actions\Media\UploadAthleteEventMedia against
            // config('finisher.event_media') — this only guards against a
            // missing file.
            'file' => ['required', 'file', 'max:'.(int) (config('finisher.event_media.max_video_bytes') / 1024)],
            'is_public' => ['nullable', 'boolean'],
        ];
    }
}
