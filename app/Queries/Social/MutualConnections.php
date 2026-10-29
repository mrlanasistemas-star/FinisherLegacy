<?php

namespace App\Queries\Social;

use App\Models\AthleteFollow;
use App\Models\User;
use App\Services\Social\SocialVisibility;
use Illuminate\Support\Collection;

/**
 * "Seguido por Ana y 3 más" — for a page of people, how many of the
 * viewer's followings follow each one, the first names to show, and
 * whether each one follows the viewer back. A fixed 4 queries per page
 * (follows-you, grouped counts, follower pairs, the named users), never per row.
 *
 * Only followings with a non-private profile count: a private profile's
 * follows are not revealed to anyone.
 */
class MutualConnections
{
    private const int NAMES = 2;

    public function __construct(
        private readonly SocialVisibility $visibility,
        private readonly FriendsOfFriendsQuery $friendsOfFriends,
    ) {}

    /**
     * Sets `is_following`, `follows_you`, `mutual_count` and
     * `mutual_names` on every user (AthleteResource renders them).
     *
     * @param  Collection<int, User>  $users
     * @return Collection<int, User>
     */
    public function annotate(?User $viewer, Collection $users): Collection
    {
        if ($users->isEmpty()) {
            return $users;
        }

        if ($viewer === null) {
            return $users->each(fn (User $user) => $user->setAttribute('is_following', false));
        }

        $ids = $users->pluck('id')->map(fn ($id) => (int) $id)->all();
        $following = $this->visibility->followingIds($viewer);
        $hidden = $this->visibility->hiddenUserIds($viewer);

        $followsYou = AthleteFollow::query()
            ->where('following_id', $viewer->id)
            ->whereIn('follower_id', $ids)
            ->pluck('follower_id')
            ->map(fn ($id) => (int) $id)
            ->flip();

        $pairs = $following === [] ? collect() : AthleteFollow::query()
            ->whereIn('following_id', $ids)
            ->whereIn('follower_id', $this->friendsOfFriends->visibleFollowings($viewer))
            ->when($hidden !== [], fn ($q) => $q->whereNotIn('follower_id', $hidden))
            ->orderByDesc('id')
            ->limit(count($ids) * 50)
            ->get(['follower_id', 'following_id'])
            ->groupBy('following_id');

        $counts = $following === [] ? collect() : AthleteFollow::query()
            ->whereIn('following_id', $ids)
            ->whereIn('follower_id', $this->friendsOfFriends->visibleFollowings($viewer))
            ->when($hidden !== [], fn ($q) => $q->whereNotIn('follower_id', $hidden))
            ->groupBy('following_id')
            ->selectRaw('following_id, COUNT(*) as total')
            ->pluck('total', 'following_id');

        $nameIds = $pairs->flatMap(fn (Collection $rows) => $rows->take(self::NAMES)->pluck('follower_id'))->unique()->values();
        $names = $nameIds->isEmpty() ? collect() : User::query()
            ->whereIn('id', $nameIds)
            ->get(['id', 'first_name', 'last_name'])
            ->mapWithKeys(fn (User $user) => [$user->id => $user->name]);

        return $users->each(function (User $user) use ($following, $followsYou, $pairs, $counts, $names) {
            $user->setAttribute('is_following', in_array((int) $user->id, $following, true));
            $user->setAttribute('follows_you', $followsYou->has((int) $user->id));
            $user->setAttribute('mutual_count', (int) ($counts[$user->id] ?? 0));
            $user->setAttribute('mutual_names', ($pairs->get($user->id) ?? collect())
                ->take(self::NAMES)
                ->map(fn ($row) => $names[$row->follower_id] ?? null)
                ->filter()
                ->values()
                ->all());
        });
    }
}
