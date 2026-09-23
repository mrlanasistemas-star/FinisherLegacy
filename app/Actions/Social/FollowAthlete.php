<?php

namespace App\Actions\Social;

use App\Exceptions\SocialActionNotAllowedException;
use App\Models\AthleteFollow;
use App\Models\AthleteProfile;
use App\Models\User;
use App\Services\Social\SocialVisibility;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Idempotent: following someone you already follow is a no-op (and never
 * a second notification).
 */
class FollowAthlete
{
    public function __construct(
        private readonly SocialVisibility $visibility,
        private readonly NotifySocialActivity $notify,
    ) {}

    public function handle(User $follower, AthleteProfile $target): void
    {
        if ($follower->id === $target->user_id) {
            throw new SocialActionNotAllowedException('No puedes seguirte a ti mismo.');
        }

        if (! $this->visibility->canViewProfile($target, $follower)) {
            throw new SocialActionNotAllowedException('No puedes seguir a este atleta.');
        }

        try {
            $follow = AthleteFollow::query()->firstOrCreate([
                'follower_id' => $follower->id,
                'following_id' => $target->user_id,
            ]);
        } catch (UniqueConstraintViolationException) {
            return; // concurrent double-tap already created it
        }

        $this->visibility->forget($follower);

        if ($follow->wasRecentlyCreated) {
            $this->notify->newFollower($target->user, $follower);
        }
    }
}
