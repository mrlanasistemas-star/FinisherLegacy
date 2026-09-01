<?php

namespace App\Http\Controllers;

use App\Actions\Athletes\EnsureAthleteForUser;
use App\Actions\Media\DeleteAthleteEventMedia;
use App\Actions\Media\UpdateAthleteEventMediaVisibility;
use App\Actions\Media\UploadAthleteEventMedia;
use App\Enums\SupportMessageType;
use App\Exceptions\MediaLimitReachedException;
use App\Exceptions\MediaTooLargeException;
use App\Models\AthleteEventMedia;
use App\Models\AthleteOwnedProduct;
use App\Models\AthleteSupportMessage;
use App\Models\EventParticipant;
use App\Models\EventResultSplit;
use App\Models\LegacyPlateEntitlement;
use App\Models\LegacyPlateModelField;
use App\Models\Medal;
use App\Models\MedalImage;
use App\Models\OrderItem;
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

    /**
     * "MI LEGADO" event detail (product UX consolidation brief §5-§6): one
     * full-experience screen per participation — result, medal, Legacy
     * Plate (with its real model geometry for LegacyPlateViewer.vue),
     * media, and what was bought for this event — instead of the three
     * separate screens ("Mis eventos" / "Mis medallas" / "Mis Legacy
     * Plates") this replaces. Deliberately reuses the exact same
     * participant load as myEventShow() rather than a second query shape;
     * this is that same screen with the Legacy Plate viewer and purchases
     * folded in, not a competing read model.
     */
    public function legadoShow(Request $request, EventParticipant $participant, EnsureAthleteForUser $ensureAthlete, ResolveMediaEntitlement $entitlement): Response
    {
        $athlete = $ensureAthlete->handle($request->user(), 'dashboard_legado_show');
        abort_unless($participant->athlete_id === $athlete->id, 403);

        $participant->loadMissing([
            'eventEdition.event', 'eventRace', 'result.splits', 'medals.images',
            'plates.legacyCode', 'plates.legacyPlateModel.fields', 'media',
        ]);

        $supportSession = $participant->supportSessions()->with('messages.contributor')->latest()->first();
        $plate = $participant->plates->first();
        $entitlementRecord = $plate !== null ? null : LegacyPlateEntitlement::query()
            ->where('event_participant_id', $participant->id)
            ->with('legacyPlateModel.fields')
            ->latest('created_at')
            ->first();

        // Not $plate?->legacyPlateModel ?? $entitlementRecord?->legacyPlateModel:
        // PHPStan's flow analysis over-narrows $plate to "never null" when
        // that expression is read back through $entitlementRecord (whose
        // type is itself conditional on $plate's nullability from the
        // ternary above) — an explicit if/elseif keeps the two variables'
        // types independent instead of cross-linked.
        $plateModel = null;
        if ($plate !== null) {
            $plateModel = $plate->legacyPlateModel;
        } elseif ($entitlementRecord !== null) {
            $plateModel = $entitlementRecord->legacyPlateModel;
        }

        $purchases = $participant->eventEdition === null ? collect() : OrderItem::query()
            ->whereHas('order', fn ($q) => $q
                ->where('athlete_id', $athlete->id)
                ->where('event_edition_id', $participant->eventEdition->id))
            ->with(['product', 'order'])
            ->get();

        return Inertia::render('dashboard/LegadoShow', [
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
                'image_url' => $medal->images->sortBy('sort_order')->first() instanceof MedalImage
                    ? Storage::disk('public')->url($medal->images->sortBy('sort_order')->first()->optimized_path ?? $medal->images->sortBy('sort_order')->first()->original_path)
                    : null,
            ])->values(),
            'legacyPlate' => $plate === null && $entitlementRecord === null ? null : [
                'status' => $plate?->status->value ?? $entitlementRecord?->status->value,
                'serial_number' => $plate?->serial_number,
                'legacy_code' => $plate?->legacyCode?->code,
                'model' => $plateModel === null ? null : [
                    'name' => $plateModel->name,
                    'slug' => $plateModel->slug,
                    'width_mm' => (float) $plateModel->width_mm,
                    'height_mm' => (float) $plateModel->height_mm,
                    'engraving_area' => $plateModel->engraving_area,
                    'preview_image_url' => $plateModel->preview_image_path ? Storage::disk('public')->url($plateModel->preview_image_path) : null,
                    'fields' => $plateModel->fields->map(fn (LegacyPlateModelField $field) => [
                        'field_key' => $field->field_key->value,
                        'x' => (float) $field->x,
                        'y' => (float) $field->y,
                        'width' => (float) $field->width,
                        'height' => (float) $field->height,
                        'font_size' => $field->font_size !== null ? (float) $field->font_size : null,
                        'alignment' => $field->alignment->value,
                        'visible' => $field->visible,
                    ])->values(),
                ],
                // Same reasoning as $plateModel above: $plate is read
                // through an if/null check instead of ?? so PHPStan doesn't
                // cross-link its nullability with $entitlementRecord's.
                'personalization' => $plate !== null ? [
                    'athlete_name' => $plate->engraving_display_name,
                    'race_label' => $plate->race_name,
                    'official_time' => $plate->official_time,
                    'pace' => $plate->pace,
                ] : [
                    'athlete_name' => $athlete->full_name,
                    'race_label' => $participant->eventRace?->name,
                    'official_time' => $participant->result?->official_time,
                    'pace' => $participant->result?->pace,
                ],
            ],
            'plates' => $participant->plates->map(fn (Plate $p) => [
                'id' => $p->id,
                'serial_number' => $p->serial_number,
                'status' => $p->status->value,
                'legacy_code' => $p->legacyCode?->code,
                'engraving_display_name' => $p->engraving_display_name,
            ])->values(),
            'media' => $participant->media->map(fn (AthleteEventMedia $media) => [
                'uuid' => $media->uuid,
                'type' => $media->type->value,
                'url' => $media->url(),
                'is_public' => $media->is_public,
            ])->values(),
            'mediaLimits' => $entitlement->limits(),
            'mediaRemaining' => $entitlement->remaining($participant),
            'purchases' => $purchases->map(fn (OrderItem $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'quantity' => $item->quantity,
                'line_total_minor' => $item->line_total_minor,
                'currency' => $item->currency,
                'order_uuid' => $item->order->uuid,
                'payment_status' => $item->order->payment_status->value,
            ])->values(),
            'supportSession' => $supportSession === null ? null : [
                'public_code' => $supportSession->public_code,
                'title' => $supportSession->title,
                'public_url' => url("/support/{$supportSession->public_code}"),
                'qr_url' => url("/support/{$supportSession->public_code}/qr.svg"),
                'status' => $supportSession->status->value,
                // A surprise message (brief §32) withholds its own content
                // from the athlete even here — moderation stays possible
                // (approve/reject "blind"), the content just isn't shown.
                'messages' => $supportSession->messages->sortByDesc('created_at')->map(fn (AthleteSupportMessage $m) => [
                    'id' => $m->id,
                    'type' => $m->type->value,
                    'message_text' => $m->is_surprise ? null : $m->message_text,
                    'audio_url' => ! $m->is_surprise && $m->type === SupportMessageType::Audio ? $m->signedAudioUrl() : null,
                    'contributor_name' => $m->contributor?->display_name,
                    'status' => $m->status->value,
                    'is_surprise' => $m->is_surprise,
                    'created_at' => $m->created_at->diffForHumans(),
                ])->values(),
            ],
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
