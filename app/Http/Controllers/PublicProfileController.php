<?php

namespace App\Http\Controllers;

use App\Enums\AthleteEventMediaType;
use App\Http\Resources\Api\V1\MomentResource;
use App\Models\AthleteEventMedia;
use App\Models\AthleteFollow;
use App\Models\AthleteProfile;
use App\Queries\Social\MomentQuery;
use App\Services\PublicAthleteProfileService;
use App\Services\Social\SocialVisibility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public Legacy Profile (/@username). Everything beyond the header is
 * filtered for the viewer: medals by their own visibility, posts through
 * SocialVisibility/MomentQuery, photos only when the athlete made them
 * public. A private profile renders profile/Private for everyone but its
 * owner.
 */
class PublicProfileController extends Controller
{
    public function __construct(private readonly PublicAthleteProfileService $profiles) {}

    public function show(Request $request, AthleteProfile $athleteProfile, MomentQuery $moments, SocialVisibility $visibility): Response
    {
        $athleteProfile->load(['user', 'mainSport']);
        $viewer = $request->user();

        $isOwner = $viewer !== null && $viewer->id === $athleteProfile->user_id;

        if (! $this->profiles->isVisibleTo($athleteProfile, $viewer)) {
            return Inertia::render('profile/Private', [
                'username' => $athleteProfile->username,
            ]);
        }

        $medals = $this->profiles->publicMedals($athleteProfile);
        $athleteId = $athleteProfile->user->athlete()->value('id');

        return Inertia::render('profile/Show', [
            'isOwner' => $isOwner,
            'profile' => $this->profiles->profilePayload($athleteProfile),
            'stats' => $this->profiles->statsPayload($athleteProfile, $medals),
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
            }),
            'social' => [
                'followers' => AthleteFollow::query()->where('following_id', $athleteProfile->user_id)->count(),
                'following' => AthleteFollow::query()->where('follower_id', $athleteProfile->user_id)->count(),
                'is_following' => $viewer !== null && in_array($athleteProfile->user_id, $visibility->followingIds($viewer), true),
            ],
            'events' => $this->profiles->recentEvents($athleteProfile, 20),
            'posts' => MomentResource::collection(
                $moments->visibleTo($viewer)
                    ->where('legacy_moments.user_id', $athleteProfile->user_id)
                    ->orderByDesc('legacy_moments.id')
                    ->limit(6)
                    ->get()
            )->resolve($request),
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
