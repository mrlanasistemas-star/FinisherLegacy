<?php

namespace App\Http\Controllers\Api\V1\Social;

use App\Actions\Social\BlockUser;
use App\Actions\Social\FollowAthlete;
use App\Enums\ProfileVisibility;
use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Api\V1\Concerns\ResolvesAuthenticatedUser;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AthleteResource;
use App\Http\Resources\Api\V1\MomentResource;
use App\Models\AthleteFollow;
use App\Models\AthleteProfile;
use App\Models\User;
use App\Models\UserBlock;
use App\Queries\Social\MomentQuery;
use App\Services\Social\SocialVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Follow / followers / following / an athlete's moments / block. Every
 * endpoint first checks the viewer may see the profile at all (404
 * otherwise — never reveals that a private or blocking profile exists).
 */
class AthleteSocialController extends Controller
{
    use ApiResponses;
    use ResolvesAuthenticatedUser;

    public function __construct(private readonly SocialVisibility $visibility) {}

    public function follow(Request $request, AthleteProfile $athleteProfile, FollowAthlete $follow): JsonResponse
    {
        $viewer = $this->sanctumUser($request);
        $this->ensureVisible($athleteProfile, $viewer);

        $follow->handle($viewer, $athleteProfile);

        return $this->respond($this->followState($athleteProfile, $viewer), "Listo. Ya sigues a {$athleteProfile->user->first_name}.");
    }

    public function unfollow(Request $request, AthleteProfile $athleteProfile): JsonResponse
    {
        $viewer = $this->sanctumUser($request);

        AthleteFollow::query()->where('follower_id', $viewer->id)->where('following_id', $athleteProfile->user_id)->delete();
        $this->visibility->forget($viewer);

        return $this->respond($this->followState($athleteProfile, $viewer));
    }

    public function followers(Request $request, AthleteProfile $athleteProfile): JsonResponse
    {
        $viewer = $this->sanctumUser($request);
        $this->ensureVisible($athleteProfile, $viewer);

        return $this->athleteList(
            AthleteFollow::query()->where('following_id', $athleteProfile->user_id)->select('follower_id'),
            $viewer,
        );
    }

    public function following(Request $request, AthleteProfile $athleteProfile): JsonResponse
    {
        $viewer = $this->sanctumUser($request);
        $this->ensureVisible($athleteProfile, $viewer);

        return $this->athleteList(
            AthleteFollow::query()->where('follower_id', $athleteProfile->user_id)->select('following_id'),
            $viewer,
        );
    }

    public function moments(Request $request, AthleteProfile $athleteProfile, MomentQuery $moments): JsonResponse
    {
        $viewer = $this->sanctumUser($request);
        $this->ensureVisible($athleteProfile, $viewer);

        $page = $moments->paginate(
            $moments->visibleTo($viewer)->where('legacy_moments.user_id', $athleteProfile->user_id),
            (int) config('finisher.social.feed_per_page'),
        );

        return MomentResource::collection($page)->response();
    }

    public function block(Request $request, AthleteProfile $athleteProfile, BlockUser $block): JsonResponse
    {
        $viewer = $this->sanctumUser($request);
        $block->block($viewer, $athleteProfile->user);

        return $this->respond(['blocked' => true], 'Bloqueaste a este atleta. Ya no verán su actividad entre ustedes.');
    }

    public function unblock(Request $request, AthleteProfile $athleteProfile, BlockUser $block): JsonResponse
    {
        $block->unblock($this->sanctumUser($request), $athleteProfile->user);

        return $this->respond(['blocked' => false], 'Desbloqueaste a este atleta.');
    }

    public function blocks(Request $request): JsonResponse
    {
        $viewer = $this->sanctumUser($request);

        $blocked = UserBlock::query()
            ->where('blocker_id', $viewer->id)
            ->with('blocked.athleteProfile')
            ->latest('id')
            ->paginate(30);

        return AthleteResource::collection($blocked->through(fn (UserBlock $block) => $block->blocked))->response();
    }

    /**
     * @param  Builder<AthleteFollow>  $userIds  A single-column subquery of user ids.
     */
    private function athleteList(Builder $userIds, User $viewer): JsonResponse
    {
        $hidden = $this->visibility->hiddenUserIds($viewer);
        $following = $this->visibility->followingIds($viewer);

        $page = User::query()
            ->whereIn('users.id', $userIds)
            ->where(fn (Builder $q) => $q
                ->where('users.id', $viewer->id)
                ->orWhereHas('athleteProfile', fn (Builder $p) => $p->where('profile_visibility', ProfileVisibility::Public)))
            ->whereHas('athleteProfile')
            ->when($hidden !== [], fn (Builder $q) => $q->whereNotIn('users.id', $hidden))
            ->with('athleteProfile')
            ->orderBy('first_name')
            ->orderBy('id')
            ->paginate(30);

        $page->getCollection()->each(fn (User $user) => $user->setAttribute('is_following', in_array($user->id, $following, true)));

        return AthleteResource::collection($page)->response();
    }

    private function ensureVisible(AthleteProfile $profile, User $viewer): void
    {
        $profile->loadMissing('user');
        abort_unless($profile->user !== null && $this->visibility->canViewProfile($profile, $viewer), 404);
    }

    /**
     * @return array{is_following: bool, followers_count: int, following_count: int}
     */
    private function followState(AthleteProfile $profile, User $viewer): array
    {
        return [
            'is_following' => AthleteFollow::query()->where('follower_id', $viewer->id)->where('following_id', $profile->user_id)->exists(),
            'followers_count' => AthleteFollow::query()->where('following_id', $profile->user_id)->count(),
            'following_count' => AthleteFollow::query()->where('follower_id', $profile->user_id)->count(),
        ];
    }
}
