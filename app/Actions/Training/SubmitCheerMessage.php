<?php

namespace App\Actions\Training;

use App\Actions\LegacyTags\ManageStoryMedia;
use App\Actions\Notifications\SendAthleteNotification;
use App\Enums\CheerChannel;
use App\Enums\CheerIcon;
use App\Enums\CheerMessageBilling;
use App\Enums\CheerMessageStatus;
use App\Enums\MediaKind;
use App\Enums\NotificationType;
use App\Exceptions\MediaTooLargeException;
use App\Models\CheerBlock;
use App\Models\CheerLink;
use App\Models\CheerMessage;
use App\Models\CheerSenderCredit;
use App\Models\LegacyTag;
use App\Models\MediaFile;
use App\Models\User;
use App\Services\Media\MediaStorage;
use App\Services\Media\MediaUploadRules;
use App\Services\Media\VideoTranscoder;
use App\Services\Training\CheerQuota;
use App\Support\Commerce\WebCheckoutPayment;
use App\Support\Training\CheerQuotaDecision;
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
 * full) and the pricing decision is taken again (App\Services\Training\
 * CheerQuota, docs/renovacion/tarjeta-animo.md), so the last free slot is
 * never handed out twice. A sender the athlete blocked gets the normal
 * success screen but nothing is stored (returns null) — blocking is never
 * revealed.
 *
 * Paid extra message: the message is stored `pending_payment` (invisible,
 * no notification) with a pending guest Order (CreateCheerMessageOrder);
 * it is delivered only after the server confirms the payment
 * (DeliverPaidCheerMessages) and purged if it never arrives
 * (training:purge-unpaid-cheers). Without OpenPay keys nothing is stored
 * and the sender gets a clear message.
 */
class SubmitCheerMessage
{
    public function __construct(
        private readonly MediaStorage $storage,
        private readonly VideoTranscoder $probe,
        private readonly SendAthleteNotification $notify,
        private readonly CheerQuota $quota,
        private readonly CreateCheerMessageOrder $createOrder,
        private readonly WebCheckoutPayment $payments,
    ) {}

    public static function hashIp(?string $ip): ?string
    {
        return $ip === null || $ip === '' ? null : hash_hmac('sha256', 'cheer-ip|'.$ip, (string) config('app.key'));
    }

    /** HMAC of the sender's random `cheer_sender` cookie (pack credits); never the cookie itself. */
    public static function hashSenderKey(?string $key): ?string
    {
        return $key === null || $key === '' ? null : hash_hmac('sha256', 'cheer-sender|'.$key, (string) config('app.key'));
    }

    public static function rules(): MediaUploadRules
    {
        return new MediaUploadRules(
            [MediaKind::Audio->value => ManageStoryMedia::AUDIO_MIMES],
            [MediaKind::Audio->value => (int) config('training.cheers.max_audio_bytes')],
        );
    }

    /**
     * @param  array{sender_name?: string|null, relationship?: string|null, body?: string|null, icon: string, duration_seconds?: int|string|null, anonymous?: bool|string|null, email?: string|null, pack?: int|string|null}  $data
     * @param  array{channel?: CheerChannel, card?: LegacyTag|null, sender_key?: string|null}  $context
     *
     * @throws ValidationException
     */
    public function handle(CheerLink $link, array $data, ?UploadedFile $audio, ?string $ip, array $context = []): ?CheerMessage
    {
        $ipHash = self::hashIp($ip);
        $channel = $context['channel'] ?? CheerChannel::Link;
        $card = $channel === CheerChannel::NfcCard ? ($context['card'] ?? null) : null;
        $senderKeyHash = self::hashSenderKey($context['sender_key'] ?? null);

        if ($ipHash !== null && CheerBlock::query()->where('cheer_link_id', $link->id)->where('sender_ip_hash', $ipHash)->exists()) {
            return null;
        }

        $body = isset($data['body']) && trim((string) $data['body']) !== '' ? trim((string) $data['body']) : null;

        if ($audio === null && $body === null) {
            throw ValidationException::withMessages(['audio' => __('Graba un mensaje de voz o escribe unas palabras.')]);
        }

        // Early answer before any bytes are stored (taken again under the lock).
        $early = $this->quota->evaluate($link, $channel, $card, $senderKeyHash);
        [$senderName, $anonymous] = $this->sender($data, $early->setting->allow_anonymous);
        $this->assertCanTake($early);

        $duration = $audio !== null ? $this->duration($audio, $data['duration_seconds'] ?? null) : 0;
        $packMessages = isset($data['pack']) && (int) $data['pack'] > 1 ? (int) $data['pack'] : null;

        /** @var MediaFile|null $file */
        $file = null;
        /** @var CheerMessage|null $message */
        $message = null;

        try {
            DB::transaction(function () use ($link, $data, $audio, $body, $duration, $ipHash, $senderKeyHash, $channel, $card, $senderName, $anonymous, $packMessages, &$file, &$message) {
                $locked = CheerLink::query()->whereKey($link->id)->lockForUpdate()->firstOrFail();

                if (! $locked->isAccepting()) {
                    throw ValidationException::withMessages(['form' => __('Este enlace ya no recibe mensajes.')]);
                }

                $decision = $this->quota->evaluate($locked, $channel, $card, $senderKeyHash);
                $this->assertCanTake($decision);

                $recipient = $locked->user;

                if ($audio !== null) {
                    $file = $this->accept($audio, $recipient);
                }

                $paid = $decision->requiresPayment();

                $message = CheerMessage::create([
                    'cheer_link_id' => $locked->id,
                    'recipient_user_id' => $locked->user_id,
                    'source' => $channel,
                    'legacy_tag_id' => $card?->id,
                    'sender_name' => $senderName,
                    'anonymous' => $anonymous,
                    'relationship' => isset($data['relationship']) && trim((string) $data['relationship']) !== '' ? trim((string) $data['relationship']) : null,
                    'body' => $body,
                    'icon' => CheerIcon::from($data['icon']),
                    'audio_media_file_id' => $file?->id,
                    'duration_seconds' => $duration,
                    'status' => $paid ? CheerMessageStatus::PendingPayment : CheerMessageStatus::Visible,
                    'billing' => match ($decision->mode) {
                        CheerQuotaDecision::PAID => CheerMessageBilling::Paid,
                        CheerQuotaDecision::CREDIT => CheerMessageBilling::Credit,
                        default => CheerMessageBilling::Free,
                    },
                    'payment_expires_at' => $paid ? now()->addMinutes(max(5, (int) config('finisher.commerce.order_payment_expiry_minutes', 60))) : null,
                    'favorite' => false,
                    'sender_ip_hash' => $ipHash,
                    'sender_key_hash' => $senderKeyHash,
                ]);

                if ($paid) {
                    $this->createOrder->handle($message, $decision, $packMessages, [
                        'name' => $anonymous ? null : $senderName,
                        'email' => $data['email'] ?? null,
                    ]);

                    return;
                }

                if ($decision->credit !== null) {
                    // Spend one pack credit, re-read under its own row lock.
                    $credit = CheerSenderCredit::query()->whereKey($decision->credit->id)->lockForUpdate()->first();

                    if ($credit === null || $credit->credits_remaining < 1) {
                        throw ValidationException::withMessages(['form' => __('No pudimos usar tu paquete. Recarga la página e intenta de nuevo.')]);
                    }

                    $credit->decrement('credits_remaining');
                }

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
            // Processed now even when unpaid: the purge deletes it if the payment never comes.
            $this->storage->process($file, $message);
        }

        if ($message->status === CheerMessageStatus::Visible) {
            $this->sendNotification($message, $link);
        }

        return $message;
    }

    /**
     * The athlete hears this message now (paid one just confirmed).
     * Shared with DeliverPaidCheerMessages.
     */
    public function notifyDelivered(CheerMessage $message): void
    {
        $message->loadMissing('link.user');
        $this->sendNotification($message, $message->link);
    }

    /**
     * @throws ValidationException
     */
    private function assertCanTake(CheerQuotaDecision $decision): void
    {
        if ($decision->isBlocked()) {
            throw ValidationException::withMessages(['form' => $decision->blockedMessage()]);
        }

        // Fail before storing anything when the site cannot charge (no OpenPay keys).
        if ($decision->requiresPayment() && $this->payments->openpayOnly()['provider'] === null) {
            throw ValidationException::withMessages(['form' => __('Por ahora no podemos cobrar mensajes adicionales. Intenta más tarde.')]);
        }
    }

    /**
     * Sender name, or anonymous when the admin allows it and none was given.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: string, 1: bool}
     *
     * @throws ValidationException
     */
    private function sender(array $data, bool $anonymousAllowed): array
    {
        $name = trim((string) ($data['sender_name'] ?? ''));
        $wantsAnonymous = filter_var($data['anonymous'] ?? false, FILTER_VALIDATE_BOOL) || $name === '';

        if ($anonymousAllowed && $wantsAnonymous) {
            return ['', true];
        }

        if ($name === '') {
            throw ValidationException::withMessages(['sender_name' => __('Escribe tu nombre para que sepa quién le manda ánimo.')]);
        }

        return [$name, false];
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
                ? __(':name te dejó un mensaje de voz para tu entrenamiento.', ['name' => $message->displaySenderName()])
                : $message->displaySenderName().': '.mb_strimwidth((string) $message->body, 0, 90, '…'),
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
