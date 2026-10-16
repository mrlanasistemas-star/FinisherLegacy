<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\MomentResource;
use App\Http\Resources\Api\V1\PublicMedalResource;
use App\Models\AthleteFollow;
use App\Models\AthleteProfile;
use App\Models\User;
use App\Models\UserBlock;
use App\Queries\Social\MomentQuery;
use App\Services\PublicAthleteProfileService;
use App\Services\Social\SocialVisibility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `GET /athletes/{username}` — the athlete's public Legacy: identity,
 * stats (incl. followers/following), follow state for the viewer, recent
 * Moments, recent races and public medals. Private profiles and blocked
 * relationships answer 404 (never "this profile is private" — that would
 * confirm it exists).
 */
class PublicAthleteController extends Controller
{
    use ApiResponses;

    public function __construct(
        private readonly PublicAthleteProfileService $profiles,
        private readonly SocialVisibility $visibility,
    ) {}

    public function show(Request $request, AthleteProfile $athleteProfile, MomentQuery $moments): JsonResponse
    {
        $athleteProfile->load(['user', 'mainSport']);
        abort_if($athleteProfile->user === null, 404);

        // Public route (no auth:sanctum middleware) — a Bearer-
        // authenticated mobile viewer must be recognized too, and 'sanctum'
        // is checked first so a stale, unrelated 'web' session cookie can
        // never be mistaken for who is actually asking (see
        // App\Http\Controllers\Api\V1\Concerns\ResolvesAuthenticatedUser).
        $viewer = $request->user('sanctum') ?? $request->user();
        $viewer = $viewer instanceof User ? $viewer : null;

        $ownerId = $athleteProfile->user_id;
        $isOwn = $viewer?->id === $ownerId;

        if (! $this->profiles->isVisibleTo($athleteProfile, $viewer)) {
            // Followers-only profile seen by a signed-in non-follower: the
            // header + follow state only (`restricted: true`), no content.
            abort_unless($this->visibility->canSeeProfileShell($athleteProfile, $viewer), 404);

            return $this->respond([
                'restricted' => true,
                'profile' => $this->profiles->profilePayload($athleteProfile),
                'stats' => [
                    'medals' => 0,
                    'events' => 0,
                    'followers' => AthleteFollow::query()->where('following_id', $ownerId)->count(),
                    'following' => AthleteFollow::query()->where('follower_id', $ownerId)->count(),
                    'moments' => 0,
                ],
                'viewer' => [
                    'is_own' => false,
                    'is_following' => false,
                    'is_blocked' => false,
                    'can_follow' => true,
                ],
                'medals' => [],
                'recent_moments' => [],
                'recent_events' => [],
            ]);
        }
        $medals = $this->profiles->publicMedals($athleteProfile);

        $recentMoments = $moments->visibleTo($viewer)
            ->where('legacy_moments.user_id', $ownerId)
            ->orderByDesc('legacy_moments.id')
            ->limit(6)
            ->get();

        return $this->respond([
            'restricted' => false,
            'profile' => $this->profiles->profilePayload($athleteProfile),
            'stats' => [
                ...$this->profiles->statsPayload($athleteProfile, $medals),
                'followers' => AthleteFollow::query()->where('following_id', $ownerId)->count(),
                'following' => AthleteFollow::query()->where('follower_id', $ownerId)->count(),
                'moments' => $moments->visibleTo($viewer)->where('legacy_moments.user_id', $ownerId)->count(),
            ],
            'viewer' => [
                'is_own' => $isOwn,
                'is_following' => $viewer !== null && ! $isOwn && in_array($ownerId, $this->visibility->followingIds($viewer), true),
                'is_blocked' => $viewer !== null && UserBlock::query()->where('blocker_id', $viewer->id)->where('blocked_id', $ownerId)->exists(),
                'can_follow' => $viewer !== null && ! $isOwn,
            ],
            'medals' => PublicMedalResource::collection($medals),
            'recent_moments' => MomentResource::collection($recentMoments),
            'recent_events' => $this->profiles->recentEvents($athleteProfile),
        ]);
    }
}
