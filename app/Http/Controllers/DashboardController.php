<?php

namespace App\Http\Controllers;

use App\Enums\AthleteEventMediaType;
use App\Http\Resources\Api\V1\MomentResource;
use App\Models\AthleteEventMedia;
use App\Models\AthleteFollow;
use App\Models\AthleteProfile;
use App\Models\LegacyMoment;
use App\Queries\Athletes\GetAthleteLegado;
use App\Queries\Social\MomentQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Mi legado" — the athlete's home inside Mi espacio (spec §7/§134): a
 * greeting, what's next, the running totals and the timeline of every race
 * ("Cada carrera suma una página."), plus the latest moments. Every list is
 * capped and every row's relations are eager-loaded or aggregated — the
 * query count does not grow with the athlete's history.
 */
class DashboardController extends Controller
{
    public function index(Request $request, GetAthleteLegado $legado, MomentQuery $moments): Response
    {
        $user = $request->user();
        $user->loadMissing(['legacyId', 'athleteProfile.mainSport']);

        $athlete = $user->athlete()->first();
        $profile = $user->athleteProfile;

        $timeline = $athlete === null ? [] : $legado->handle($athlete);
        $upcoming = $athlete === null ? [] : $legado->upcoming($athlete, 1);

        return Inertia::render('Dashboard', [
            'legacyId' => $user->legacyId?->code,
            'profile' => $profile ? [
                'username' => $profile->username,
                'profile_visibility' => $profile->profile_visibility->value,
                'completion' => $this->completion($profile),
            ] : null,
            'stats' => [
                'medals' => $user->medals()->count(),
                'events' => $athlete === null ? 0 : $athlete->eventParticipations()->count(),
                'plates' => $athlete === null ? 0 : $athlete->plates()->count(),
                'ownedProducts' => $athlete === null ? 0 : $athlete->ownedProducts()->count(),
                'media' => $athlete === null ? 0 : $athlete->eventMedia()->count(),
            ],
            'totals' => $athlete === null
                ? ['races' => 0, 'km' => 0, 'years' => 0, 'first_year' => null]
                : $legado->totals($athlete),
            'legado' => $timeline,
            'legadoLimit' => GetAthleteLegado::HOME_LIMIT,
            'nextEvent' => $upcoming[0] ?? ($legado->preregistrations($user, 1)[0] ?? null),
            'athlete' => [
                'name' => $user->name,
                'first_name' => $user->first_name ?: str($user->name)->before(' ')->toString(),
                'username' => $profile?->username,
                'photo_url' => $profile?->profile_photo_path ? Storage::disk('public')->url($profile->profile_photo_path) : null,
                'cover_url' => $profile?->cover_photo_path ? Storage::disk('public')->url($profile->cover_photo_path) : null,
                'sport' => $profile?->mainSport?->name,
                'city' => $profile?->city,
            ],
            'social' => [
                'followers' => AthleteFollow::query()->where('following_id', $user->id)->count(),
                'following' => AthleteFollow::query()->where('follower_id', $user->id)->count(),
                'posts' => LegacyMoment::query()->where('user_id', $user->id)->count(),
            ],
            'recentPhotos' => $athlete === null ? [] : AthleteEventMedia::query()
                ->where('athlete_id', $athlete->id)
                ->where('type', AthleteEventMediaType::Image)
                ->with('eventParticipant.eventEdition.event')
                ->latest('id')
                ->limit(8)
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
                    ->limit(2)
                    ->get()
            )->resolve($request),
        ]);
    }

    private function completion(AthleteProfile $profile): int
    {
        $checks = [
            filled($profile->username),
            filled($profile->profile_photo_path),
            filled($profile->bio),
            filled($profile->city),
            $profile->main_sport_id !== null,
            filled($profile->cover_photo_path),
        ];

        return (int) round((count(array_filter($checks)) / count($checks)) * 100);
    }
}
