<?php

namespace App\Http\Controllers\Api\V1\Family;

use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Api\V1\Concerns\ResolvesAuthenticatedUser;
use App\Http\Controllers\Controller;
use App\Models\AthleteFamilyLink;
use App\Models\TrackingShare;
use App\Queries\Tracking\GetLiveTrackingSnapshot;
use App\Support\Family\FamilyPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Siguiendo a…" — the live shares a family member receives
 * (docs/renovacion/familia.md). Access is decided in the QUERY, not the UI:
 * a row is only ever read when it is a `family` share, addressed to an
 * ACCEPTED link whose contact is the caller. Everything else is 404;
 * a share that ran its course (session stopped, time up) is 410.
 */
class FamilyFollowingController extends Controller
{
    use ApiResponses;
    use ResolvesAuthenticatedUser;

    /** `GET family/following` — who is sharing with me right now. */
    public function index(Request $request): JsonResponse
    {
        $user = $this->sanctumUser($request);

        $shares = $this->addressedTo($user->id)
            ->usable()
            ->whereHas('trackingSession', fn ($q) => $q->where('status', 'active'))
            ->with([
                'familyLink.athleteUser.athleteProfile',
                'trackingSession:id,event_participant_id,training_workout_id,status,started_at,last_point_at,distance_m',
                'trackingSession.trainingWorkout.sport:id,name',
                'eventParticipant.eventEdition.event:id,name',
            ])
            ->latest('id')
            ->limit(50)
            ->get();

        $pausedAfter = (int) config('tracking.paused_after_seconds', 180);

        return $this->respond($shares->map(function (TrackingShare $share) use ($pausedAfter) {
            $session = $share->trackingSession;
            $lastAt = $session?->last_point_at;

            return [
                'uuid' => $share->uuid,
                'athlete' => FamilyPresenter::person($share->familyLink?->athleteUser),
                'event_name' => $share->eventParticipant->eventEdition->event->name ?? $share->eventParticipant->eventEdition->name
                    // A followed live training workout: its activity instead of an event.
                    ?? (($sport = $session?->trainingWorkout?->sport?->name) !== null ? __('Entrenamiento · :sport', ['sport' => __($sport)]) : null),
                'status' => $lastAt !== null && $lastAt->lt(now()->subSeconds($pausedAfter)) ? 'paused' : 'active',
                'started_at' => $session?->started_at->toIso8601String(),
                'last_update_at' => $lastAt?->toIso8601String(),
                'distance_m' => $session->distance_m ?? 0,
                'expires_at' => $share->expires_at->toIso8601String(),
                'ends_with_session' => $share->ends_with_session,
            ];
        })->values());
    }

    /** `GET family/following/{share}` — the live snapshot (position + recent path). */
    public function show(Request $request, string $share, GetLiveTrackingSnapshot $snapshot): JsonResponse
    {
        $user = $this->sanctumUser($request);

        $row = $this->addressedTo($user->id)->where('uuid', $share)->first();

        if ($row === null || $row->unavailableReason() === 'revoked') {
            return $this->error(404, 'NOT_FOUND', __('Este seguimiento ya no está disponible.'));
        }

        if ($row->unavailableReason() !== null) {
            return $this->error(410, 'TRACKING_ENDED', __('El seguimiento terminó.'));
        }

        if ($row->last_viewed_at === null || $row->last_viewed_at->lt(now()->subMinute())) {
            $row->forceFill(['last_viewed_at' => now()])->saveQuietly();
        }

        return $this->respond([
            ...$snapshot->handle($row),
            'share' => [
                'uuid' => $row->uuid,
                'ends_with_session' => $row->ends_with_session,
            ],
        ]);
    }

    /**
     * Family shares the caller may read at all: addressed to one of their
     * accepted links. Usability (revoked / ended / expired) is checked on top.
     *
     * @return Builder<TrackingShare>
     */
    private function addressedTo(int $userId): Builder
    {
        return TrackingShare::query()
            ->where('audience', TrackingShare::AUDIENCE_FAMILY)
            ->whereIn('athlete_family_link_id', AthleteFamilyLink::query()->where('contact_user_id', $userId)->accepted()->select('id'));
    }

    private function error(int $status, string $code, string $message): JsonResponse
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message, 'details' => (object) []]], $status);
    }
}
