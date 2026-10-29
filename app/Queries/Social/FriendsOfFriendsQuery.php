<?php

namespace App\Queries\Social;

use App\Enums\ProfileVisibility;
use App\Models\AthleteFollow;
use App\Models\User;
use App\Services\Social\SocialVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * "Amigos de amigos" — people followed by the people the viewer follows,
 * ranked by how many of them do (the mutual connections).
 *
 * One grouped derived table over `athlete_follows` (both legs hit the
 * unique (follower_id, following_id) index), joined to `users`:
 *
 *   fof = SELECT following_id, COUNT(*) mutual_count
 *         FROM athlete_follows
 *         WHERE follower_id IN (people I follow, non-private profiles)
 *         GROUP BY following_id
 *
 * Excludes the viewer, people already followed, blocks in either
 * direction, and anyone whose identity the viewer may not see in a list
 * (SocialVisibility::scopeListableUsers). Follows made by private
 * profiles never feed the suggestion — that would leak who they follow.
 */
class FriendsOfFriendsQuery
{
    public function __construct(private readonly SocialVisibility $visibility) {}

    /**
     * @return Builder<User>
     */
    public function builder(User $viewer): Builder
    {
        $exclude = [...$this->visibility->followingIds($viewer), $viewer->id];

        return $this->visibility->scopeListableUsers(User::query(), $viewer)
            ->joinSub($this->mutualCounts($viewer), 'fof', 'fof.following_id', '=', 'users.id')
            ->whereNotIn('users.id', $exclude)
            ->select('users.*', 'fof.mutual_count')
            ->orderByDesc('fof.mutual_count')
            ->orderByDesc('users.id');
    }

    /**
     * following_id → how many of the viewer's (visible) followings follow
     * them. Reused by the suggestions ranking.
     */
    public function mutualCounts(User $viewer): QueryBuilder
    {
        return AthleteFollow::query()
            ->toBase()
            ->select('following_id')
            ->selectRaw('COUNT(*) as mutual_count')
            ->whereIn('follower_id', $this->visibleFollowings($viewer))
            ->groupBy('following_id');
    }

    /**
     * The viewer's followings whose own follows may be revealed (not
     * private profiles), as a subquery.
     */
    public function visibleFollowings(User $viewer): QueryBuilder
    {
        return AthleteFollow::query()
            ->toBase()
            ->select('athlete_follows.following_id')
            ->join('athlete_profiles', 'athlete_profiles.user_id', '=', 'athlete_follows.following_id')
            ->where('athlete_follows.follower_id', $viewer->id)
            ->where('athlete_profiles.profile_visibility', '!=', ProfileVisibility::Private->value);
    }
}
