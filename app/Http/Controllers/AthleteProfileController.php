<?php

namespace App\Http\Controllers;

use App\Actions\Athletes\EnsureAthleteForUser;
use App\Enums\ProfileVisibility;
use App\Http\Requests\UpdateAthleteProfileRequest;
use App\Models\AthleteFollow;
use App\Models\EventEdition;
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

        $participations = $history->handle($athlete, $filters, perPage: 20);
        $participations->through(fn (EventParticipant $p) => GetAthleteHistory::summarize($p));

        // Filter dropdowns come from a small, purpose-built distinct-editions
        // query, not from loading every participation just to pluck an
        // event/sport off it (product consolidation brief §27-§28) — and
        // deliberately from the Athlete's full history, not the already-
        // filtered result, or picking one event would make every other
        // event disappear from its own filter's options.
        $editions = EventEdition::query()
            ->whereIn('id', $athlete->eventParticipations()->select('event_edition_id'))
            ->with('event.sport')
            ->get();

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
                'cover_photo_url' => $profile->cover_photo_path
                    ? Storage::disk('public')->url($profile->cover_photo_path)
                    : null,
                'profile_visibility' => $profile->profile_visibility->value,
                'public_url' => $profile->username ? route('profile.public', $profile->username) : null,
            ] : null,
            'visibilityOptions' => self::visibilityOptions(),
            'social' => [
                'followers' => AthleteFollow::query()->where('following_id', $request->user()->id)->count(),
                'following' => AthleteFollow::query()->where('follower_id', $request->user()->id)->count(),
            ],
            'stats' => $stats->handle($athlete),
            'filters' => $filters,
            'filterOptions' => [
                'events' => $editions->pluck('event')->filter()->unique('id')
                    ->map(fn ($event) => ['id' => $event->id, 'name' => $event->name])
                    ->values(),
                'sports' => $editions->pluck('event.sport')->filter()->unique('id')
                    ->map(fn ($sport) => ['id' => $sport->id, 'name' => $sport->name])
                    ->values(),
            ],
            'participations' => $participations,
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
            'visibilityOptions' => self::visibilityOptions(),
        ]);
    }

    /**
     * Every privacy level the profile supports today, in human words — read
     * from the enum so a new level (e.g. "solo seguidores") shows up here
     * as soon as it exists, without a second list to keep in sync.
     *
     * @return list<array{value: string, label: string, description: string}>
     */
    private static function visibilityOptions(): array
    {
        $copy = [
            'public' => ['Público', 'Cualquiera puede ver tu perfil y tu historia.'],
            'followers' => ['Solo seguidores', 'Solo las personas que te siguen ven tu historia.'],
            'private' => ['Privado', 'Solo tú ves tu historia.'],
        ];

        return array_map(function (ProfileVisibility $case) use ($copy): array {
            [$label, $description] = $copy[$case->value];

            return ['value' => $case->value, 'label' => $label, 'description' => $description];
        }, ProfileVisibility::cases());
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
