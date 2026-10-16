<?php

namespace App\Http\Resources\Api\V1;

use App\Models\AthleteEventMedia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AthleteEventMedia
 */
class AthleteEventMediaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'type' => $this->type->value,
            'url' => $this->url(),
            // Additive (media pipeline): processing | ready | pending_transcode | failed.
            'status' => $this->processingStatus(),
            'thumb_url' => $this->mediaFile?->url($this->mediaFile->hasVariant('thumb') ? 'thumb' : 'poster_thumb'),
            'card_url' => $this->mediaFile?->url($this->mediaFile->hasVariant('card') ? 'card' : 'poster_card'),
            'poster_url' => $this->mediaFile?->url('poster'),
            'width' => $this->width,
            'height' => $this->height,
            'duration_seconds' => $this->duration_seconds,
            'is_public' => $this->is_public,
            'sort_order' => $this->sort_order,
        ];
    }
}
