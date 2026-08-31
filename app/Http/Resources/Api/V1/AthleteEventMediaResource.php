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
            'width' => $this->width,
            'height' => $this->height,
            'duration_seconds' => $this->duration_seconds,
            'is_public' => $this->is_public,
            'sort_order' => $this->sort_order,
        ];
    }
}
