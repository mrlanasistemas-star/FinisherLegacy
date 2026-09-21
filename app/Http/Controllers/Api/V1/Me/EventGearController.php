<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Actions\Athletes\AssignOwnedProductToEvent;
use App\Actions\Athletes\EnsureAthleteForUser;
use App\Actions\Athletes\RemoveOwnedProductFromEvent;
use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Models\AthleteOwnedProduct;
use App\Models\EventGearSelection;
use App\Models\EventParticipant;
use App\Queries\Athletes\GetEventGear;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `GET/POST me/events/{participant}/gear`, `DELETE .../gear/{selection}`
 * (product consolidation brief §28) — same App\Actions\Athletes\
 * AssignOwnedProductToEvent/RemoveOwnedProductFromEvent and
 * App\Queries\Athletes\GetEventGear the Web "Mi Legado" event detail uses.
 */
class EventGearController extends Controller
{
    use ApiResponses;

    public function index(EventParticipant $participant, EnsureAthleteForUser $ensureAthlete, Request $request, GetEventGear $query): JsonResponse
    {
        $athlete = $ensureAthlete->handle($request->user(), 'me_event_gear_index');
        abort_unless($participant->athlete_id === $athlete->id, 403);

        return $this->respond($query->handle($participant)->map(fn (EventGearSelection $selection) => $this->payload($selection)));
    }

    public function store(Request $request, EventParticipant $participant, EnsureAthleteForUser $ensureAthlete, AssignOwnedProductToEvent $assign): JsonResponse
    {
        $athlete = $ensureAthlete->handle($request->user(), 'me_event_gear_store');
        abort_unless($participant->athlete_id === $athlete->id, 403);

        $data = $request->validate([
            'athlete_owned_product_uuid' => ['required', 'string'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $ownedProduct = AthleteOwnedProduct::query()->where('uuid', $data['athlete_owned_product_uuid'])->firstOrFail();
        $selection = $assign->handle($participant, $ownedProduct, $data['notes'] ?? null);

        return $this->respond($this->payload($selection), 'Equipo agregado.', status: 201);
    }

    public function destroy(EventParticipant $participant, EventGearSelection $gear, Request $request, EnsureAthleteForUser $ensureAthlete, RemoveOwnedProductFromEvent $remove): JsonResponse
    {
        $athlete = $ensureAthlete->handle($request->user(), 'me_event_gear_destroy');
        abort_unless($participant->athlete_id === $athlete->id, 403);
        abort_unless($gear->event_participant_id === $participant->id, 403);

        $remove->handle($gear);

        return $this->respond(null, 'Equipo removido.');
    }

    /** @return array<string, mixed> */
    private function payload(EventGearSelection $selection): array
    {
        $selection->loadMissing('athleteOwnedProduct');

        return [
            'uuid' => $selection->uuid,
            'athlete_owned_product_uuid' => $selection->athleteOwnedProduct->uuid,
            'product_name' => $selection->snapshot['product_name'] ?? null,
            'variant_name' => $selection->snapshot['variant_name'] ?? null,
            'asset_code' => $selection->athleteOwnedProduct->asset_code,
            'notes' => $selection->notes,
            'selected_at' => $selection->selected_at->toIso8601String(),
        ];
    }
}
