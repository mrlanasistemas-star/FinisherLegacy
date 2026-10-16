<?php

namespace App\Policies;

use App\Contracts\Media\OwnsMediaFiles;
use App\Enums\MediaVisibility;
use App\Models\MediaFile;
use App\Models\User;

/**
 * Who may fetch a media file WITHOUT a valid signed URL (spec §68/§117):
 * the uploader, staff with `media.manage`, or whoever the owning domain
 * row allows (owner, visibility, purchase entitlement… — see
 * App\Contracts\Media\OwnsMediaFiles). A file with no owning row falls
 * back to its own `visibility`.
 */
class MediaFilePolicy
{
    public function view(?User $user, MediaFile $file): bool
    {
        if ($user !== null && ($this->isUploader($user, $file) || $user->can('media.manage'))) {
            return true;
        }

        $owner = $file->mediable_type !== null ? $file->mediable : null;

        if ($owner instanceof OwnsMediaFiles) {
            return $owner->canViewMedia($user, $file);
        }

        return $file->visibility === MediaVisibility::Public;
    }

    /**
     * Originals keep camera EXIF (GPS) — uploader and staff only, and
     * never through a signature alone.
     */
    public function viewOriginal(?User $user, MediaFile $file): bool
    {
        return $user !== null && ($this->isUploader($user, $file) || $user->can('media.manage'));
    }

    private function isUploader(User $user, MediaFile $file): bool
    {
        return $file->owner_user_id !== null && $file->owner_user_id === $user->id;
    }
}
