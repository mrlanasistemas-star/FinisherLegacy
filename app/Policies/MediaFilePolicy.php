<?php

namespace App\Policies;

use App\Contracts\Media\OwnsMediaFiles;
use App\Contracts\Privacy\PinProtectable;
use App\Enums\MediaVisibility;
use App\Models\MediaFile;
use App\Models\User;
use App\Services\Privacy\ContentPin;

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
        // PIN-protected content: no uploader/staff shortcut. Only its owner
        // with a live unlock grant — or moderators while it is under an open
        // report (ContentPin::underModeration, documented rule).
        if (($guard = $file->pinGuard()) !== null) {
            return $this->pinAllows($user, $guard);
        }

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
        if (($guard = $file->pinGuard()) !== null && ! $this->pinAllows($user, $guard)) {
            return false;
        }

        return $user !== null && ($this->isUploader($user, $file) || $user->can('media.manage'));
    }

    private function pinAllows(?User $user, PinProtectable $guard): bool
    {
        if ($user === null) {
            return false;
        }

        $pins = app(ContentPin::class);

        if ($guard->isPinOwnedBy($user)) {
            return $pins->reveals($guard);
        }

        return $user->can('media.manage') && $pins->underModeration($guard);
    }

    private function isUploader(User $user, MediaFile $file): bool
    {
        return $file->owner_user_id !== null && $file->owner_user_id === $user->id;
    }
}
