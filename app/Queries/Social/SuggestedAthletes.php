<?php

namespace App\Queries\Social;

use App\Models\AthleteFollow;
use App\Models\AthleteProfile;
use App\Models\User;
use App\Services\Social\SocialVisibility;
use Illuminate\Support\Collection;

/**
 * "Atletas sugeridos" — public athletes the viewer doesn't follow yet,
 * most-followed first. A plain, explainable heuristic (no opaque
 * ranking), shared by the API's /explore and the web Comunidad sidebar.
 * Guests get the same list minus the follow exclusions.
 */
class SuggestedAthletes
{
    public function __construct(private readonly SocialVisibility $visibility) {}

    /**
     * @return Collection<int, User>
     */
    public function handle(?User $viewer, int $limit = 12): Collection
    {
        $exclude = $viewer === null ? [] : [...$this->visibility->followingIds($viewer), $viewer->id];

        return $this->visibility->scopeDiscoverableProfiles(AthleteProfile::query(), $viewer)
            ->when($exclude !== [], fn ($q) => $q->whereNotIn('athlete_profiles.user_id', $exclude))
            ->with(['user.athleteProfile.mainSport'])
            ->select('athlete_profiles.*')
            ->addSelect(['followers_count' => AthleteFollow::query()
                ->selectRaw('count(*)')
                ->whereColumn('athlete_follows.following_id', 'athlete_profiles.user_id')])
            ->orderByDesc('followers_count')
            ->orderByDesc('athlete_profiles.updated_at')
            ->limit($limit)
            ->get()
            ->map(function (AthleteProfile $profile) {
                $profile->user->setAttribute('is_following', false);

                return $profile->user;
            })
            ->values();
    }
}
