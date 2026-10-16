<?php

namespace App\Queries\Athletes;

use App\Enums\AthleteEventMediaType;
use App\Enums\PreregistrationStatus;
use App\Enums\RegistrationStatus;
use App\Models\Athlete;
use App\Models\EventEdition;
use App\Models\EventParticipant;
use App\Models\EventPreregistration;
use App\Models\LegacyPlateEntitlement;
use App\Models\User;
use App\Support\RaceDistanceFormatter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * "MI LEGADO" timeline (product spec §3-§4/§102: "Cada carrera suma una
 * página") — one card per participation, ordered by when the race actually
 * happened. Every read is bounded: the dashboard takes the latest N cards,
 * "Mis eventos" paginates; the per-row data comes from eager loads and
 * aggregate counts, never a query per card.
 */
class GetAthleteLegado
{
    /** Cards shown on the Mi legado home before linking to "Mis eventos". */
    public const HOME_LIMIT = 24;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function handle(Athlete $athlete, int $limit = self::HOME_LIMIT): array
    {
        return $this->cards($this->query($athlete)->limit($limit)->get(), $athlete);
    }

    /**
     * Participations that already happened (or have no date yet), newest
     * first, one page at a time.
     *
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function past(Athlete $athlete, int $perPage = 12): LengthAwarePaginator
    {
        $paginator = $this->query($athlete)
            ->where(fn (Builder $q) => $q
                ->whereDoesntHave('eventEdition')
                ->orWhereHas('eventEdition', fn (Builder $edition) => $edition->whereDate('event_date', '<=', today())))
            ->paginate($perPage)
            ->withQueryString();

        $entitlements = $this->entitlements(new EloquentCollection($paginator->items()), $athlete);

        return $paginator->through(fn (EventParticipant $participant) => $this->card(
            $participant,
            $entitlements->get($participant->id, new Collection)->first(),
        ));
    }

    /**
     * Registered participations whose race is still ahead, soonest first.
     * Small by nature (nobody is registered to hundreds of future races),
     * still capped.
     *
     * @return array<int, array<string, mixed>>
     */
    public function upcoming(Athlete $athlete, int $limit = 12): array
    {
        $participants = $athlete->eventParticipations()
            ->where('registration_status', RegistrationStatus::Registered)
            ->whereHas('eventEdition', fn (Builder $edition) => $edition->whereDate('event_date', '>', today()))
            ->with(['eventEdition.event', 'eventRace'])
            ->orderBy(
                EventEdition::query()
                    ->select('event_date')
                    ->whereColumn('event_editions.id', 'event_participants.event_edition_id'),
            )
            ->limit($limit)
            ->get();

        return $participants->map(fn (EventParticipant $participant) => [
            'id' => $participant->id,
            'kind' => 'registered',
            'event' => $participant->eventEdition?->event?->name,
            'event_slug' => $participant->eventEdition?->event?->slug,
            'edition' => $participant->eventEdition?->name,
            'race' => $participant->eventRace?->name,
            'distance' => RaceDistanceFormatter::format($participant->eventRace),
            'bib_number' => $participant->bib_number,
            'event_date' => $participant->eventEdition?->event_date?->toDateString(),
            'city' => $participant->eventEdition?->city,
            'cover_url' => $this->coverUrl($participant->eventEdition?->event?->cover_path),
        ])->values()->all();
    }

    /**
     * Pre-registrations still waiting for their race (not yet matched to a
     * real registration, which `upcoming()` already shows), soonest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function preregistrations(User $user, int $limit = 6): array
    {
        return EventPreregistration::query()
            ->where('user_id', $user->id)
            ->whereNull('matched_participant_id')
            ->whereIn('status', [PreregistrationStatus::Pending, PreregistrationStatus::Confirmed])
            ->whereHas('eventEdition', fn (Builder $edition) => $edition->whereDate('event_date', '>', today()))
            ->with(['eventEdition.event', 'eventRace'])
            ->orderBy(
                EventEdition::query()
                    ->select('event_date')
                    ->whereColumn('event_editions.id', 'event_preregistrations.event_edition_id'),
            )
            ->limit($limit)
            ->get()
            ->map(fn (EventPreregistration $preregistration) => [
                'id' => null,
                'kind' => 'preregistered',
                'event' => $preregistration->eventEdition?->event?->name,
                'event_slug' => $preregistration->eventEdition?->event?->slug,
                'edition' => $preregistration->eventEdition?->name,
                'race' => $preregistration->eventRace?->name,
                'distance' => RaceDistanceFormatter::format($preregistration->eventRace),
                'bib_number' => $preregistration->bib_number,
                'event_date' => $preregistration->eventEdition?->event_date?->toDateString(),
                'city' => $preregistration->eventEdition?->city,
                'cover_url' => $this->coverUrl($preregistration->eventEdition?->event?->cover_path),
            ])
            ->values()
            ->all();
    }

    /**
     * Races, kilometres and seasons — three aggregate queries, regardless
     * of how long the athlete's history is.
     *
     * @return array{races: int, km: float, years: int, first_year: int|null}
     */
    public function totals(Athlete $athlete): array
    {
        $pastScope = fn (Builder $q) => $q->whereHas(
            'eventEdition',
            fn (Builder $edition) => $edition->whereDate('event_date', '<=', today()),
        );

        $races = $athlete->eventParticipations()->where($pastScope)->count();

        $km = (float) $athlete->eventParticipations()
            ->where($pastScope)
            ->join('event_races', 'event_participants.event_race_id', '=', 'event_races.id')
            ->where('event_races.distance_unit', 'km')
            ->sum('event_races.distance_value');

        $years = EventEdition::query()
            ->whereIn('id', $athlete->eventParticipations()->select('event_edition_id'))
            ->whereDate('event_date', '<=', today())
            ->pluck('event_date')
            ->map(fn ($date) => $date->year)
            ->unique();

        return [
            'races' => $races,
            'km' => round($km, 1),
            'years' => $years->count(),
            'first_year' => $years->min(),
        ];
    }

    /** @return Builder<EventParticipant> */
    private function query(Athlete $athlete): Builder
    {
        return $athlete->eventParticipations()
            ->getQuery()
            ->with(['eventEdition.event', 'eventRace', 'result', 'medals.images', 'plates'])
            ->withCount([
                'media as photo_count' => fn ($q) => $q->where('type', AthleteEventMediaType::Image->value),
                'media as video_count' => fn ($q) => $q->where('type', AthleteEventMediaType::Video->value),
                'gearSelections as gear_count',
            ])
            ->orderByDesc(
                EventEdition::query()
                    ->select('event_date')
                    ->whereColumn('event_editions.id', 'event_participants.event_edition_id'),
            )
            ->orderByDesc('event_participants.created_at');
    }

    /**
     * @param  EloquentCollection<int, EventParticipant>  $participants
     * @return array<int, array<string, mixed>>
     */
    private function cards(EloquentCollection $participants, Athlete $athlete): array
    {
        $entitlements = $this->entitlements($participants, $athlete);

        return $participants
            ->map(fn (EventParticipant $participant) => $this->card(
                $participant,
                $entitlements->get($participant->id, new Collection)->first(),
            ))
            ->values()
            ->all();
    }

    /**
     * One query for every card's Legacy Plate right, grouped by participation.
     *
     * @param  EloquentCollection<int, EventParticipant>  $participants
     * @return Collection<int|string, EloquentCollection<int, LegacyPlateEntitlement>>
     */
    private function entitlements(EloquentCollection $participants, Athlete $athlete): Collection
    {
        if ($participants->isEmpty()) {
            return new Collection;
        }

        return LegacyPlateEntitlement::query()
            ->where('athlete_id', $athlete->id)
            ->whereIn('event_participant_id', $participants->modelKeys())
            ->latest('created_at')
            ->get()
            ->groupBy('event_participant_id')
            ->toBase();
    }

    /** @return array<string, mixed> */
    private function card(EventParticipant $participant, ?LegacyPlateEntitlement $entitlement): array
    {
        $medal = $participant->medals->first();
        $medalImage = $medal?->images->sortBy('sort_order')->first();
        $plate = $participant->plates->first();
        $date = $participant->eventEdition?->event_date;

        return [
            'id' => $participant->id,
            'event' => $participant->eventEdition?->event?->name,
            'event_slug' => $participant->eventEdition?->event?->slug,
            'edition' => $participant->eventEdition?->name,
            'race' => $participant->eventRace?->name,
            'distance' => RaceDistanceFormatter::format($participant->eventRace),
            'bib_number' => $participant->bib_number,
            'event_date' => $date?->toDateString(),
            'year' => $date?->year,
            'city' => $participant->eventEdition?->city,
            'upcoming' => $date !== null && $date->isFuture(),
            'official_time' => $participant->result?->official_time,
            'pace' => $participant->result?->pace,
            'position' => $participant->result?->overall_position,
            'image_url' => $medalImage
                ? Storage::disk('public')->url($medalImage->thumbnail_path ?? $medalImage->optimized_path ?? $medalImage->original_path)
                : null,
            'cover_url' => $this->coverUrl($participant->eventEdition?->event?->cover_path),
            'has_medal' => $participant->medals->isNotEmpty(),
            'has_legacy_plate' => $plate !== null || $entitlement !== null,
            'legacy_plate_status' => $plate?->status->value ?? $entitlement?->status->value,
            'photo_count' => (int) $participant->photo_count,
            'video_count' => (int) $participant->video_count,
            'gear_count' => (int) $participant->getAttribute('gear_count'),
        ];
    }

    private function coverUrl(?string $path): ?string
    {
        return $path ? asset('storage/'.$path) : null;
    }
}
