<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Actions\Athletes\EnsureAthleteForUser;
use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Models\AthleteEventMedia;
use App\Models\AthleteOwnedProduct;
use App\Models\EventParticipant;
use App\Models\Order;
use App\Models\Plate;
use App\Queries\Athletes\GetAthleteHistory;
use App\Queries\Athletes\GetEventParticipantDetail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `GET /api/v1/me/events` and `GET /api/v1/me/history` (same controller —
 * product consolidation brief §24: "no duplicar la Query") — reuses the
 * exact same GetAthleteHistory Query as the admin Athlete Detail screen
 * (brief §116/§173): "1 Athlete, N events" holds for every consumer,
 * never a second read model.
 */
class EventsController extends Controller
{
    use ApiResponses;

    public function index(Request $request, EnsureAthleteForUser $ensureAthlete, GetAthleteHistory $history): JsonResponse
    {
        $athlete = $ensureAthlete->handle($request->user(), 'me_events');
        $data = $history->handle($athlete, [
            'from' => $request->string('from')->toString() ?: null,
            'to' => $request->string('to')->toString() ?: null,
            'event_id' => $request->integer('event_id') ?: null,
            'sport_id' => $request->integer('sport_id') ?: null,
            'event_race_id' => $request->integer('event_race_id') ?: null,
            'legacy_plate' => $request->string('legacy_plate')->toString() ?: null,
            'athlete_owned_product_id' => $request->integer('athlete_owned_product_id') ?: null,
        ]);

        return $this->respond([
            'participations' => $data['participations']->map(fn (EventParticipant $p) => [
                'id' => $p->id,
                'event' => $p->eventEdition?->event?->name,
                'edition' => $p->eventEdition?->name,
                'race' => $p->eventRace?->name,
                'bib_number' => $p->bib_number,
                'official_time' => $p->result?->official_time,
                'pace' => $p->result?->pace,
                'position' => $p->result?->overall_position,
            ])->values(),
            'plates' => $data['plates']->map(fn (Plate $plate) => [
                'id' => $plate->id,
                'serial_number' => $plate->serial_number,
                'status' => $plate->status->value,
                'legacy_code' => $plate->legacyCode?->code,
            ])->values(),
            'medals' => $data['medals']->map(fn ($medal) => [
                'id' => $medal->id,
                'event' => $medal->event?->name,
            ])->values(),
            'media' => $data['media']->map(fn (AthleteEventMedia $media) => [
                'uuid' => $media->uuid,
                'type' => $media->type->value,
                'url' => $media->url(),
                'is_public' => $media->is_public,
                'event' => $media->eventParticipant?->eventEdition?->event?->name,
            ])->values(),
            'owned_products' => $data['owned_products']->map(fn (AthleteOwnedProduct $owned) => [
                'uuid' => $owned->uuid,
                'product' => $owned->product->name,
                'variant' => $owned->productVariant?->name,
                'status' => $owned->status->value,
                'acquired_at' => $owned->acquired_at->toIso8601String(),
            ])->values(),
            'orders' => $data['orders']->map(fn (Order $order) => [
                'uuid' => $order->uuid,
                'order_number' => $order->order_number,
                'status' => $order->status->value,
                'payment_status' => $order->payment_status->value,
                'total_minor' => $order->total_minor,
                'currency' => $order->currency,
            ])->values(),
        ]);
    }

    /**
     * `GET /api/v1/me/events/{participant}` (product consolidation brief
     * §25) — same App\Queries\Athletes\GetEventParticipantDetail Web's
     * "Mi Legado" event detail renders.
     */
    public function show(EventParticipant $participant, Request $request, EnsureAthleteForUser $ensureAthlete, GetEventParticipantDetail $detail): JsonResponse
    {
        $athlete = $ensureAthlete->handle($request->user(), 'me_events_show');
        abort_unless($participant->athlete_id === $athlete->id, 403);

        return $this->respond($detail->handle($participant));
    }
}
