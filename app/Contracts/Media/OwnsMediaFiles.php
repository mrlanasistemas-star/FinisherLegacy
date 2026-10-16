<?php

namespace App\Contracts\Media;

use App\Models\MediaFile;
use App\Models\User;

/**
 * Implemented by a domain row that owns a MediaFile (its `mediable`):
 * AthleteEventMedia, LegacyMomentMedia, and later ProductMedia / posts.
 * The domain — not the media layer — decides who may see its files
 * (owner, visibility, purchase entitlement…), and receives the processed
 * metadata once the pipeline finishes.
 */
interface OwnsMediaFiles
{
    /** May this viewer (null = guest) see the file? Checked by MediaFilePolicy. */
    public function canViewMedia(?User $viewer, MediaFile $file): bool;

    /** Public files get long-lived, cache-friendly signed URLs. */
    public function mediaIsPublic(MediaFile $file): bool;

    /** Called once the pipeline finished (ready / pending_transcode). */
    public function syncFromMediaFile(MediaFile $file): void;
}
