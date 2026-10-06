<?php

namespace App\Http\Controllers;

use App\Enums\AthleteEventMediaType;
use App\Enums\EditionStatus;
use App\Enums\EventStatus;
use App\Models\AthleteEventMedia;
use App\Models\EventEdition;
use App\Models\EventParticipant;
use App\Models\User;
use App\Services\Social\SocialVisibility;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Fotos / Mis fotos" — built on the event media that already exists
 * (AthleteEventMedia, uploaded per participation). Searching by event +
 * bib number only ever returns photos their owner made PUBLIC on a
 * profile the viewer may see (SocialVisibility); the signed-in athlete
 * additionally sees all of their own photos.
 *
 * Not implemented on purpose (no infrastructure exists yet): photographer
 * uploads, photo purchase and face recognition. The page presents those
 * as upcoming instead of simulating them.
 */
class PhotosController extends Controller
{
    private const int MAX_RESULTS = 60;

    public function __invoke(Request $request, SocialVisibility $visibility): Response
    {
        $viewer = $request->user();
        $editionId = $request->integer('evento') ?: null;
        $bib = trim(mb_substr($request->string('numero')->toString(), 0, 20));
        $searched = $editionId !== null && $bib !== '';

        return Inertia::render('photos/Index', [
            'events' => $this->eventOptions(),
            'filters' => ['evento' => $editionId, 'numero' => $bib],
            'searched' => $searched,
            'results' => $searched ? $this->search($editionId, $bib, $viewer, $visibility) : [],
            'mine' => $viewer === null ? null : $this->ownPhotos($viewer),
            'purchase' => [
                // No photo product/pricing exists yet — the selection panel
                // shows this honestly instead of a fake price.
                'available' => false,
            ],
        ]);
    }

    /**
     * Published editions that already happened — the only ones that can
     * have race photos.
     *
     * @return Collection<int, array{id: int, label: string, date: string|null}>
     */
    private function eventOptions(): Collection
    {
        return EventEdition::query()
            ->whereHas('event', fn ($q) => $q->where('status', EventStatus::Published))
            ->where('status', EditionStatus::Published)
            ->whereDate('event_date', '<=', now()->toDateString())
            ->with('event:id,name')
            ->orderByDesc('event_date')
            ->limit(100)
            ->get(['id', 'event_id', 'name', 'event_date'])
            ->map(fn (EventEdition $edition) => [
                'id' => $edition->id,
                'label' => trim(($edition->event?->name ?? 'Evento').($edition->name ? ' — '.$edition->name : '')),
                'date' => $edition->event_date?->toDateString(),
            ])
            ->values();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function search(int $editionId, string $bib, ?User $viewer, SocialVisibility $visibility): array
    {
        $participants = EventParticipant::query()
            ->where('event_edition_id', $editionId)
            ->where('bib_number', $bib)
            ->with(['athlete.user.athleteProfile', 'eventEdition.event', 'eventRace'])
            ->get();

        return $participants
            ->flatMap(function (EventParticipant $participant) use ($viewer, $visibility) {
                $owner = $participant->athlete?->user;
                $isOwner = $viewer !== null && $owner !== null && $owner->id === $viewer->id;
                $profile = $owner?->athleteProfile;

                // Without a visible profile behind them, photos stay private
                // to their owner even if flagged public.
                if (! $isOwner && ($profile === null || ! $visibility->canViewProfile($profile, $viewer))) {
                    return [];
                }

                return $participant->media()
                    ->where('type', AthleteEventMediaType::Image)
                    ->when(! $isOwner, fn ($q) => $q->where('is_public', true))
                    ->orderBy('sort_order')
                    ->limit(self::MAX_RESULTS)
                    ->get()
                    ->map(fn (AthleteEventMedia $media) => $this->photo($media, $participant));
            })
            ->take(self::MAX_RESULTS)
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function ownPhotos(User $viewer): array
    {
        $athlete = $viewer->athlete()->first();

        if ($athlete === null) {
            return [];
        }

        return AthleteEventMedia::query()
            ->where('athlete_id', $athlete->id)
            ->where('type', AthleteEventMediaType::Image)
            ->with(['eventParticipant.eventEdition.event', 'eventParticipant.eventRace'])
            ->latest('id')
            ->limit(self::MAX_RESULTS)
            ->get()
            ->map(fn (AthleteEventMedia $media) => $this->photo($media, $media->eventParticipant))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function photo(AthleteEventMedia $media, ?EventParticipant $participant): array
    {
        return [
            'uuid' => $media->uuid,
            'url' => $media->url(),
            'width' => $media->width,
            'height' => $media->height,
            'is_public' => $media->is_public,
            'event' => $participant?->eventEdition?->event?->name,
            'race' => $participant?->eventRace?->name,
            'participant_id' => $participant?->id,
            // Every photo today is uploaded by the athlete themself — there
            // is no photographer attribution to show yet.
            'source' => 'Subida por el atleta',
        ];
    }
}
