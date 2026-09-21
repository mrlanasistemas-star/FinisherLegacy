<?php

namespace App\Actions\Support;

use App\Enums\SupportMessageStatus;
use App\Enums\SupportMessageType;
use App\Enums\SupportTriggerType;
use App\Models\AthleteSupportMessage;
use App\Models\AthleteSupportSession;
use App\Models\SupportContributor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * A supporter's text or audio message (product UX consolidation brief
 * §27-§31) — no account required. Never trusts the session's
 * accept-messages state to have been checked by the caller: re-verifies
 * here, since this is reachable directly from a public, unauthenticated
 * route.
 */
class SubmitSupportMessage
{
    /**
     * @param  array{display_name: string, email?: ?string, relationship?: ?string}  $contributor
     */
    public function handle(
        AthleteSupportSession $session,
        array $contributor,
        SupportMessageType $type,
        ?string $messageText = null,
        ?UploadedFile $audio = null,
        SupportTriggerType $triggerType = SupportTriggerType::Manual,
        ?int $triggerDistanceMeters = null,
        bool $isSurprise = false,
    ): AthleteSupportMessage {
        if (! $session->isAcceptingMessages()) {
            throw new InvalidArgumentException('Esta sesión de apoyo ya no acepta mensajes.');
        }

        if ($type === SupportMessageType::Text && ! $session->allow_text) {
            throw new InvalidArgumentException('Esta sesión no acepta mensajes de texto.');
        }

        if ($type === SupportMessageType::Audio && ! $session->allow_audio) {
            throw new InvalidArgumentException('Esta sesión no acepta mensajes de audio.');
        }

        $contributorModel = SupportContributor::create([
            'athlete_support_session_id' => $session->id,
            'display_name' => $contributor['display_name'],
            'email' => $contributor['email'] ?? null,
            'relationship' => $contributor['relationship'] ?? null,
        ]);

        $audioAttributes = [];

        if ($type === SupportMessageType::Audio && $audio !== null) {
            $disk = (string) config('finisher.support.audio_disk', 'local');
            $path = $audio->store("support-audio/{$session->id}", $disk);

            $audioAttributes = [
                'audio_disk' => $disk,
                'audio_path' => $path,
                'audio_mime' => $audio->getMimeType(),
                'audio_size_bytes' => $audio->getSize(),
            ];
        }

        return AthleteSupportMessage::create([
            'uuid' => (string) Str::uuid(),
            'athlete_support_session_id' => $session->id,
            'support_contributor_id' => $contributorModel->id,
            'type' => $type,
            'message_text' => $type === SupportMessageType::Text ? $messageText : null,
            ...$audioAttributes,
            'trigger_type' => $triggerType,
            'trigger_distance_meters' => $triggerType === SupportTriggerType::Distance ? $triggerDistanceMeters : null,
            'status' => $session->auto_approve ? SupportMessageStatus::Approved : SupportMessageStatus::Pending,
            'is_surprise' => $isSurprise,
            'approved_at' => $session->auto_approve ? now() : null,
        ]);
    }
}
