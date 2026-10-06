<?php

namespace App\Http\Controllers;

use App\Enums\AthleteEventMediaType;
use App\Http\Resources\Api\V1\MomentResource;
use App\Models\AthleteEventMedia;
use App\Models\AthleteFollow;
use App\Models\LegacyMoment;
use App\Models\Medal;
use App\Queries\Athletes\GetAthleteLegado;
use App\Queries\Social\MomentQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request, GetAthleteLegado $legado, MomentQuery $moments): Response
    {
        $user = $request->user();
        $user->loadMissing(['legacyId', 'athleteProfile.mainSport']);

        // Read-only counts for "Mi Legado" (product UX consolidation brief
        // §3-§6): the ecosystem summary reaches beyond medals/events into
        // what the athlete owns, never a second read model — just cheap
        // counts off the same canonical Athlete used by GetAthleteHistory.
        $athlete = $user->athlete()->first();

        $profile = $user->athleteProfile;
        $completion = null;

        if ($profile) {
            $checks = [
                filled($profile->username),
                filled($profile->profile_photo_path),
                filled($profile->bio),
                filled($profile->city),
                $profile->main_sport_id !== null,
                filled($profile->cover_photo_path),
            ];

            $completion = (int) round((count(array_filter($checks)) / count($checks)) * 100);
        }

        return Inertia::render('Dashboard', [
            'legacyId' => $user->legacyId?->code,
            'profile' => $profile ? [
                'username' => $profile->username,
                'profile_visibility' => $profile->profile_visibility->value,
                'completion' => $completion,
            ] : null,
            'stats' => [
                'medals' => $user->medals()->count(),
                'events' => $user->eventParticipations()->count(),
                'plates' => $user->plates()->count(),
                'legacyCodes' => $user->legacyCodes()->count(),
                'ownedProducts' => $athlete === null ? 0 : $athlete->ownedProducts()->count(),
                'media' => $athlete === null ? 0 : $athlete->eventMedia()->count(),
            ],
            'legado' => $athlete === null ? [] : $legado->handle($athlete),

            // Editorial "Mi Legado" header — the athlete's own data only.
            'athlete' => [
                'name' => $user->name,
                'username' => $profile?->username,
                'bio' => $profile?->bio,
                'city' => $profile?->city,
                'state' => $profile?->state,
                'country' => $profile?->country,
                'sport' => $profile?->mainSport?->name,
                'photo_url' => $profile?->profile_photo_path ? Storage::disk('public')->url($profile->profile_photo_path) : null,
                'cover_url' => $profile?->cover_photo_path ? Storage::disk('public')->url($profile->cover_photo_path) : null,
            ],
            'social' => [
                'followers' => AthleteFollow::query()->where('following_id', $user->id)->count(),
                'following' => AthleteFollow::query()->where('follower_id', $user->id)->count(),
                'posts' => LegacyMoment::query()->where('user_id', $user->id)->count(),
            ],
            'medals' => Medal::query()
                ->where('user_id', $user->id)
                ->with('images')
                ->latest('event_date')
                ->limit(12)
                ->get()
                ->map(function (Medal $medal) {
                    $front = $medal->images->firstWhere('type', 'front') ?? $medal->images->first();

                    return [
                        'id' => $medal->id,
                        'title' => $medal->title,
                        'distance_label' => $medal->distance_label,
                        'event_date' => $medal->event_date?->toDateString(),
                        'visibility' => $medal->visibility->value,
                        'thumbnail_url' => $front?->thumbnail_path ? Storage::disk('public')->url($front->thumbnail_path) : null,
                    ];
                }),
            'recentPhotos' => $athlete === null ? [] : AthleteEventMedia::query()
                ->where('athlete_id', $athlete->id)
                ->where('type', AthleteEventMediaType::Image)
                ->with('eventParticipant.eventEdition.event')
                ->latest('id')
                ->limit(12)
                ->get()
                ->map(fn (AthleteEventMedia $media) => [
                    'uuid' => $media->uuid,
                    'url' => $media->url(),
                    'is_public' => $media->is_public,
                    'event' => $media->eventParticipant?->eventEdition?->event?->name,
                    'participant_id' => $media->event_participant_id,
                ]),
            'recentPosts' => MomentResource::collection(
                $moments->visibleTo($user)
                    ->where('legacy_moments.user_id', $user->id)
                    ->orderByDesc('legacy_moments.id')
                    ->limit(3)
                    ->get()
            )->resolve($request),
        ]);
    }
}
