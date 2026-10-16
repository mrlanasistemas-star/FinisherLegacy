<?php

namespace App\Http\Controllers;

use App\Actions\Athletes\AssignOwnedProductToEvent;
use App\Actions\Athletes\EnsureAthleteForUser;
use App\Actions\Athletes\RemoveOwnedProductFromEvent;
use App\Actions\Media\DeleteAthleteEventMedia;
use App\Actions\Media\UpdateAthleteEventMediaVisibility;
use App\Actions\Media\UploadAthleteEventMedia;
use App\Enums\LegacyPlateEntitlementStatus;
use App\Exceptions\EventGearAlreadyAssignedException;
use App\Exceptions\EventGearOwnershipMismatchException;
use App\Exceptions\MediaLimitReachedException;
use App\Exceptions\MediaTooLargeException;
use App\Models\AthleteEventMedia;
use App\Models\AthleteOwnedProduct;
use App\Models\EventGearSelection;
use App\Models\EventParticipant;
use App\Models\LegacyPlateEntitlement;
use App\Queries\Athletes\GetAthleteLegado;
use App\Queries\Athletes\GetEventParticipantDetail;
use App\Queries\Commerce\GetAthleteOwnedProducts;
use App\Queries\Tracking\GetParticipantTrackingSummary;
use App\Services\Media\ResolveMediaEntitlement;
use App\Support\RaceDistanceFormatter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
    /**
     * Legacy direct-URL page (brief §3/§89: consolidated into Mi Legado,
     * not deleted) — bounded to the first 100 participations rather than
     * every one the Athlete has, matching GetAthleteHistory's now-paginated
     * shape (brief §27-§28) without adding pagination UI to a page nobody
     * reaches from navigation anymore.
     */
    /**
     * "Mis eventos" (spec §7/§134): what's ahead — registrations and
     * pre-registrations — and every race already run, one page at a time.
     */
    public function myEvents(Request $request, EnsureAthleteForUser $ensureAthlete, GetAthleteLegado $legado): Response
    {
        $athlete = $ensureAthlete->handle($request->user(), 'dashboard_my_events');

        return Inertia::render('dashboard/MyEvents', [
            'upcoming' => [
                ...$legado->upcoming($athlete),
                ...$legado->preregistrations($request->user()),
            ],
            'past' => $legado->past($athlete),
            'totals' => $legado->totals($athlete),
        ]);
    }

    /**
     * The old per-event page is now the event story at
     * /dashboard/legado/{participant}; bookmarks keep working.
     */
    public function myEventShow(Request $request, EventParticipant $participant, EnsureAthleteForUser $ensureAthlete): RedirectResponse
    {
        $athlete = $ensureAthlete->handle($request->user(), 'dashboard_my_event_show');
        abort_unless($participant->athlete_id === $athlete->id, 403);

        return to_route('dashboard.legado.show', $participant, 301);
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
    public function legadoShow(
        Request $request,
        EventParticipant $participant,
        EnsureAthleteForUser $ensureAthlete,
        ResolveMediaEntitlement $entitlement,
        GetEventParticipantDetail $detail,
        GetAthleteOwnedProducts $ownedProducts,
    ): Response {
        $athlete = $ensureAthlete->handle($request->user(), 'dashboard_legado_show');
        abort_unless($participant->athlete_id === $athlete->id, 403);

        $payload = $detail->handle($participant);
        $assignedOwnedProductIds = $participant->gearSelections()->pluck('athlete_owned_product_id');
        $availableGear = $ownedProducts->handle($athlete)
            ->reject(fn (AthleteOwnedProduct $owned) => $assignedOwnedProductIds->contains($owned->id));

        $edition = $participant->eventEdition;

        return Inertia::render('dashboard/LegadoShow', [
            ...$payload,
            // Context for the "event story" header — the race in its place
            // and time, not just a row of numbers.
            'story' => [
                'event_slug' => $edition?->event?->slug,
                'distance' => RaceDistanceFormatter::format($participant->eventRace),
                'city' => $edition?->city,
                'state' => $edition?->state,
                'year' => $edition?->event_date?->year,
                'upcoming' => $edition?->event_date?->isFuture() ?? false,
                'cover_url' => $edition?->event?->cover_path ? asset('storage/'.$edition->event->cover_path) : null,
                'category' => $participant->category,
            ],
            'mediaLimits' => $entitlement->limits($participant),
            'mediaRemaining' => $entitlement->remaining($participant),
            'availableGear' => $availableGear->map(fn (AthleteOwnedProduct $owned) => [
                'uuid' => $owned->uuid,
                'product' => $owned->product->name,
                'variant' => $owned->productVariant?->name,
            ])->values(),
            // "Seguimiento en vivo" card (live tracking, SPEC §75-§84).
            'tracking' => fn () => app(GetParticipantTrackingSummary::class)->handle($participant),
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

    public function storeGear(Request $request, EventParticipant $participant, EnsureAthleteForUser $ensureAthlete, AssignOwnedProductToEvent $assign): RedirectResponse
    {
        $athlete = $ensureAthlete->handle($request->user(), 'dashboard_gear_store');
        abort_unless($participant->athlete_id === $athlete->id, 403);

        $data = $request->validate([
            'athlete_owned_product_uuid' => ['required', 'string'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $ownedProduct = AthleteOwnedProduct::query()->where('uuid', $data['athlete_owned_product_uuid'])->firstOrFail();

        try {
            $assign->handle($participant, $ownedProduct, $data['notes'] ?? null);
        } catch (EventGearOwnershipMismatchException|EventGearAlreadyAssignedException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back();
    }

    public function destroyGear(Request $request, EventParticipant $participant, EventGearSelection $gear, EnsureAthleteForUser $ensureAthlete, RemoveOwnedProductFromEvent $remove): RedirectResponse
    {
        $athlete = $ensureAthlete->handle($request->user(), 'dashboard_gear_destroy');
        abort_unless($participant->athlete_id === $athlete->id, 403);
        abort_unless($gear->event_participant_id === $participant->id, 403);

        $remove->handle($gear);

        return back();
    }

    /**
     * "Mis Legacy Plates" — every commercial right to a Legacy Plate this
     * athlete has, paid presales with no result yet included (brief §12-
     * §13: "debe mostrar también las preventas todavía sin resultado").
     * Reads App\Models\LegacyPlateEntitlement directly rather than only
     * App\Models\Plate — a paid-but-not-yet-produced presale has no Plate
     * row at all, so GetAthleteHistory's 'plates' key (Plate-only) would
     * silently drop it.
     */
    public function myPlates(Request $request, EnsureAthleteForUser $ensureAthlete): Response
    {
        $athlete = $ensureAthlete->handle($request->user(), 'dashboard_my_plates');

        $entitlements = LegacyPlateEntitlement::query()
            ->where('athlete_id', $athlete->id)
            ->where('status', '!=', LegacyPlateEntitlementStatus::Cancelled)
            ->with(['eventEdition.event', 'legacyPlateModel', 'plate.legacyCode', 'eventParticipant.eventRace'])
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('dashboard/MyPlates', [
            'plates' => $entitlements->map(fn (LegacyPlateEntitlement $entitlement) => [
                'id' => $entitlement->id,
                'presale_status' => $entitlement->status->value,
                'event_name' => $entitlement->eventEdition?->event?->name,
                'edition_name' => $entitlement->eventEdition?->name,
                'race_name' => $entitlement->eventParticipant?->eventRace?->name,
                'model_name' => $entitlement->legacyPlateModel?->name,
                'price_type' => $entitlement->price_type,
                'paid_at' => $entitlement->paid_at?->toDateTimeString(),
                'plate' => $entitlement->plate ? [
                    'id' => $entitlement->plate->id,
                    'serial_number' => $entitlement->plate->serial_number,
                    'engraving_display_name' => $entitlement->plate->engraving_display_name,
                    'legacy_code' => $entitlement->plate->legacyCode?->code,
                    'produced_at' => $entitlement->plate->produced_at?->toDateTimeString(),
                    'delivered_at' => $entitlement->plate->delivered_at?->toDateTimeString(),
                ] : null,
            ])->values(),
        ]);
    }

    // "Mi equipo" (dashboard.my-gear) moved to App\Http\Controllers\Nfc\MyGearController.
}
