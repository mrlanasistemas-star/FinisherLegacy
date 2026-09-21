<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Actions\Athletes\EnsureAthleteForUser;
use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Api\V1\Concerns\ResolvesAuthenticatedUser;
use App\Http\Controllers\Controller;
use App\Models\EventParticipant;
use App\Queries\Athletes\GetAthleteHistory;
use App\Queries\Athletes\GetEventParticipantDetail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `GET /api/v1/me/events` and `GET /api/v1/me/history` (same controller —
 * product consolidation brief §24/§29: "no duplicar la Query") — the same
 * paginated, participation-centric GetAthleteHistory Query "Mi Perfil"
 * renders. Deliberately does NOT bundle the Athlete's full plates/medals/
 * media/owned_products/orders here anymore (brief §27-§28: "no cargar 100
 * eventos, 500 media, 100 orders... en cada GET /me/history") — those have
 * their own endpoints (/me/gear, /api/v1/orders) or per-row summary counts.
 */
class EventsController extends Controller
{
    use ApiResponses;
    use ResolvesAuthenticatedUser;

    public function index(Request $request, EnsureAthleteForUser $ensureAthlete, GetAthleteHistory $history): JsonResponse
    {
        $athlete = $ensureAthlete->handle($this->sanctumUser($request), 'me_events');
        $participations = $history->handle($athlete, [
            'from' => $request->string('from')->toString() ?: null,
            'to' => $request->string('to')->toString() ?: null,
            'event_id' => $request->integer('event_id') ?: null,
            'sport_id' => $request->integer('sport_id') ?: null,
            'event_race_id' => $request->integer('event_race_id') ?: null,
            'legacy_plate' => $request->string('legacy_plate')->toString() ?: null,
            'athlete_owned_product_id' => $request->integer('athlete_owned_product_id') ?: null,
        ], perPage: $request->integer('per_page', 25));

        return $this->respond($participations->through(GetAthleteHistory::summarize(...)));
    }

    /**
     * `GET /api/v1/me/events/{participant}` (product consolidation brief
     * §25) — same App\Queries\Athletes\GetEventParticipantDetail Web's
     * "Mi Legado" event detail renders.
     */
    public function show(EventParticipant $participant, Request $request, EnsureAthleteForUser $ensureAthlete, GetEventParticipantDetail $detail): JsonResponse
    {
        $athlete = $ensureAthlete->handle($this->sanctumUser($request), 'me_events_show');
        abort_unless($participant->athlete_id === $athlete->id, 403);

        return $this->respond($detail->handle($participant));
    }
}
