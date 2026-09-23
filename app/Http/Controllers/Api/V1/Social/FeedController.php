<?php

namespace App\Http\Controllers\Api\V1\Social;

use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Api\V1\Concerns\ResolvesAuthenticatedUser;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AthleteResource;
use App\Http\Resources\Api\V1\MomentResource;
use App\Http\Resources\EventEditionCardResource;
use App\Models\AthleteFollow;
use App\Models\AthleteProfile;
use App\Models\User;
use App\Queries\Social\MomentQuery;
use App\Services\EventCatalogService;
use App\Services\Social\SocialVisibility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class FeedController extends Controller
{
    use ApiResponses;
    use ResolvesAuthenticatedUser;

    public function __construct(
        private readonly MomentQuery $moments,
        private readonly SocialVisibility $visibility,
    ) {}

    /**
     * `GET /feed` — your own Moments plus those of athletes you follow,
     * newest first, cursor-paginated (`meta.next_cursor`). `?scope=discover`
     * returns recent public Moments from everyone instead (Explore "Para
     * ti"). Same visibility rules either way.
     */
    public function feed(Request $request): JsonResponse
    {
        $viewer = $this->sanctumUser($request);
        $scope = $request->query('scope') === 'discover' ? 'discover' : 'following';

        $query = $this->moments->visibleTo($viewer);

        if ($scope === 'following') {
            $authorIds = [...$this->visibility->followingIds($viewer), $viewer->id];
            $query->whereIn('legacy_moments.user_id', $authorIds);
        }

        $page = $this->moments->paginate($query, (int) config('finisher.social.feed_per_page'));

        return MomentResource::collection($page)->additional(['meta' => ['scope' => $scope]])->response();
    }

    /**
     * `GET /explore` — a small, fixed-size discovery payload: athletes to
     * follow, recent public Moments and upcoming events.
     */
    public function explore(Request $request, EventCatalogService $events): JsonResponse
    {
        $viewer = $this->sanctumUser($request);

        $recentMoments = $this->moments->visibleTo($viewer)
            ->where('legacy_moments.user_id', '!=', $viewer->id)
            ->orderByDesc('legacy_moments.id')
            ->limit(10)
            ->get();

        $upcoming = $events->publishedEditions(['status' => 'upcoming'], perPage: 6);

        return $this->respond([
            'athletes' => AthleteResource::collection($this->recommendedAthletes($viewer)),
            'moments' => MomentResource::collection($recentMoments),
            'events' => EventEditionCardResource::collection($upcoming->getCollection()),
        ]);
    }

    /**
     * Public athletes you don't follow yet, most-followed first — a plain,
     * explainable heuristic, no opaque ranking.
     *
     * @return Collection<int, User>
     */
    private function recommendedAthletes(User $viewer): Collection
    {
        $exclude = [...$this->visibility->followingIds($viewer), $viewer->id];

        return $this->visibility->scopeDiscoverableProfiles(AthleteProfile::query(), $viewer)
            ->whereNotIn('athlete_profiles.user_id', $exclude)
            ->with('user.athleteProfile')
            ->select('athlete_profiles.*')
            ->addSelect(['followers_count' => AthleteFollow::query()
                ->selectRaw('count(*)')
                ->whereColumn('athlete_follows.following_id', 'athlete_profiles.user_id')])
            ->orderByDesc('followers_count')
            ->orderByDesc('athlete_profiles.updated_at')
            ->limit(12)
            ->get()
            ->map(function (AthleteProfile $profile) {
                $profile->user->setAttribute('is_following', false);

                return $profile->user;
            })
            ->values();
    }
}
