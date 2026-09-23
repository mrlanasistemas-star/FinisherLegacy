<?php

namespace App\Actions\Social;

use App\Exceptions\SocialActionNotAllowedException;
use App\Models\AthleteFollow;
use App\Models\User;
use App\Models\UserBlock;
use App\Services\Social\SocialVisibility;
use Illuminate\Support\Facades\DB;

/**
 * Blocking removes any follow in both directions — after a block neither
 * person sees the other's profile, moments or comments
 * (SocialVisibility::hiddenUserIds()).
 */
class BlockUser
{
    public function __construct(private readonly SocialVisibility $visibility) {}

    public function block(User $blocker, User $blocked): void
    {
        if ($blocker->id === $blocked->id) {
            throw new SocialActionNotAllowedException('No puedes bloquearte a ti mismo.');
        }

        DB::transaction(function () use ($blocker, $blocked) {
            UserBlock::query()->firstOrCreate(['blocker_id' => $blocker->id, 'blocked_id' => $blocked->id]);

            AthleteFollow::query()
                ->where(fn ($q) => $q->where('follower_id', $blocker->id)->where('following_id', $blocked->id))
                ->orWhere(fn ($q) => $q->where('follower_id', $blocked->id)->where('following_id', $blocker->id))
                ->delete();
        });

        $this->visibility->forget($blocker);
    }

    public function unblock(User $blocker, User $blocked): void
    {
        UserBlock::query()->where('blocker_id', $blocker->id)->where('blocked_id', $blocked->id)->delete();

        $this->visibility->forget($blocker);
    }
}
