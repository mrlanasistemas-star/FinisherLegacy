<?php

namespace App\Http\Controllers;

use App\Actions\Athletes\EnsureAthleteForUser;
use App\Http\Requests\UpdateAthleteProfileRequest;
use App\Models\EventParticipant;
use App\Models\Sport;
use App\Queries\Athletes\GetAthleteHistory;
use App\Queries\Athletes\GetAthleteProfileStats;
use App\Services\AthleteProfileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class AthleteProfileController extends Controller
{
    public function __construct(private readonly AthleteProfileService $profiles) {}

    /**
     * "Mi Perfil" (product consolidation brief §99-§101) — header, stats,
     * and a filterable history, not just an edit form. Reuses the exact
     * same GetAthleteHistory/GetAthleteProfileStats Queries the API's
     * /me/profile and /me/history endpoints call.
     */
    public function show(Request $request, EnsureAthleteForUser $ensureAthlete, GetAthleteHistory $history, GetAthleteProfileStats $stats): Response
    {
        $athlete = $ensureAthlete->handle($request->user(), 'dashboard_profile_show');
        $profile = $request->user()->athleteProfile;

        $filters = [
            'from' => $request->string('from')->toString() ?: null,
            'to' => $request->string('to')->toString() ?: null,
            'event_id' => $request->integer('event_id') ?: null,
            'sport_id' => $request->integer('sport_id') ?: null,
            'legacy_plate' => $request->string('legacy_plate')->toString() ?: null,
        ];

        $filteredParticipations = $history->handle($athlete, $filters)['participations'];

        // Filter dropdowns are built from the Athlete's full history, not
        // the already-filtered result — otherwise picking one event would
        // make every other event disappear from its own filter's options.
        $allParticipations = $history->handle($athlete)['participations']
            ->loadMissing('eventEdition.event.sport');

        return Inertia::render('dashboard/profile/Show', [
            'athlete' => [
                'legacy_id' => $athlete->uuid,
                'full_name' => $athlete->full_name,
            ],
            'profile' => $profile ? [
                'username' => $profile->username,
                'bio' => $profile->bio,
                'city' => $profile->city,
                'state' => $profile->state,
                'country' => $profile->country,
                'sport' => $profile->loadMissing('mainSport')->mainSport?->name,
                'profile_photo_url' => $profile->profile_photo_path
                    ? Storage::disk('public')->url($profile->profile_photo_path)
                    : null,
            ] : null,
            'stats' => $stats->handle($athlete),
            'filters' => $filters,
            'filterOptions' => [
                'events' => $allParticipations
                    ->pluck('eventEdition.event')
                    ->filter()
                    ->unique('id')
                    ->map(fn ($event) => ['id' => $event->id, 'name' => $event->name])
                    ->values(),
                'sports' => $allParticipations
                    ->pluck('eventEdition.event.sport')
                    ->filter()
                    ->unique('id')
                    ->map(fn ($sport) => ['id' => $sport->id, 'name' => $sport->name])
                    ->values(),
            ],
            'participations' => $filteredParticipations->map(fn (EventParticipant $p) => [
                'id' => $p->id,
                'event' => $p->eventEdition?->event?->name,
                'edition' => $p->eventEdition?->name,
                'race' => $p->eventRace?->name,
                'bib_number' => $p->bib_number,
                'event_date' => $p->eventEdition?->event_date?->toDateString(),
                'official_time' => $p->result?->official_time,
                'pace' => $p->result?->pace,
                'position' => $p->result?->overall_position,
                'has_plate' => $p->legacyPlateEntitlements->isNotEmpty(),
                'gear_count' => $p->gearSelections->count(),
            ])->values(),
        ]);
    }

    public function edit(Request $request): Response
    {
        $profile = $request->user()->athleteProfile;

        return Inertia::render('dashboard/profile/Edit', [
            'profile' => $profile ? [
                'username' => $profile->username,
                'bio' => $profile->bio,
                'city' => $profile->city,
                'state' => $profile->state,
                'country' => $profile->country,
                'main_sport_id' => $profile->main_sport_id,
                'profile_visibility' => $profile->profile_visibility->value,
                'profile_photo_url' => $profile->profile_photo_path
                    ? Storage::disk('public')->url($profile->profile_photo_path)
                    : null,
                'cover_photo_url' => $profile->cover_photo_path
                    ? Storage::disk('public')->url($profile->cover_photo_path)
                    : null,
            ] : null,
            'sports' => Sport::query()->where('active', true)->orderBy('sort_order')->get(['id', 'name']),
        ]);
    }

    public function update(UpdateAthleteProfileRequest $request): RedirectResponse
    {
        $this->profiles->update(
            $request->user(),
            $request->safe()->except(['profile_photo', 'cover_photo']),
            $request->file('profile_photo'),
            $request->file('cover_photo'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tu Legacy Profile fue actualizado.']);

        return to_route('dashboard.profile.edit');
    }
}
