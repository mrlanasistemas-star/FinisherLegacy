<?php

namespace App\Services\Social;

use App\Enums\MomentVisibility;
use App\Enums\ProfileVisibility;
use App\Models\AthleteFollow;
use App\Models\AthleteProfile;
use App\Models\LegacyMoment;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Database\Eloquent\Builder;

/**
 * THE privacy rules of the social layer — feed, profile, search, explore,
 * direct moment access and comments all ask this class, never re-derive
 * it (docs/SOCIAL_ARCHITECTURE.md §Privacidad):
 *
 *  - A private profile is visible only to its owner (its moments too).
 *  - A block, in either direction, hides both people from each other.
 *  - Moment visibility on top: public → anyone who can see the profile;
 *    followers → only people who follow the author; private → author only.
 *
 * Registered as a scoped singleton so the block/follow id lists are read
 * once per request.
 */
class SocialVisibility
{
    /** @var array<int, list<int>> */
    private array $blockedCache = [];

    /** @var array<int, list<int>> */
    private array $followingCache = [];

    /**
     * Everyone the viewer blocked or who blocked the viewer.
     *
     * @return list<int>
     */
    public function hiddenUserIds(?User $viewer): array
    {
        if ($viewer === null) {
            return [];
        }

        return $this->blockedCache[$viewer->id] ??= array_values(UserBlock::query()
            ->where('blocker_id', $viewer->id)
            ->pluck('blocked_id')
            ->merge(UserBlock::query()->where('blocked_id', $viewer->id)->pluck('blocker_id'))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->all());
    }

    /**
     * @return list<int>
     */
    public function followingIds(?User $viewer): array
    {
        if ($viewer === null) {
            return [];
        }

        return $this->followingCache[$viewer->id] ??= array_values(AthleteFollow::query()
            ->where('follower_id', $viewer->id)
            ->pluck('following_id')
            ->map(fn ($id) => (int) $id)
            ->all());
    }

    public function forget(User $viewer): void
    {
        unset($this->blockedCache[$viewer->id], $this->followingCache[$viewer->id]);
    }

    public function isHiddenBetween(?User $viewer, int $otherUserId): bool
    {
        return $viewer !== null && in_array($otherUserId, $this->hiddenUserIds($viewer), true);
    }

    public function canViewProfile(AthleteProfile $profile, ?User $viewer): bool
    {
        if ($viewer !== null && $viewer->id === $profile->user_id) {
            return true;
        }

        return $profile->profile_visibility === ProfileVisibility::Public
            && ! $this->isHiddenBetween($viewer, $profile->user_id);
    }

    public function canViewMoment(LegacyMoment $moment, ?User $viewer): bool
    {
        if ($viewer !== null && $viewer->id === $moment->user_id) {
            return true;
        }

        $profile = $moment->author?->athleteProfile;

        if ($profile === null || ! $this->canViewProfile($profile, $viewer)) {
            return false;
        }

        return match ($moment->visibility) {
            MomentVisibility::Public => true,
            MomentVisibility::Followers => in_array($moment->user_id, $this->followingIds($viewer), true),
            MomentVisibility::Private => false,
        };
    }

    /**
     * Same rules as canViewMoment(), as SQL — so feeds/profiles paginate
     * over exactly what the viewer is allowed to see.
     *
     * @param  Builder<LegacyMoment>  $query
     * @return Builder<LegacyMoment>
     */
    public function scopeVisibleMoments(Builder $query, ?User $viewer): Builder
    {
        $hidden = $this->hiddenUserIds($viewer);
        $following = $this->followingIds($viewer);

        return $query
            ->when($hidden !== [], fn (Builder $q) => $q->whereNotIn('legacy_moments.user_id', $hidden))
            ->where(function (Builder $q) use ($viewer, $following) {
                if ($viewer !== null) {
                    $q->where('legacy_moments.user_id', $viewer->id);
                }

                $q->orWhere(function (Builder $shared) use ($following) {
                    $shared->whereHas('author.athleteProfile', fn (Builder $p) => $p->where('profile_visibility', ProfileVisibility::Public))
                        ->where(function (Builder $v) use ($following) {
                            $v->where('legacy_moments.visibility', MomentVisibility::Public);

                            if ($following !== []) {
                                $v->orWhere(fn (Builder $f) => $f
                                    ->where('legacy_moments.visibility', MomentVisibility::Followers)
                                    ->whereIn('legacy_moments.user_id', $following));
                            }
                        });
                });
            });
    }

    /**
     * Public profiles a viewer may discover (search / explore / lists).
     *
     * @param  Builder<AthleteProfile>  $query
     * @return Builder<AthleteProfile>
     */
    public function scopeDiscoverableProfiles(Builder $query, ?User $viewer): Builder
    {
        $hidden = $this->hiddenUserIds($viewer);

        return $query
            ->where('profile_visibility', ProfileVisibility::Public)
            ->whereHas('user')
            ->when($hidden !== [], fn (Builder $q) => $q->whereNotIn('athlete_profiles.user_id', $hidden));
    }
}
