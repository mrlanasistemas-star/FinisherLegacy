<?php

namespace App\Http\Controllers\Api\V1\Tracking;

use App\Actions\Tracking\EndTrackingShares;
use App\Actions\Tracking\ShareTrackingSession;
use App\Enums\TrackingSessionStatus;
use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Api\V1\Concerns\ResolvesAuthenticatedUser;
use App\Http\Controllers\Controller;
use App\Models\AthleteFamilyLink;
use App\Models\TrackingSession;
use App\Models\TrackingShare;
use App\Models\User;
use App\Support\Family\FamilyPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * The athlete's control over who sees their live location
 * (docs/renovacion/familia.md): what is shared right now, share with
 * family / a link for a chosen time, and "Dejar de compartir" at once.
 * Owner-only (TrackingSessionPolicy::share).
 */
class TrackingSharingController extends Controller
{
    use ApiResponses;
    use ResolvesAuthenticatedUser;

    /**
     * `GET me/tracking/sharing` — my running sessions with their open
     * shares, plus my accepted family (the people I can pick).
     */
    public function overview(Request $request): JsonResponse
    {
        $user = $this->sanctumUser($request);
        $athleteId = $user->athlete()->value('id');

        $sessions = $athleteId === null ? collect() : TrackingSession::query()
            ->where('status', TrackingSessionStatus::Active)
            ->whereHas('eventParticipant', fn ($q) => $q->where('athlete_id', $athleteId))
            ->with('eventParticipant.eventEdition.event:id,name')
            ->latest('started_at')
            ->limit(5)
            ->get();

        return $this->respond([
            'sessions' => $sessions->map(fn (TrackingSession $s) => $this->sessionState($s))->values(),
            'family' => $this->acceptedFamily($user),
        ]);
    }

    /** `GET tracking/sessions/{session}/sharing` — the same, for one session. */
    public function show(Request $request, TrackingSession $session): JsonResponse
    {
        $user = $this->sanctumUser($request);
        Gate::forUser($user)->authorize('share', $session);

        return $this->respond([
            ...$this->sessionState($session->load('eventParticipant.eventEdition.event:id,name')),
            'family' => $this->acceptedFamily($user),
        ]);
    }

    /**
     * `POST tracking/sessions/{session}/sharing`
     * `{family: all|selected|none, family_link_ids?: uuid[], public_link?: bool, duration: until_stop|2h|6h|24h}`
     */
    public function store(Request $request, TrackingSession $session, ShareTrackingSession $share): JsonResponse
    {
        $user = $this->sanctumUser($request);
        Gate::forUser($user)->authorize('share', $session);

        $data = $request->validate([
            'family' => ['required', Rule::in(['all', 'selected', 'none'])],
            'family_link_ids' => ['array', 'max:30', Rule::requiredIf($request->input('family') === 'selected')],
            'family_link_ids.*' => ['uuid'],
            'public_link' => ['boolean'],
            'duration' => ['required', Rule::in(array_keys(ShareTrackingSession::DURATIONS))],
        ]);

        $recipients = match ($data['family']) {
            'all' => 'all',
            'selected' => array_values($data['family_link_ids'] ?? []),
            default => [],
        };

        $result = $share->handle($session, $user, $recipients, (bool) ($data['public_link'] ?? false), $data['duration']);

        return $this->respond([
            ...$this->sessionState($session->load('eventParticipant.eventEdition.event:id,name')),
            // The public link's url exists only in this response (token hashed).
            'link' => $result['link'] !== null ? [
                'uuid' => $result['link']['share']->uuid,
                'url' => $result['link']['url'],
                'expires_at' => $result['link']['share']->expires_at->toIso8601String(),
            ] : null,
        ], __('Ubicación compartida.'), status: 201);
    }

    /**
     * `DELETE tracking/sessions/{session}/sharing` — "Dejar de compartir
     * ahora": every open share that shows this session (family, links of
     * the session and participation-wide links) is revoked at once. The
     * GPS session itself keeps running.
     */
    public function destroy(Request $request, TrackingSession $session, EndTrackingShares $end): JsonResponse
    {
        Gate::forUser($this->sanctumUser($request))->authorize('share', $session);

        $count = $end->revoke(TrackingShare::query()
            ->where('event_participant_id', $session->event_participant_id)
            ->where(fn ($q) => $q->whereNull('tracking_session_id')->orWhere('tracking_session_id', $session->id)));

        return $this->respond(['revoked' => $count], __('Dejaste de compartir tu ubicación.'));
    }

    /** @return array<string, mixed> */
    private function sessionState(TrackingSession $session): array
    {
        $shares = TrackingShare::query()
            ->usable()
            ->where('event_participant_id', $session->event_participant_id)
            ->where(fn ($q) => $q->whereNull('tracking_session_id')->orWhere('tracking_session_id', $session->id))
            ->with('familyLink.contactUser.athleteProfile')
            ->latest('id')
            ->limit(60)
            ->get();

        $edition = $session->eventParticipant?->eventEdition;

        return [
            'session' => [
                'uuid' => $session->uuid,
                'event_participant_id' => $session->event_participant_id,
                'event_name' => $edition?->event->name ?? $edition?->name,
                'status' => $session->status->value,
                'started_at' => $session->started_at->toIso8601String(),
            ],
            'shares' => $shares->map(fn (TrackingShare $s) => [
                'uuid' => $s->uuid,
                'audience' => $s->audience,
                'family_link_uuid' => $s->familyLink?->uuid,
                'label' => $s->familyLink?->label,
                'contact' => $s->audience === TrackingShare::AUDIENCE_FAMILY ? FamilyPresenter::person($s->familyLink?->contactUser) : null,
                'expires_at' => $s->expires_at->toIso8601String(),
                'ends_with_session' => $s->ends_with_session,
                'last_viewed_at' => $s->last_viewed_at?->toIso8601String(),
                'created_at' => $s->created_at?->toIso8601String(),
            ])->values(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function acceptedFamily(User $user): array
    {
        return array_values(AthleteFamilyLink::query()
            ->where('athlete_user_id', $user->id)
            ->accepted()
            ->with('contactUser.athleteProfile')
            ->orderBy('label')
            ->get()
            ->map(fn (AthleteFamilyLink $l) => FamilyPresenter::forAthlete($l))
            ->all());
    }
}
