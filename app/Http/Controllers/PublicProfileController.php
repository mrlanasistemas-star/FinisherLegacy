<?php

namespace App\Http\Controllers;

use App\Enums\AthleteEventMediaType;
use App\Enums\ProfileVisibility;
use App\Http\Resources\Api\V1\MomentResource;
use App\Models\AthleteEventMedia;
use App\Models\AthleteFollow;
use App\Models\AthleteProfile;
use App\Queries\Social\MomentQuery;
use App\Services\PublicAthleteProfileService;
use App\Services\Social\SocialVisibility;
use App\Support\Social\ReportReasons;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public Legacy Profile (/@username). Everything beyond the header is
 * filtered for the viewer: medals by their own visibility, posts through
 * SocialVisibility/MomentQuery, photos only when the athlete made them
 * public.
 *
 *  - Full access (canViewProfile) → profile/Show with content.
 *  - Followers-only profile, signed-in non-follower → profile/Show with
 *    `restricted: true` (header + Seguir, no content).
 *  - Otherwise (private, blocked, anonymous on followers-only) →
 *    profile/Private.
 */
class PublicProfileController extends Controller
{
    public function __construct(private readonly PublicAthleteProfileService $profiles) {}

    public function show(Request $request, AthleteProfile $athleteProfile, MomentQuery $moments, SocialVisibility $visibility): Response
    {
        $athleteProfile->load(['user', 'mainSport']);
        abort_if($athleteProfile->user === null, 404);
        $viewer = $request->user();

        $isOwner = $viewer !== null && $viewer->id === $athleteProfile->user_id;
        $canView = $this->profiles->isVisibleTo($athleteProfile, $viewer);

        if (! $canView && ! $visibility->canSeeProfileShell($athleteProfile, $viewer)) {
            return Inertia::render('profile/Private', [
                'username' => $athleteProfile->username,
                'followersOnly' => $athleteProfile->profile_visibility === ProfileVisibility::Followers
                    && ! $visibility->isHiddenBetween($viewer, $athleteProfile->user_id),
            ]);
        }

        $ownerId = $athleteProfile->user_id;
        $medals = $canView ? $this->profiles->publicMedals($athleteProfile) : collect();
        $athleteId = $canView ? $athleteProfile->user->athlete()->value('id') : null;

        return Inertia::render('profile/Show', [
            'isOwner' => $isOwner,
            'restricted' => ! $canView,
            'profile' => [
                ...$this->profiles->profilePayload($athleteProfile),
                'visibility' => $athleteProfile->profile_visibility->value,
            ],
            'stats' => $canView
                ? [
                    ...$this->profiles->statsPayload($athleteProfile, $medals),
                    'posts' => $moments->visibleTo($viewer)->where('legacy_moments.user_id', $ownerId)->count(),
                ]
                : ['medals' => 0, 'events' => 0, 'posts' => 0],
            'medals' => $medals->map(function ($medal) {
                $frontImage = $medal->images->firstWhere('type', 'front');

                return [
                    'id' => $medal->id,
                    'title' => $medal->title,
                    'distance_label' => $medal->distance_label,
                    'event_date' => $medal->event_date?->toDateString(),
                    'thumbnail_url' => $frontImage?->thumbnail_path
                        ? Storage::disk('public')->url($frontImage->thumbnail_path)
                        : null,
                ];
            })->values(),
            'social' => [
                'followers' => AthleteFollow::query()->where('following_id', $ownerId)->count(),
                'following' => AthleteFollow::query()->where('follower_id', $ownerId)->count(),
                'is_following' => $viewer !== null && $visibility->follows($viewer, $ownerId),
            ],
            'events' => $canView ? $this->profiles->recentEvents($athleteProfile, 20) : [],
            'posts' => $canView
                ? Inertia::scroll(fn () => MomentResource::collection(
                    $moments->paginate(
                        $moments->visibleTo($viewer)->where('legacy_moments.user_id', $ownerId),
                        12,
                    )
                ))
                : ['data' => []],
            'reportReasons' => ReportReasons::options(),
            // Public page = public photos only, even for the owner (it is
            // a preview of what everyone else sees).
            'photos' => $athleteId === null ? [] : AthleteEventMedia::query()
                ->where('athlete_id', $athleteId)
                ->where('type', AthleteEventMediaType::Image)
                ->where('is_public', true)
                ->with('eventParticipant.eventEdition.event')
                ->latest('id')
                ->limit(12)
                ->get()
                ->map(fn (AthleteEventMedia $media) => [
                    'uuid' => $media->uuid,
                    'url' => $media->url(),
                    'event' => $media->eventParticipant?->eventEdition?->event?->name,
                ]),
        ]);
    }
}
