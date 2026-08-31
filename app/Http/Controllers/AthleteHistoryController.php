<?php

namespace App\Http\Controllers;

use App\Actions\Athletes\EnsureAthleteForUser;
use App\Actions\Media\DeleteAthleteEventMedia;
use App\Actions\Media\UpdateAthleteEventMediaVisibility;
use App\Actions\Media\UploadAthleteEventMedia;
use App\Exceptions\MediaLimitReachedException;
use App\Exceptions\MediaTooLargeException;
use App\Models\AthleteEventMedia;
use App\Models\AthleteOwnedProduct;
use App\Models\EventParticipant;
use App\Models\EventResultSplit;
use App\Models\Medal;
use App\Models\Plate;
use App\Queries\Athletes\GetAthleteHistory;
use App\Queries\Commerce\GetAthleteOwnedProducts;
use App\Services\Media\ResolveMediaEntitlement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * "MIS EVENTOS / MIS LEGACY PLATES / MI EQUIPO" (brief §39-§46/§94-§111) —
 * the athlete-facing side of the same App\Queries\Athletes\GetAthleteHistory
 * and App\Queries\Commerce\GetAthleteOwnedProducts the admin Athlete Detail
 * screen and the /api/v1/me/* endpoints already use. Web Controller →
 * Query/Action → Inertia, no REST round-trip (brief §62/§88-§89).
 */
class AthleteHistoryController extends Controller
{
    public function myEvents(Request $request, EnsureAthleteForUser $ensureAthlete, GetAthleteHistory $history): Response
    {
        $athlete = $ensureAthlete->handle($request->user(), 'dashboard_my_events');
        $data = $history->handle($athlete);

        return Inertia::render('dashboard/MyEvents', [
            'participations' => $data['participations']->map(fn (EventParticipant $p) => [
                'id' => $p->id,
                'event' => $p->eventEdition?->event?->name,
                'edition' => $p->eventEdition?->name,
                'race' => $p->eventRace?->name,
                'bib_number' => $p->bib_number,
                'event_date' => $p->eventEdition?->event_date?->toDateString(),
                'official_time' => $p->result?->official_time,
                'pace' => $p->result?->pace,
                'position' => $p->result?->overall_position,
                'has_plate' => $p->plates->isNotEmpty(),
                'has_medal' => $p->medals->isNotEmpty(),
                'media_count' => $p->media->count(),
            ])->values(),
        ]);
    }

    public function myEventShow(Request $request, EventParticipant $participant, EnsureAthleteForUser $ensureAthlete, ResolveMediaEntitlement $entitlement): Response
    {
        $athlete = $ensureAthlete->handle($request->user(), 'dashboard_my_event_show');
        abort_unless($participant->athlete_id === $athlete->id, 403);

        $participant->loadMissing([
            'eventEdition.event', 'eventRace', 'result.splits', 'medals', 'plates.legacyCode', 'media',
        ]);

        return Inertia::render('dashboard/MyEventShow', [
            'participant' => [
                'id' => $participant->id,
                'event' => $participant->eventEdition?->event?->name,
                'edition' => $participant->eventEdition?->name,
                'race' => $participant->eventRace?->name,
                'bib_number' => $participant->bib_number,
                'event_date' => $participant->eventEdition?->event_date?->toDateString(),
            ],
            'result' => $participant->result ? [
                'official_time' => $participant->result->official_time,
                'chip_time' => $participant->result->chip_time,
                'pace' => $participant->result->pace,
                'overall_position' => $participant->result->overall_position,
                'gender_position' => $participant->result->gender_position,
                'category_position' => $participant->result->category_position,
                'splits' => $participant->result->splits->map(fn (EventResultSplit $split) => [
                    'label' => $split->label,
                    'distance_value' => $split->distance_value,
                    'distance_unit' => $split->distance_unit,
                    'segment_time' => $split->segment_time,
                    'elapsed_time' => $split->elapsed_time,
                    'pace' => $split->pace,
                ])->values(),
            ] : null,
            'medals' => $participant->medals->map(fn (Medal $medal) => [
                'id' => $medal->id,
                'title' => $medal->title,
                'story' => $medal->story,
            ])->values(),
            'plates' => $participant->plates->map(fn (Plate $plate) => [
                'id' => $plate->id,
                'serial_number' => $plate->serial_number,
                'status' => $plate->status->value,
                'legacy_code' => $plate->legacyCode?->code,
                'engraving_display_name' => $plate->engraving_display_name,
            ])->values(),
            'media' => $participant->media->map(fn (AthleteEventMedia $media) => [
                'uuid' => $media->uuid,
                'type' => $media->type->value,
                'url' => $media->url(),
                'is_public' => $media->is_public,
            ])->values(),
            'mediaLimits' => $entitlement->limits(),
            'mediaRemaining' => $entitlement->remaining($participant),
        ]);
    }

    public function uploadMedia(Request $request, EventParticipant $participant, EnsureAthleteForUser $ensureAthlete, UploadAthleteEventMedia $upload): RedirectResponse
    {
        $athlete = $ensureAthlete->handle($request->user(), 'dashboard_media_upload');
        abort_unless($participant->athlete_id === $athlete->id, 403);

        $data = $request->validate([
            'file' => ['required', 'file', 'max:102400'],
            'is_public' => ['nullable', 'boolean'],
        ]);

        try {
            $upload->handle($athlete, $participant, $data['file'], (bool) ($data['is_public'] ?? false));
        } catch (MediaLimitReachedException|MediaTooLargeException $e) {
            throw ValidationException::withMessages(['file' => $e->getMessage()]);
        } catch (Throwable $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back();
    }

    public function updateMediaVisibility(Request $request, AthleteEventMedia $media, EnsureAthleteForUser $ensureAthlete, UpdateAthleteEventMediaVisibility $updateVisibility): RedirectResponse
    {
        $athlete = $ensureAthlete->handle($request->user(), 'dashboard_media_visibility');
        abort_unless($media->athlete_id === $athlete->id, 403);

        $data = $request->validate(['is_public' => ['required', 'boolean']]);
        $updateVisibility->handle($media, (bool) $data['is_public']);

        return back();
    }

    public function destroyMedia(Request $request, AthleteEventMedia $media, EnsureAthleteForUser $ensureAthlete, DeleteAthleteEventMedia $deleteMedia): RedirectResponse
    {
        $athlete = $ensureAthlete->handle($request->user(), 'dashboard_media_destroy');
        abort_unless($media->athlete_id === $athlete->id, 403);

        $deleteMedia->handle($media);

        return back();
    }

    public function myPlates(Request $request, EnsureAthleteForUser $ensureAthlete, GetAthleteHistory $history): Response
    {
        $athlete = $ensureAthlete->handle($request->user(), 'dashboard_my_plates');
        $data = $history->handle($athlete);

        return Inertia::render('dashboard/MyPlates', [
            'plates' => $data['plates']->map(fn (Plate $plate) => [
                'id' => $plate->id,
                'serial_number' => $plate->serial_number,
                'status' => $plate->status->value,
                'event_name' => $plate->event_name,
                'race_name' => $plate->race_name,
                'engraving_display_name' => $plate->engraving_display_name,
                'legacy_code' => $plate->legacyCode?->code,
                'produced_at' => $plate->produced_at?->toDateTimeString(),
                'delivered_at' => $plate->delivered_at?->toDateTimeString(),
            ])->values(),
        ]);
    }

    public function myGear(Request $request, EnsureAthleteForUser $ensureAthlete, GetAthleteOwnedProducts $query): Response
    {
        $athlete = $ensureAthlete->handle($request->user(), 'dashboard_my_gear');

        return Inertia::render('dashboard/MyGear', [
            'items' => $query->handle($athlete)->map(fn (AthleteOwnedProduct $owned) => [
                'uuid' => $owned->uuid,
                'product' => $owned->product->name,
                'variant' => $owned->productVariant?->name,
                'image_url' => $owned->product->image_path ? Storage::disk('public')->url($owned->product->image_path) : null,
                'status' => $owned->status->value,
                'acquired_at' => $owned->acquired_at->toDateString(),
                'asset_code' => $owned->asset_code,
            ])->values(),
        ]);
    }
}
