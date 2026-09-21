<?php

namespace App\Http\Controllers;

use App\Actions\Support\SubmitSupportMessage;
use App\Enums\SupportMessageType;
use App\Enums\SupportTriggerType;
use App\Models\AthleteSupportSession;
use App\Services\QrCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

/**
 * "/support/{publicCode}" — the public page family/friends reach without
 * an account (product UX consolidation brief §33, §23-§25). Never exposes
 * the athlete's internal id, email, or any PII beyond what the athlete
 * chose as this session's public display name.
 */
class SupportController extends Controller
{
    public function show(string $publicCode): Response
    {
        $session = AthleteSupportSession::query()
            ->where('public_code', $publicCode)
            ->with('athlete', 'eventEdition.event')
            ->firstOrFail();

        return Inertia::render('support/Show', [
            'session' => [
                'public_code' => $session->public_code,
                'title' => $session->title,
                'description' => $session->description,
                'activity_type' => $session->activity_type->value,
                'target_distance_meters' => $session->target_distance_meters,
                'athlete_name' => $session->athlete->full_name,
                'event' => $session->eventEdition?->event?->name,
                'allow_text' => $session->allow_text,
                'allow_audio' => $session->allow_audio,
                'accepting' => $session->isAcceptingMessages(),
            ],
            'audioMaxSeconds' => (int) config('finisher.support.audio_max_seconds', 60),
            'audioMaxBytes' => (int) config('finisher.support.audio_max_bytes', 10 * 1024 * 1024),
        ]);
    }

    public function qr(string $publicCode): HttpResponse
    {
        $session = AthleteSupportSession::query()->where('public_code', $publicCode)->firstOrFail();
        $qr = app(QrCodeService::class);

        return response($qr->svg(url("/support/{$session->public_code}")), 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    public function storeMessage(Request $request, string $publicCode, SubmitSupportMessage $submit): RedirectResponse
    {
        $session = AthleteSupportSession::query()->where('public_code', $publicCode)->firstOrFail();

        $data = $request->validate([
            'display_name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'relationship' => ['nullable', 'string', 'max:60'],
            'type' => ['required', Rule::enum(SupportMessageType::class)],
            'message_text' => ['required_if:type,text', 'nullable', 'string', 'max:500'],
            'audio' => [
                'required_if:type,audio', 'nullable', 'file',
                'mimetypes:audio/webm,audio/mp4,audio/m4a,audio/mpeg,audio/mp3,audio/ogg,audio/wav,video/webm',
                'max:'.((int) config('finisher.support.audio_max_bytes', 10 * 1024 * 1024) / 1024),
            ],
            'trigger_type' => ['required', Rule::enum(SupportTriggerType::class)],
            'trigger_distance_meters' => ['required_if:trigger_type,distance', 'nullable', 'integer', 'min:0'],
            'is_surprise' => ['nullable', 'boolean'],
        ]);

        try {
            $submit->handle(
                session: $session,
                contributor: [
                    'display_name' => $data['display_name'],
                    'email' => $data['email'] ?? null,
                    'relationship' => $data['relationship'] ?? null,
                ],
                type: SupportMessageType::from($data['type']),
                messageText: $data['message_text'] ?? null,
                audio: $request->file('audio'),
                triggerType: SupportTriggerType::from($data['trigger_type']),
                triggerDistanceMeters: $data['trigger_distance_meters'] ?? null,
                isSurprise: (bool) ($data['is_surprise'] ?? false),
            );
        } catch (InvalidArgumentException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => '¡Mensaje enviado! Gracias por tu apoyo.']);

        return back();
    }
}
