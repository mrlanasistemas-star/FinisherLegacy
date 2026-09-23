<?php

namespace App\Services;

use App\Models\AthleteProfile;
use App\Models\EventParticipant;
use App\Models\Medal;
use App\Models\User;
use App\Services\Social\SocialVisibility;
use App\Support\RaceDistanceFormatter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Builds the payload for a public Legacy Profile page — shared by the web
 * /@{username} route and the /api/v1/athletes/{username} endpoint, so the
 * "who can see this profile" and "which medals count" rules only live here.
 */
class PublicAthleteProfileService
{
    /**
     * Delegates to the social layer's single privacy rule (private profile
     * → owner only; a block in either direction hides it too).
     */
    public function isVisibleTo(AthleteProfile $profile, ?User $viewer): bool
    {
        return app(SocialVisibility::class)->canViewProfile($profile, $viewer);
    }

    /**
     * The athlete's most recent participations with results — the public
     * "Carreras" list. Prefers the canonical Athlete identity, falls back
     * to the User link for legacy rows.
     *
     * @return array<int, array<string, mixed>>
     */
    public function recentEvents(AthleteProfile $profile, int $limit = 10): array
    {
        $athleteId = $profile->user->athlete()->value('id');

        return EventParticipant::query()
            ->where(fn ($q) => $athleteId !== null
                ? $q->where('athlete_id', $athleteId)->orWhere('user_id', $profile->user_id)
                : $q->where('user_id', $profile->user_id))
            ->with(['eventEdition.event', 'eventRace', 'result'])
            ->join('event_editions', 'event_editions.id', '=', 'event_participants.event_edition_id')
            ->orderByDesc('event_editions.event_date')
            ->select('event_participants.*')
            ->limit($limit)
            ->get()
            ->map(fn (EventParticipant $participant) => [
                'event' => $participant->eventEdition?->event?->name,
                'event_slug' => $participant->eventEdition?->event?->slug,
                'edition' => $participant->eventEdition?->name,
                'event_date' => $participant->eventEdition?->event_date?->toDateString(),
                'race' => $participant->eventRace?->name,
                'distance' => RaceDistanceFormatter::format($participant->eventRace),
                'official_time' => $participant->result?->official_time,
                'pace' => $participant->result?->pace,
            ])
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, Medal>
     */
    public function publicMedals(AthleteProfile $profile): Collection
    {
        return $profile->user->medals()
            ->where('visibility', 'public')
            ->with('images')
            ->latest()
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function profilePayload(AthleteProfile $profile): array
    {
        return [
            'name' => $profile->user->name,
            'username' => $profile->username,
            'bio' => $profile->bio,
            'city' => $profile->city,
            'state' => $profile->state,
            'country' => $profile->country,
            'sport' => $profile->mainSport?->name,
            'photo_url' => $profile->profile_photo_path
                ? Storage::disk('public')->url($profile->profile_photo_path)
                : null,
            'cover_url' => $profile->cover_photo_path
                ? Storage::disk('public')->url($profile->cover_photo_path)
                : null,
        ];
    }

    /**
     * @param  Collection<int, Medal>  $medals
     * @return array<string, mixed>
     */
    public function statsPayload(AthleteProfile $profile, Collection $medals): array
    {
        return [
            'medals' => $medals->count(),
            'events' => $profile->user->eventParticipations()->count(),
        ];
    }
}
