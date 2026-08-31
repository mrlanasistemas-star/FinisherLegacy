<?php

namespace App\Policies;

use App\Models\AthleteEventMedia;
use App\Models\User;

/**
 * Only the owning Athlete or an authorized admin (`media.manage`) may
 * modify private media — public Athlete profiles only ever see media
 * explicitly marked `is_public` (brief §102/§144), enforced by the
 * Resource/query layer, not this policy.
 */
class AthleteEventMediaPolicy
{
    public function update(User $user, AthleteEventMedia $media): bool
    {
        return $this->owns($user, $media) || $user->can('media.manage');
    }

    public function delete(User $user, AthleteEventMedia $media): bool
    {
        return $this->owns($user, $media) || $user->can('media.manage');
    }

    private function owns(User $user, AthleteEventMedia $media): bool
    {
        return $user->athlete?->id === $media->athlete_id;
    }
}
