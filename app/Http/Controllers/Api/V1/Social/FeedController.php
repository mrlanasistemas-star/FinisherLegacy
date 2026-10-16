<?php

namespace App\Http\Controllers\Api\V1\Social;

use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Api\V1\Concerns\ResolvesAuthenticatedUser;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AthleteResource;
use App\Http\Resources\Api\V1\MomentResource;
use App\Http\Resources\EventEditionCardResource;
use App\Queries\Social\FeedQuery;
use App\Queries\Social\MomentQuery;
use App\Queries\Social\SuggestedAthletes;
use App\Services\EventCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeedController extends Controller
{
    use ApiResponses;
    use ResolvesAuthenticatedUser;

    public function __construct(private readonly MomentQuery $moments) {}

    /**
     * `GET /feed` — cursor-paginated (`meta.next_cursor`), same visibility
     * rules for every scope (App\Queries\Social\FeedQuery):
     *  - `following` (default): you + athletes you follow, newest first.
     *  - `discover`: every visible Moment, newest first.
     *  - `for_you`: newest day first; inside a day people you follow, then
     *    posts about events you ran, then the rest (web "Para ti").
     *  - `events`: like `for_you`, only posts tied to an event.
     */
    public function feed(Request $request, FeedQuery $feed): JsonResponse
    {
        $viewer = $this->sanctumUser($request);
        $scope = in_array($request->query('scope'), FeedQuery::SCOPES, true) ? (string) $request->query('scope') : FeedQuery::FOLLOWING;

        return MomentResource::collection($feed->paginate($viewer, $scope))
            ->additional(['meta' => ['scope' => $scope]])
            ->response();
    }

    /**
     * `GET /explore` — a small, fixed-size discovery payload: athletes to
     * follow, recent public Moments and upcoming events.
     */
    public function explore(Request $request, EventCatalogService $events, SuggestedAthletes $suggested): JsonResponse
    {
        $viewer = $this->sanctumUser($request);

        $recentMoments = $this->moments->visibleTo($viewer)
            ->where('legacy_moments.user_id', '!=', $viewer->id)
            ->orderByDesc('legacy_moments.id')
            ->limit(10)
            ->get();

        $upcoming = $events->publishedEditions(['status' => 'upcoming'], perPage: 6);

        return $this->respond([
            'athletes' => AthleteResource::collection($suggested->handle($viewer)),
            'moments' => MomentResource::collection($recentMoments),
            'events' => EventEditionCardResource::collection($upcoming->getCollection()),
        ]);
    }
}
