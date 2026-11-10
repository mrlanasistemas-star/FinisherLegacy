<?php

namespace App\Actions\Training;

use App\Enums\CheerMessageStatus;
use App\Enums\ReportStatus;
use App\Models\CheerBlock;
use App\Models\CheerLink;
use App\Models\CheerMessage;
use App\Models\CheerMessageReport;
use App\Models\User;
use App\Services\Media\MediaStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

/**
 * Staff moderation of Entrenamientos (docs/renovacion/entrenamientos.md
 * §Moderación). Cheers are private: staff never browse messages. They can
 * disable / re-enable a link, resolve a report, purge a reported sender's
 * messages, and — only for a REPORTED message — get a short-lived signed
 * URL to its audio. Every action is written to the activity log.
 */
class ModerateCheers
{
    /** Minutes a moderator's audio URL stays valid. */
    public const AUDIO_URL_MINUTES = 10;

    public function __construct(private readonly MediaStorage $storage) {}

    public function disableLink(User $moderator, CheerLink $link, ?string $note = null): void
    {
        if ($link->moderation_disabled_at === null) {
            $link->forceFill(['moderation_disabled_at' => now(), 'moderation_disabled_by' => $moderator->id])->save();
        }

        $this->log($moderator, $link, 'cheer_link.disabled', 'Enlace de ánimo desactivado por moderación.', ['note' => $note]);
    }

    public function enableLink(User $moderator, CheerLink $link): void
    {
        $link->forceFill(['moderation_disabled_at' => null, 'moderation_disabled_by' => null])->save();

        $this->log($moderator, $link, 'cheer_link.enabled', 'Enlace de ánimo reactivado por moderación.');
    }

    public function resolve(User $moderator, CheerMessageReport $report, ReportStatus $status, ?string $notes = null): void
    {
        $report->forceFill([
            'status' => $status,
            'reviewed_by' => $moderator->id,
            'reviewed_at' => now(),
            'resolution_notes' => $notes,
        ])->save();

        $this->log($moderator, $report, 'cheer_report.'.$status->value, 'Reporte de mensaje de ánimo actualizado.', ['notes' => $notes]);
    }

    /**
     * Deletes (rows + audio files) every message the reported sender left
     * for that athlete, blocks the sender on the reported link and resolves
     * the report. Without a sender hash only the reported message goes.
     *
     * @return int Messages deleted
     */
    public function purgeSender(User $moderator, CheerMessageReport $report, ?string $notes = null): int
    {
        $link = $report->link;
        $recipientId = $link !== null ? $link->user_id : $report->reporter_user_id;
        $hash = $report->sender_ip_hash;

        $query = CheerMessage::query()->with('audio');
        $hash !== null
            ? $query->where('recipient_user_id', $recipientId)->where('sender_ip_hash', $hash)
            : $query->whereKey($report->cheer_message_id ?? 0);
        $messages = $query->get();

        DB::transaction(function () use ($messages, $hash, $link, $recipientId) {
            if ($hash !== null && $link !== null) {
                CheerBlock::query()->firstOrCreate(
                    ['cheer_link_id' => $link->id, 'sender_ip_hash' => $hash],
                    ['user_id' => $recipientId, 'cheer_message_id' => null],
                );
            }

            foreach ($messages as $message) {
                $audio = $message->audio;
                $message->delete();

                if ($audio !== null) {
                    $this->storage->delete($audio);
                }
            }
        });

        $this->resolve($moderator, $report->refresh(), ReportStatus::Resolved, $notes ?? 'Mensajes del remitente eliminados.');
        $this->log($moderator, $report, 'cheer_sender.purged', 'Mensajes de un remitente eliminados por moderación.', ['deleted' => $messages->count()]);

        return $messages->count();
    }

    /**
     * Signed URL (AUDIO_URL_MINUTES) to the reported message's audio, or
     * null when there is none (text only, purged, still processing).
     * Audit-logged every time it is minted.
     */
    public function reportedAudioUrl(User $moderator, CheerMessageReport $report): ?string
    {
        $message = $report->message;
        $audio = $message?->audio;

        if ($audio === null || ! $audio->isServable()) {
            return null;
        }

        $this->log($moderator, $report, 'cheer_report.audio_accessed', 'Audio de un mensaje reportado escuchado por moderación.', [
            'message_uuid' => $message->uuid,
        ]);

        return url(URL::temporarySignedRoute(
            'media.file',
            now()->addMinutes(self::AUDIO_URL_MINUTES),
            ['mediaFile' => $audio->uuid, 'variant' => 'default'],
            absolute: false,
        ));
    }

    /** Puts a reported message back in the athlete's list (e.g. a dismissed report). */
    public function restoreMessage(User $moderator, CheerMessageReport $report): void
    {
        $report->message?->forceFill(['status' => CheerMessageStatus::Visible])->save();

        $this->log($moderator, $report, 'cheer_message.restored', 'Mensaje de ánimo restaurado por moderación.');
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function log(User $moderator, CheerLink|CheerMessageReport $subject, string $event, string $description, array $properties = []): void
    {
        activity('training')
            ->causedBy($moderator)
            ->performedOn($subject)
            ->event($event)
            ->withProperties(array_filter($properties, fn ($v) => $v !== null))
            ->log($description);
    }
}
