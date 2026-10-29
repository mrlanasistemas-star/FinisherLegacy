<?php

namespace App\Queries\Social;

use App\Enums\ProfileVisibility;
use App\Models\AthleteFollow;
use App\Models\User;
use App\Models\UserBlock;
use App\Services\Social\SocialVisibility;
use Illuminate\Database\Eloquent\Builder;

/**
 * The lists of the "Personas" hub (/comunidad/personas). Every tab is a
 * plain users Builder — paginated by the controller, privacy-scoped here
 * through SocialVisibility (never re-derived):
 *
 *  - sugeridos:        public profiles you don't follow, ranked by mutual
 *                      connections, then by followers.
 *  - amigos-de-amigos: FriendsOfFriendsQuery.
 *  - amigos:           mutual follows.
 *  - seguidores / siguiendo: your followers / who you follow.
 *  - bloqueados:       people you blocked (identity only, to unblock).
 */
class PeopleQuery
{
    public const string SUGGESTED = 'sugeridos';

    public const string FRIENDS_OF_FRIENDS = 'amigos-de-amigos';

    public const string FRIENDS = 'amigos';

    public const string FOLLOWERS = 'seguidores';

    public const string FOLLOWING = 'siguiendo';

    public const string BLOCKED = 'bloqueados';

    public const array TABS = [
        self::SUGGESTED,
        self::FRIENDS_OF_FRIENDS,
        self::FRIENDS,
        self::FOLLOWERS,
        self::FOLLOWING,
        self::BLOCKED,
    ];

    public function __construct(
        private readonly SocialVisibility $visibility,
        private readonly FriendsOfFriendsQuery $friendsOfFriends,
    ) {}

    /**
     * @return Builder<User>
     */
    public function builder(User $viewer, string $tab, ?string $search = null): Builder
    {
        $query = match ($tab) {
            self::FRIENDS_OF_FRIENDS => $this->friendsOfFriends->builder($viewer),
            self::FRIENDS => $this->ordered($this->visibility->scopeListableUsers(User::query(), $viewer)
                ->whereIn('users.id', $this->followingIds($viewer))
                ->whereIn('users.id', $this->followerIds($viewer))),
            self::FOLLOWERS => $this->ordered($this->visibility->scopeListableUsers(User::query(), $viewer)
                ->whereIn('users.id', $this->followerIds($viewer))),
            self::FOLLOWING => $this->ordered($this->visibility->scopeListableUsers(User::query(), $viewer)
                ->whereIn('users.id', $this->followingIds($viewer))),
            self::BLOCKED => $this->ordered(User::query()
                ->whereHas('athleteProfile')
                ->whereIn('users.id', UserBlock::query()->select('blocked_id')->where('blocker_id', $viewer->id))),
            default => $this->suggested($viewer),
        };

        return $this->search($query, $search)->with('athleteProfile.mainSport');
    }

    /**
     * Public profiles the viewer doesn't follow yet: people followed by
     * your followings first, then the most-followed.
     *
     * @return Builder<User>
     */
    public function suggested(User $viewer): Builder
    {
        $hidden = $this->visibility->hiddenUserIds($viewer);
        $exclude = [...$this->visibility->followingIds($viewer), $viewer->id, ...$hidden];

        return User::query()
            ->join('athlete_profiles', 'athlete_profiles.user_id', '=', 'users.id')
            ->leftJoinSub($this->friendsOfFriends->mutualCounts($viewer), 'fof', 'fof.following_id', '=', 'users.id')
            ->where('athlete_profiles.profile_visibility', ProfileVisibility::Public->value)
            ->whereNotIn('users.id', $exclude)
            ->select('users.*')
            ->selectRaw('COALESCE(fof.mutual_count, 0) as mutual_count')
            ->addSelect(['followers_count' => AthleteFollow::query()
                ->selectRaw('COUNT(*)')
                ->whereColumn('athlete_follows.following_id', 'users.id')])
            ->orderByDesc('mutual_count')
            ->orderByDesc('followers_count')
            ->orderByDesc('users.id');
    }

    /**
     * @return array<string, int>
     */
    public function counts(User $viewer): array
    {
        return [
            self::FRIENDS => $this->builder($viewer, self::FRIENDS)->count(),
            self::FOLLOWERS => AthleteFollow::query()->where('following_id', $viewer->id)->count(),
            self::FOLLOWING => AthleteFollow::query()->where('follower_id', $viewer->id)->count(),
            self::BLOCKED => UserBlock::query()->where('blocker_id', $viewer->id)->count(),
        ];
    }

    /**
     * Every word must match the first name, last name or username.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function search(Builder $query, ?string $search): Builder
    {
        $words = array_filter(preg_split('/\s+/', trim((string) $search)) ?: []);

        foreach (array_slice($words, 0, 4) as $word) {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], mb_substr($word, 0, 40)).'%';

            $query->where(fn (Builder $q) => $q
                ->where('users.first_name', 'like', $like)
                ->orWhere('users.last_name', 'like', $like)
                ->orWhereHas('athleteProfile', fn (Builder $p) => $p->where('username', 'like', $like)));
        }

        return $query;
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    private function ordered(Builder $query): Builder
    {
        return $query->select('users.*')->orderBy('users.first_name')->orderBy('users.id');
    }

    /**
     * @return Builder<AthleteFollow>
     */
    private function followingIds(User $viewer): Builder
    {
        return AthleteFollow::query()->select('following_id')->where('follower_id', $viewer->id);
    }

    /**
     * @return Builder<AthleteFollow>
     */
    private function followerIds(User $viewer): Builder
    {
        return AthleteFollow::query()->select('follower_id')->where('following_id', $viewer->id);
    }
}
