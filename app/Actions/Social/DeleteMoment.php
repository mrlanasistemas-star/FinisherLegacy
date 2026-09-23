<?php

namespace App\Actions\Social;

use App\Models\LegacyMoment;
use Illuminate\Support\Facades\Storage;

/**
 * Soft-deletes the Moment and removes the photos uploaded WITH it. Linked
 * event media is only referenced, so it stays exactly where it was.
 */
class DeleteMoment
{
    public function handle(LegacyMoment $moment): void
    {
        foreach ($moment->media()->whereNull('athlete_event_media_id')->get() as $media) {
            if ($media->path !== null) {
                Storage::disk($media->disk ?? 'public')->delete($media->path);
            }
            $media->delete();
        }

        $moment->delete();
    }
}
