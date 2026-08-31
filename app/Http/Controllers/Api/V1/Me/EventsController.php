<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Actions\Athletes\EnsureAthleteForUser;
use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Models\EventParticipant;
use App\Models\Plate;
use App\Queries\Athletes\GetAthleteHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `GET /api/v1/me/events` — reuses the exact same GetAthleteHistory Query
 * as the admin Athlete Detail screen (brief §116/§173): "1 Athlete, N
 * events" holds for every consumer, never a second read model.
 */
class EventsController extends Controller
{
    use ApiResponses;

    public function index(Request $request, EnsureAthleteForUser $ensureAthlete, GetAthleteHistory $history): JsonResponse
    {
        $athlete = $ensureAthlete->handle($request->user(), 'me_events');
        $data = $history->handle($athlete);

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
        ]);
    }
}
