<?php

namespace App\Http\Controllers\Api\V1\Tracking;

use App\Actions\Tracking\CreateTrackingShare;
use App\Actions\Tracking\IngestTrackingPoints;
use App\Actions\Tracking\RevokeTrackingShare;
use App\Actions\Tracking\StartTrackingSession;
use App\Actions\Tracking\StopTrackingSession;
use App\Enums\TrackingSessionStatus;
use App\Enums\TrackingSource;
use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Api\V1\Concerns\ResolvesAuthenticatedUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IngestTrackingPointsRequest;
use App\Models\EventParticipant;
use App\Models\TrackingSession;
use App\Models\TrackingShare;
use App\Queries\Tracking\GetParticipantTrackingSummary;
use App\Support\Tracking\TrackingSessionPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Live tracking API (SPEC §77-§82, AGENT_BRIEF tracking contract). Start
 * and stop are mobile-only by design: the web never starts GPS.
 */
class TrackingSessionController extends Controller
{
    use ApiResponses;
    use ResolvesAuthenticatedUser;

    /** `GET me/events/{participant}/tracking` — card summary (additive). */
    public function summary(Request $request, EventParticipant $participant, GetParticipantTrackingSummary $summary): JsonResponse
    {
        Gate::forUser($this->sanctumUser($request))->authorize('create', [TrackingSession::class, $participant]);

        return $this->respond($summary->handle($participant));
    }

    /** `POST me/events/{participant}/tracking/sessions` */
    public function store(Request $request, EventParticipant $participant, StartTrackingSession $start): JsonResponse
    {
        Gate::forUser($this->sanctumUser($request))->authorize('create', [TrackingSession::class, $participant]);

        $request->validate(['source' => ['nullable', 'string', 'in:'.TrackingSource::MobileGps->value]]);

        $result = $start->handle($participant, TrackingSource::MobileGps);

        return $this->respond(
            TrackingSessionPresenter::present($result['session']),
            $result['created'] ? 'Seguimiento iniciado.' : 'El seguimiento ya estaba activo.',
            status: $result['created'] ? 201 : 200,
        );
    }

    /**
     * `GET me/tracking/sessions/active` — the caller's running sessions
     * (mobile Home live banner). Empty list when none.
     */
    public function active(Request $request): JsonResponse
    {
        $athleteId = $this->sanctumUser($request)->athlete()->value('id');

        $sessions = $athleteId === null ? collect() : TrackingSession::query()
            ->where('status', TrackingSessionStatus::Active)
            ->whereHas('eventParticipant', fn ($q) => $q->where('athlete_id', $athleteId))
            ->with('eventParticipant.eventEdition.event:id,name')
            ->latest('started_at')
            ->limit(10)
            ->get();

        return $this->respond($sessions->map(fn (TrackingSession $s) => [
            'uuid' => $s->uuid,
            'event_participant_id' => $s->event_participant_id,
            'event_name' => $s->eventParticipant->eventEdition?->event->name ?? $s->eventParticipant->eventEdition?->name,
            'source' => $s->source->value,
            'started_at' => $s->started_at->toIso8601String(),
            'distance_m' => $s->distance_m,
            'last_point_at' => $s->last_point_at?->toIso8601String(),
        ])->values());
    }

    /**
     * `GET tracking/sessions/{session}/shares` — owner only. Shares of the
     * session plus participation-wide ones (both show this session). The
     * plaintext token is never stored, so `url` is always null here.
     */
    public function shares(Request $request, TrackingSession $session): JsonResponse
    {
        Gate::forUser($this->sanctumUser($request))->authorize('share', $session);

        $shares = TrackingShare::query()
            ->where('event_participant_id', $session->event_participant_id)
            ->where(fn ($q) => $q->whereNull('tracking_session_id')->orWhere('tracking_session_id', $session->id))
            ->latest('id')
            ->limit(50)
            ->get();

        return $this->respond($shares->map(fn (TrackingShare $share) => [
            'uuid' => $share->uuid,
            'url' => null,
            'expires_at' => $share->expires_at->toIso8601String(),
            'revoked_at' => $share->revoked_at?->toIso8601String(),
            'active' => $share->isUsable(),
            'last_viewed_at' => $share->last_viewed_at?->toIso8601String(),
            'created_at' => $share->created_at?->toIso8601String(),
        ])->values());
    }

    /** `GET tracking/sessions/{session}` — owner only. */
    public function show(Request $request, TrackingSession $session): JsonResponse
    {
        Gate::forUser($this->sanctumUser($request))->authorize('view', $session);

        return $this->respond(TrackingSessionPresenter::present($session));
    }

    /**
     * `POST tracking/sessions/{session}/points` — authorized in the Form
     * Request: the session owner (Sanctum user token) or the session's
     * paired device (`X-Tracking-Device-Token`).
     */
    public function points(IngestTrackingPointsRequest $request, TrackingSession $session, IngestTrackingPoints $ingest): JsonResponse
    {
        /** @var list<array{lat: float, lng: float, accuracy?: float|null, altitude?: float|null, speed?: float|null, battery?: int|null, recorded_at: string}> $points */
        $points = array_values($request->validated('points'));

        $result = $ingest->handle($session, $points);

        return $this->respond($result, status: 202);
    }

    /** `POST tracking/sessions/{session}/stop` */
    public function stop(Request $request, TrackingSession $session, StopTrackingSession $stop): JsonResponse
    {
        Gate::forUser($this->sanctumUser($request))->authorize('update', $session);

        return $this->respond(TrackingSessionPresenter::present($stop->handle($session)), 'Seguimiento detenido.');
    }

    /** `POST tracking/sessions/{session}/shares` */
    public function share(Request $request, TrackingSession $session, CreateTrackingShare $create): JsonResponse
    {
        $user = $this->sanctumUser($request);
        Gate::forUser($user)->authorize('share', $session);

        $data = $request->validate([
            'expires_in_hours' => ['nullable', 'integer', 'min:1', 'max:'.(int) config('tracking.share_max_ttl_hours', 336)],
        ]);

        $result = $create->handle(
            $session->eventParticipant()->firstOrFail(),
            $user,
            $session,
            isset($data['expires_in_hours']) ? (int) $data['expires_in_hours'] : null,
        );

        return $this->respond([
            'uuid' => $result['share']->uuid,
            'token' => $result['token'],
            'url' => $result['url'],
            'expires_at' => $result['share']->expires_at->toIso8601String(),
        ], 'Enlace de seguimiento creado.', status: 201);
    }

    /** `DELETE tracking/shares/{share}` */
    public function revokeShare(Request $request, TrackingShare $share, RevokeTrackingShare $revoke): Response
    {
        Gate::forUser($this->sanctumUser($request))->authorize('delete', $share);

        $revoke->handle($share);

        return response()->noContent();
    }
}
