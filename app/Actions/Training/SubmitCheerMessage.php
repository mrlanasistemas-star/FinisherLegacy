<?php

namespace App\Actions\Training;

use App\Actions\LegacyTags\ManageStoryMedia;
use App\Actions\Notifications\SendAthleteNotification;
use App\Enums\CheerIcon;
use App\Enums\CheerMessageStatus;
use App\Enums\MediaKind;
use App\Enums\NotificationType;
use App\Exceptions\MediaTooLargeException;
use App\Models\CheerBlock;
use App\Models\CheerLink;
use App\Models\CheerMessage;
use App\Models\MediaFile;
use App\Models\User;
use App\Services\Media\MediaStorage;
use App\Services\Media\MediaUploadRules;
use App\Services\Media\VideoTranscoder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use League\Flysystem\FilesystemException;
use Throwable;

/**
 * A family member/friend leaves a cheer through `/animo/{token}` — no
 * account. The voice note goes through the same MediaStorage pipeline as
 * Legacy story audio (sniffed MIME from the bytes, the story AUDIO_MIMES,
 * size cap, temp disk → queue → media disk, private). Duration is probed
 * with ffprobe when the host has it, else the browser-measured value is
 * clamped to the limit.
 *
 * Under a lock on the link row the link is re-checked (revoked / expired /
 * full), so the last free slot is never handed out twice. A sender the
 * athlete blocked gets the normal success screen but nothing is stored
 * (returns null) — blocking is never revealed.
 */
class SubmitCheerMessage
{
    public function __construct(
        private readonly MediaStorage $storage,
        private readonly VideoTranscoder $probe,
        private readonly SendAthleteNotification $notify,
    ) {}

    public static function hashIp(?string $ip): ?string
    {
        return $ip === null || $ip === '' ? null : hash_hmac('sha256', 'cheer-ip|'.$ip, (string) config('app.key'));
    }

    public static function rules(): MediaUploadRules
    {
        return new MediaUploadRules(
            [MediaKind::Audio->value => ManageStoryMedia::AUDIO_MIMES],
            [MediaKind::Audio->value => (int) config('training.cheers.max_audio_bytes')],
        );
    }

    /**
     * @param  array{sender_name: string, relationship?: string|null, body?: string|null, icon: string, duration_seconds?: int|string|null}  $data
     *
     * @throws ValidationException
     */
    public function handle(CheerLink $link, array $data, ?UploadedFile $audio, ?string $ip): ?CheerMessage
    {
        $ipHash = self::hashIp($ip);

        if ($ipHash !== null && CheerBlock::query()->where('cheer_link_id', $link->id)->where('sender_ip_hash', $ipHash)->exists()) {
            return null;
        }

        $body = isset($data['body']) && trim((string) $data['body']) !== '' ? trim((string) $data['body']) : null;

        if ($audio === null && $body === null) {
            throw ValidationException::withMessages(['audio' => __('Graba un mensaje de voz o escribe unas palabras.')]);
        }

        $duration = $audio !== null ? $this->duration($audio, $data['duration_seconds'] ?? null) : 0;

        /** @var MediaFile|null $file */
        $file = null;
        /** @var CheerMessage|null $message */
        $message = null;

        try {
            DB::transaction(function () use ($link, $data, $audio, $body, $duration, $ipHash, &$file, &$message) {
                $locked = CheerLink::query()->whereKey($link->id)->lockForUpdate()->firstOrFail();

                if (! $locked->isAccepting()) {
                    throw ValidationException::withMessages(['form' => __('Este enlace ya no recibe mensajes.')]);
                }

                $recipient = $locked->user;

                if ($audio !== null) {
                    $file = $this->accept($audio, $recipient);
                }

                $message = CheerMessage::create([
                    'cheer_link_id' => $locked->id,
                    'recipient_user_id' => $locked->user_id,
                    'sender_name' => trim($data['sender_name']),
                    'relationship' => isset($data['relationship']) && trim((string) $data['relationship']) !== '' ? trim((string) $data['relationship']) : null,
                    'body' => $body,
                    'icon' => CheerIcon::from($data['icon']),
                    'audio_media_file_id' => $file?->id,
                    'duration_seconds' => $duration,
                    'status' => CheerMessageStatus::Visible,
                    'favorite' => false,
                    'sender_ip_hash' => $ipHash,
                ]);

                $locked->increment('messages_count');
            });
        } catch (Throwable $e) {
            if ($file !== null) {
                $this->discardUpload($file);
            }

            throw $e;
        }

        /** @var CheerMessage $message */
        if ($file !== null) {
            $this->storage->process($file, $message);
        }

        $this->sendNotification($message, $link);

        return $message;
    }

    /**
     * @throws ValidationException
     */
    private function duration(UploadedFile $audio, int|string|null $clientSeconds): int
    {
        $max = (int) config('training.cheers.max_audio_seconds', 180);
        $probed = $this->probe->probe((string) $audio->getRealPath())['duration_ms'];

        // One second of slack: browsers stop the recorder a hair late.
        if ($probed !== null && $probed > ($max + 1) * 1000) {
            throw ValidationException::withMessages(['audio' => __('El mensaje dura más de :minutes minutos. Graba uno más corto.', ['minutes' => intdiv($max, 60)])]);
        }

        $seconds = $probed !== null ? (int) round($probed / 1000) : (int) $clientSeconds;

        return max(0, min($max, $seconds));
    }

    /**
     * @throws ValidationException
     */
    private function accept(UploadedFile $audio, User $recipient): MediaFile
    {
        try {
            return $this->storage->accept($audio, $this->storage->paths()->forUser($recipient, 'support'), self::rules(), $recipient);
        } catch (MediaTooLargeException) {
            throw ValidationException::withMessages(['audio' => __('El audio pesa más de :size MB.', ['size' => intdiv((int) config('training.cheers.max_audio_bytes'), 1024 * 1024)])]);
        } catch (FilesystemException) {
            throw ValidationException::withMessages(['audio' => __('No pudimos guardar el audio en este momento. Intenta de nuevo en unos minutos.')]);
        } catch (ValidationException) {
            throw ValidationException::withMessages(['audio' => __('Sube un audio M4A, MP3, AAC, WebM u OGG.')]);
        }
    }

    private function discardUpload(MediaFile $file): void
    {
        try {
            if (is_string($file->source_path) && $file->source_path !== '') {
                Storage::disk((string) ($file->source_disk ?: $this->storage->tmpDisk()))->delete($file->source_path);
            }
            Storage::disk($this->storage->tmpDisk())->deleteDirectory($this->storage->workDirectory($file));
        } catch (Throwable) {
            // Best effort: the original error is what the sender must see.
        }
    }

    private function sendNotification(CheerMessage $message, CheerLink $link): void
    {
        $scheme = (string) config('nfc.app.scheme', 'finisherlegacy');

        $this->notify->handle(
            $link->user,
            // Rendered in the athlete's language, not the sender's browser one.
            fn (): string => __('Nuevo mensaje de ánimo'),
            fn (): string => $message->audio_media_file_id !== null
                ? __(':name te dejó un mensaje de voz para tu entrenamiento.', ['name' => $message->sender_name])
                : "{$message->sender_name}: ".mb_strimwidth((string) $message->body, 0, 90, '…'),
            NotificationType::TrainingCheer,
            route('training.index'),
            [
                'target' => ['type' => 'training', 'uuid' => $message->uuid],
                'deep_link' => $scheme.'://training',
            ],
            push: true,
        );
    }
}
