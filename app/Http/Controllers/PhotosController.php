<?php

namespace App\Http\Controllers;

use App\Actions\Photos\CheckoutEventPhotos;
use App\Enums\AthleteEventMediaType;
use App\Enums\EditionStatus;
use App\Enums\EventStatus;
use App\Models\AthleteEventMedia;
use App\Models\EventEdition;
use App\Models\EventParticipant;
use App\Models\EventPhoto;
use App\Models\PhotoSale;
use App\Models\User;
use App\Services\Social\SocialVisibility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "Fotos" — the photo marketplace for athletes:
 *  - signed in: the athlete's own participations (event + their bib) are
 *    already known, so their photographers' photos appear automatically,
 *    grouped by event — they only choose which ones to buy;
 *  - search an event + bib number across PHOTOGRAPHERS' published photos
 *    (watermarked preview, price, buy) and athletes' own public photos;
 *  - buy a selection (a regular Order paid through the existing payment
 *    flow; RecordPhotoSales unlocks the download on payment);
 *  - "Mis fotos": purchased photos (full-resolution download) and the
 *    photos the athlete uploaded.
 */
class PhotosController extends Controller
{
    private const int MAX_RESULTS = 120;

    public function __invoke(Request $request, SocialVisibility $visibility): Response
    {
        $viewer = $request->user();
        $editionId = $request->integer('evento') ?: null;
        $bib = ltrim(trim(mb_substr($request->string('numero')->toString(), 0, 20)), '#');
        $searched = $editionId !== null && $bib !== '';
        $owned = $viewer === null ? [] : PhotoSale::query()->where('buyer_user_id', $viewer->id)->pluck('event_photo_id')->all();

        return Inertia::render('photos/Index', [
            'events' => $this->eventOptions(),
            'filters' => ['evento' => $editionId, 'numero' => $bib],
            'searched' => $searched,
            'myEvents' => $viewer === null ? [] : $this->myEvents($viewer, $owned),
            'results' => $searched ? [
                ...$this->photographerResults($editionId, $bib, $owned),
                ...$this->athleteResults($editionId, $bib, $viewer, $visibility),
            ] : [],
            'purchased' => $viewer === null ? null : $this->purchased($viewer),
            'mine' => $viewer === null ? null : $this->ownPhotos($viewer),
            'purchase' => ['available' => true, 'currency' => config('finisher.photos.currency', 'MXN')],
        ]);
    }

    public function checkout(Request $request, CheckoutEventPhotos $checkout): RedirectResponse
    {
        $data = $request->validate([
            'photos' => ['required', 'array', 'min:1', 'max:60'],
            'photos.*' => ['string'],
        ]);

        $order = $checkout->handle($request->user(), $data['photos']);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tu pedido está listo: completa el pago para descargar tus fotos.']);

        return to_route('store.orders.show', $order);
    }

    public function download(Request $request, PhotoSale $sale): StreamedResponse|RedirectResponse
    {
        // Only the buyer — anyone else gets a 404 (never confirms it exists).
        abort_unless($sale->buyer_user_id === $request->user()->id, 404);

        $photo = $sale->photo;

        if ($photo === null || ! Storage::disk('event_photo_originals')->exists($photo->original_path)) {
            // Paid but the original is gone (storage incident): tell the
            // buyer instead of a bare 404 and leave a trail for support.
            report(new RuntimeException("Original missing for photo sale {$sale->uuid}"));
            Inertia::flash('toast', ['type' => 'error', 'message' => 'No pudimos encontrar el archivo original de esta foto. Escríbenos desde Contacto y lo resolvemos.']);

            return back();
        }

        $sale->increment('download_count');
        $extension = pathinfo($photo->original_path, PATHINFO_EXTENSION) ?: 'jpg';

        return Storage::disk('event_photo_originals')->download($photo->original_path, "finisher-legacy-{$photo->uuid}.{$extension}");
    }

    /**
     * Every participation of the viewer that has a bib (as user or as the
     * claimed athlete), newest first, with the photographers' photos tagged
     * with that bib in that event. One entry per event edition.
     *
     * @param  list<int>  $owned
     * @return list<array<string, mixed>>
     */
    private function myEvents(User $viewer, array $owned): array
    {
        $athleteId = $viewer->athlete?->id;

        return array_values(EventParticipant::query()
            ->where(fn ($q) => $q->where('user_id', $viewer->id)
                ->when($athleteId, fn ($w, $id) => $w->orWhere('athlete_id', $id)))
            ->whereNotNull('bib_number')
            ->where('bib_number', '!=', '')
            ->with(['eventEdition.event', 'eventRace'])
            ->latest('id')
            ->limit(30)
            ->get()
            ->unique('event_edition_id')
            ->filter(fn (EventParticipant $p) => $p->eventEdition !== null)
            ->map(fn (EventParticipant $p) => [
                'edition_id' => $p->event_edition_id,
                'event' => $p->eventEdition->event->name,
                'race' => $p->eventRace?->name,
                'date' => $p->eventEdition->event_date?->toDateString(),
                'bib' => ltrim((string) $p->bib_number, '#'),
                'photos' => $this->photographerResults($p->event_edition_id, ltrim((string) $p->bib_number, '#'), $owned),
            ])
            ->sortByDesc(fn (array $e) => [count($e['photos']) > 0, $e['date']])
            ->values()
            ->all());
    }

    /**
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
     * @param  list<int>  $owned
     * @return list<array<string, mixed>>
     */
    private function photographerResults(int $editionId, string $bib, array $owned): array
    {
        return EventPhoto::query()
            ->where('event_edition_id', $editionId)
            ->forSale()
            ->whereHas('bibs', fn ($q) => $q->where('bib_number', $bib))
            ->with(['photographer', 'eventEdition.event'])
            ->latest('id')
            ->limit(self::MAX_RESULTS)
            ->get()
            ->map(fn (EventPhoto $photo) => [
                'uuid' => $photo->uuid,
                'kind' => 'pro',
                'url' => $photo->previewUrl(),
                'thumb_url' => $photo->thumbUrl(),
                'width' => $photo->width,
                'height' => $photo->height,
                'event' => $photo->eventEdition?->event?->name,
                'race' => null,
                'source' => $photo->photographer?->display_name ?? 'Fotógrafo',
                'price_minor' => $photo->price_minor,
                'currency' => $photo->currency,
                'owned' => in_array($photo->id, $owned, true),
                'is_public' => true,
            ])
            ->all();
    }

    /**
     * Athletes' own photos made public (free to view, not for sale).
     *
     * @return list<array<string, mixed>>
     */
    private function athleteResults(int $editionId, string $bib, ?User $viewer, SocialVisibility $visibility): array
    {
        return EventParticipant::query()
            ->where('event_edition_id', $editionId)
            ->where('bib_number', $bib)
            ->with(['athlete.user.athleteProfile', 'eventEdition.event', 'eventRace'])
            ->get()
            ->flatMap(function (EventParticipant $participant) use ($viewer, $visibility) {
                $owner = $participant->athlete?->user;
                $isOwner = $viewer !== null && $owner !== null && $owner->id === $viewer->id;
                $profile = $owner?->athleteProfile;

                if (! $isOwner && ($profile === null || ! $visibility->canViewProfile($profile, $viewer))) {
                    return [];
                }

                return $participant->media()
                    ->where('type', AthleteEventMediaType::Image)
                    ->when(! $isOwner, fn ($q) => $q->where('is_public', true))
                    ->orderBy('sort_order')
                    ->limit(self::MAX_RESULTS)
                    ->get()
                    ->map(fn (AthleteEventMedia $media) => $this->athletePhoto($media, $participant));
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function purchased(User $viewer): array
    {
        return PhotoSale::query()
            ->where('buyer_user_id', $viewer->id)
            ->with(['photo.eventEdition.event', 'photo.photographer'])
            ->latest('id')
            ->limit(self::MAX_RESULTS)
            ->get()
            ->filter(fn (PhotoSale $sale) => $sale->photo !== null)
            ->map(fn (PhotoSale $sale) => [
                'uuid' => $sale->uuid,
                'thumb_url' => $sale->photo->thumbUrl(),
                'event' => $sale->photo->eventEdition?->event?->name,
                'source' => $sale->photo->photographer?->display_name,
                'download_url' => route('photos.download', $sale),
                'purchased_at' => $sale->created_at->toIso8601String(),
            ])
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
            ->map(fn (AthleteEventMedia $media) => $this->athletePhoto($media, $media->eventParticipant))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function athletePhoto(AthleteEventMedia $media, ?EventParticipant $participant): array
    {
        return [
            'uuid' => $media->uuid,
            'kind' => 'athlete',
            'url' => $media->url(),
            'thumb_url' => $media->url(),
            'width' => $media->width,
            'height' => $media->height,
            'is_public' => $media->is_public,
            'event' => $participant?->eventEdition?->event?->name,
            'race' => $participant?->eventRace?->name,
            'source' => 'Subida por el atleta',
            'price_minor' => null,
            'currency' => null,
            'owned' => true,
        ];
    }
}
