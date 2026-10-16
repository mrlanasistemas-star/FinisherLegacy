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
 * it (docs/architecture/social.md §Privacidad):
 *
 *  - A block, in either direction, hides both people from each other.
 *  - Profile: public → anyone; followers → only people who follow the
 *    athlete (others signed in see a "shell" to follow); private → owner.
 *  - Moment visibility on top: public → anyone who can see the profile;
 *    followers → only people who follow the author; private → author only.
 *  - Content hidden by moderation is visible to its author only.
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

    public function follows(?User $viewer, int $otherUserId): bool
    {
        return $viewer !== null && in_array($otherUserId, $this->followingIds($viewer), true);
    }

    /**
     * Full access to the profile's content (posts, medals, races, photos,
     * follower lists).
     */
    public function canViewProfile(AthleteProfile $profile, ?User $viewer): bool
    {
        if ($viewer !== null && $viewer->id === $profile->user_id) {
            return true;
        }

        if ($this->isHiddenBetween($viewer, $profile->user_id)) {
            return false;
        }

        return match ($profile->profile_visibility) {
            ProfileVisibility::Public => true,
            ProfileVisibility::Followers => $this->follows($viewer, $profile->user_id),
            ProfileVisibility::Private => false,
        };
    }

    /**
     * The profile "shell" (name, avatar, counts, Seguir) without content:
     * a followers-only profile shows it to signed-in, non-blocked people so
     * they can follow. Anonymous visitors only get public profiles.
     */
    public function canSeeProfileShell(AthleteProfile $profile, ?User $viewer): bool
    {
        if ($this->canViewProfile($profile, $viewer)) {
            return true;
        }

        return $viewer !== null
            && $profile->profile_visibility === ProfileVisibility::Followers
            && ! $this->isHiddenBetween($viewer, $profile->user_id);
    }

    public function canViewMoment(LegacyMoment $moment, ?User $viewer): bool
    {
        if ($viewer !== null && $viewer->id === $moment->user_id) {
            return true;
        }

        // Hidden by moderation: only its author still sees it.
        if ($moment->hidden_at !== null) {
            return false;
        }

        $profile = $moment->author?->athleteProfile;

        if ($profile === null || ! $this->canViewProfile($profile, $viewer)) {
            return false;
        }

        return match ($moment->visibility) {
            MomentVisibility::Public => true,
            MomentVisibility::Followers => $this->follows($viewer, $moment->user_id),
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
                    $shared->whereNull('legacy_moments.hidden_at')
                        ->whereHas('author.athleteProfile', function (Builder $p) use ($following) {
                            $p->where(function (Builder $pv) use ($following) {
                                $pv->where('profile_visibility', ProfileVisibility::Public);

                                if ($following !== []) {
                                    $pv->orWhere(fn (Builder $f) => $f
                                        ->where('profile_visibility', ProfileVisibility::Followers)
                                        ->whereIn('athlete_profiles.user_id', $following));
                                }
                            });
                        })
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
     * Followers-only and private profiles are never listed.
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

    /**
     * Restricts a users query to people whose identity the viewer may see
     * in a list (followers/following): themselves, public profiles, and
     * followers-only profiles they follow. Blocks excluded.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeListableUsers(Builder $query, ?User $viewer): Builder
    {
        $hidden = $this->hiddenUserIds($viewer);
        $following = $this->followingIds($viewer);

        return $query
            ->whereHas('athleteProfile')
            ->where(function (Builder $q) use ($viewer, $following) {
                if ($viewer !== null) {
                    $q->where('users.id', $viewer->id);
                }

                $q->orWhereHas('athleteProfile', function (Builder $p) use ($following) {
                    $p->where('profile_visibility', ProfileVisibility::Public);

                    if ($following !== []) {
                        $p->orWhere(fn (Builder $f) => $f
                            ->where('profile_visibility', ProfileVisibility::Followers)
                            ->whereIn('athlete_profiles.user_id', $following));
                    }
                });
            })
            ->when($hidden !== [], fn (Builder $q) => $q->whereNotIn('users.id', $hidden));
    }
}
