<?php

namespace App\Actions\Media;

use App\Models\AthleteEventMedia;

class UpdateAthleteEventMediaVisibility
{
    public function handle(AthleteEventMedia $media, bool $isPublic): AthleteEventMedia
    {
        $media->update(['is_public' => $isPublic]);

        return $media->fresh();
    }
}
