<?php

namespace App\Http\Resources\Api\V1;

use App\Models\LegacyPlateModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * App-safe view of a LegacyPlateModel — active ones only (brief §119),
 * no internal admin config (field x/y/font positions stay server-side).
 *
 * @mixin LegacyPlateModel
 */
class LegacyPlateModelResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'width_mm' => (float) $this->width_mm,
            'height_mm' => (float) $this->height_mm,
            'preview_image_url' => $this->preview_image_path ? asset('storage/'.$this->preview_image_path) : null,
        ];
    }
}
