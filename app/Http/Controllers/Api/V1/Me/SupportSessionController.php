<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Actions\Athletes\EnsureAthleteForUser;
use App\Actions\Support\CreateAthleteSupportSession;
use App\Actions\Support\GetTriggeredSupportMessages;
use App\Actions\Support\MarkSupportMessageConsumed;
use App\Enums\SupportActivityType;
use App\Enums\SupportMessageType;
use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Api\V1\Concerns\ResolvesAuthenticatedUser;
use App\Http\Controllers\Controller;
use App\Models\AthleteSupportMessage;
use App\Models\AthleteSupportSession;
use App\Models\EventParticipant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `/api/v1/me/support-sessions*` (product consolidation brief §33/§65) —
 * wraps the same App\Actions\Support\* Actions the Web "Mi equipo de
 * apoyo" flow already uses, never a second Support implementation. No app
 * code lives here — just the manifest/triggered/consumed surface a future
 * mobile client needs.
 */
class SupportSessionController extends Controller
{
    use ApiResponses;
    use ResolvesAuthenticatedUser;

    public function index(Request $request, EnsureAthleteForUser $ensureAthlete): JsonResponse
    {
        $athlete = $ensureAthlete->handle($this->sanctumUser($request), 'api_support_sessions_index');

        return $this->respond($athlete->supportSessions()->latest()->get()->map($this->summary(...)));
    }

    public function store(Request $request, EnsureAthleteForUser $ensureAthlete, CreateAthleteSupportSession $create): JsonResponse
    {
        $athlete = $ensureAthlete->handle($this->sanctumUser($request), 'api_support_sessions_store');

        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'event_participant_id' => ['nullable', 'integer', 'exists:event_participants,id'],
            'target_distance_meters' => ['nullable', 'integer', 'min:1'],
        ]);

        $eventParticipant = isset($data['event_participant_id'])
            ? EventParticipant::query()->whereKey($data['event_participant_id'])->firstOrFail()
            : null;
        abort_if($eventParticipant !== null && $eventParticipant->athlete_id !== $athlete->id, 403);

        $session = $create->handle(
            athlete: $athlete,
            title: $data['title'],
            activityType: $eventParticipant !== null ? SupportActivityType::Event : SupportActivityType::Free,
            eventParticipant: $eventParticipant,
            targetDistanceMeters: $data['target_distance_meters'] ?? null,
        );

        return $this->respond($this->summary($session), 'Sesión de apoyo creada.', status: 201);
    }

    public function show(Request $request, AthleteSupportSession $supportSession, EnsureAthleteForUser $ensureAthlete): JsonResponse
    {
        $athlete = $ensureAthlete->handle($this->sanctumUser($request), 'api_support_sessions_show');
        abort_unless($supportSession->athlete_id === $athlete->id, 403);

        $supportSession->loadMissing('messages.contributor');

        return $this->respond([
            ...$this->summary($supportSession),
            'messages' => $supportSession->messages->sortByDesc('created_at')->map(fn (AthleteSupportMessage $m) => [
                'id' => $m->id,
                'type' => $m->type->value,
                'message_text' => $m->is_surprise ? null : $m->message_text,
                'audio_url' => ! $m->is_surprise && $m->type === SupportMessageType::Audio ? $m->signedAudioUrl() : null,
                'contributor_name' => $m->contributor?->display_name,
                'status' => $m->status->value,
                'is_surprise' => $m->is_surprise,
                'created_at' => $m->created_at->toIso8601String(),
            ])->values(),
        ]);
    }

    /**
     * The compact config payload a mobile client needs to initialize its
     * own message polling — no message content, see triggered() for that.
     */
    public function manifest(Request $request, AthleteSupportSession $supportSession, EnsureAthleteForUser $ensureAthlete): JsonResponse
    {
        $athlete = $ensureAthlete->handle($this->sanctumUser($request), 'api_support_sessions_manifest');
        abort_unless($supportSession->athlete_id === $athlete->id, 403);

        return $this->respond($this->summary($supportSession));
    }

    public function triggered(Request $request, AthleteSupportSession $supportSession, EnsureAthleteForUser $ensureAthlete, GetTriggeredSupportMessages $query): JsonResponse
    {
        $athlete = $ensureAthlete->handle($this->sanctumUser($request), 'api_support_sessions_triggered');
        abort_unless($supportSession->athlete_id === $athlete->id, 403);

        $data = $request->validate([
            'distance_meters' => ['required', 'integer', 'min:0'],
            'consumed_ids' => ['nullable', 'array'],
            'consumed_ids.*' => ['integer'],
        ]);

        $messages = $query->handle($supportSession, $data['distance_meters'], $data['consumed_ids'] ?? []);

        return $this->respond($messages->map(fn (AthleteSupportMessage $m) => [
            'id' => $m->id,
            'type' => $m->type->value,
            'message_text' => $m->is_surprise ? null : $m->message_text,
            'audio_url' => ! $m->is_surprise && $m->type === SupportMessageType::Audio ? $m->signedAudioUrl() : null,
            'contributor_name' => $m->contributor?->display_name,
            'is_surprise' => $m->is_surprise,
        ])->values());
    }

    public function markConsumed(Request $request, AthleteSupportMessage $message, EnsureAthleteForUser $ensureAthlete, MarkSupportMessageConsumed $markConsumed): JsonResponse
    {
        $athlete = $ensureAthlete->handle($this->sanctumUser($request), 'api_support_message_consumed');
        $message->loadMissing('session');
        abort_unless($message->session->athlete_id === $athlete->id, 403);

        $markConsumed->handle($message);

        return $this->respond(null, 'Mensaje marcado como consumido.');
    }

    /** @return array<string, mixed> */
    private function summary(AthleteSupportSession $session): array
    {
        return [
            'id' => $session->id,
            'title' => $session->title,
            'public_code' => $session->public_code,
            'public_url' => url("/support/{$session->public_code}"),
            'qr_url' => url("/support/{$session->public_code}/qr.svg"),
            'status' => $session->status->value,
            'target_distance_meters' => $session->target_distance_meters,
            'allow_text' => $session->allow_text,
            'allow_audio' => $session->allow_audio,
            'accepting' => $session->isAcceptingMessages(),
        ];
    }
}
