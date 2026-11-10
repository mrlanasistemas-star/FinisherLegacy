<?php

namespace App\Services\Notifications;

use App\Contracts\Notifications\PushNotificationGateway;
use App\Enums\NotificationCategory;
use App\Models\PushCampaign;
use App\Models\PushDevice;
use App\Models\PushTicket;
use App\Support\Notifications\PushBatchResult;
use App\Support\Notifications\PushMessage;
use App\Support\Notifications\PushNotificationPayload;
use App\Support\Notifications\PushSendResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Expo Push Service (https://docs.expo.dev/push-notifications/sending-notifications/).
 * Bound when PUSH_PROVIDER=expo. Expo relays to FCM v1 (Android) and APNs
 * (iOS) with the credentials uploaded to the EAS project — this server
 * never holds an FCM/APNs key.
 *
 *  - ≤100 messages per request (`sendBatch` chunks anything larger).
 *  - `EXPO_ACCESS_TOKEN` (optional) is sent as a bearer token; required
 *    once "Enhanced push security" is enabled for the EAS project.
 *  - Ticket `ok` → a PushTicket row; `push:check-receipts` reads the
 *    receipt ~15 min later (DeviceNotRegistered there also deactivates).
 *  - Ticket error `DeviceNotRegistered` → device deactivated at once.
 *  - 429 / 5xx / network / `MessageRateExceeded` → retryable.
 *  - Rich push: `imageUrl` → `richContent.image` (Android big picture;
 *    iOS needs a Notification Service Extension, `mutableContent` is set).
 *  - Never logs a push token or the message body.
 */
class ExpoPushNotificationGateway implements PushNotificationGateway
{
    public const int MAX_MESSAGES_PER_REQUEST = 100;

    public function send(PushDevice $device, PushNotificationPayload $payload): PushSendResult
    {
        if (! $device->active) {
            return PushSendResult::failed('Dispositivo inactivo.');
        }

        if (! self::isExpoToken((string) $device->token)) {
            return PushSendResult::failed('El token no es un token de Expo.');
        }

        $message = new PushMessage(
            title: $payload->title,
            body: $payload->message,
            data: self::routingData($payload),
            deepLink: $payload->data['action_url'] ?? $payload->actionUrl,
        );

        $response = $this->post([$this->expoMessage((string) $device->token, $message, null)]);

        if ($response === null) {
            return PushSendResult::failed('Sin conexión con el servicio de push.', retryable: true);
        }

        if ($response->status() === 429 || $response->serverError()) {
            return PushSendResult::failed("Servicio de push respondió {$response->status()}.", retryable: true);
        }

        if ($response->failed()) {
            $this->logRequestFailure($response);

            return PushSendResult::failed("Servicio de push respondió {$response->status()}.");
        }

        $ticket = $response->json('data.0') ?? $response->json('data');
        $outcome = $this->applyTicket($device, is_array($ticket) ? $ticket : []);

        return match ($outcome) {
            'sent' => PushSendResult::sent(),
            'retry' => PushSendResult::failed('MessageRateExceeded', retryable: true),
            default => PushSendResult::failed($outcome),
        };
    }

    public function sendBatch(array $devices, PushMessage $message, array $badgesByUser = []): PushBatchResult
    {
        $sent = 0;
        $failed = 0;
        $deactivated = 0;
        $retry = [];
        $campaignId = $this->campaignId($message);

        $valid = [];

        foreach ($devices as $device) {
            if ($device->active && self::isExpoToken((string) $device->token)) {
                $valid[] = $device;
            } else {
                $failed++;
            }
        }

        foreach (array_chunk($valid, self::MAX_MESSAGES_PER_REQUEST) as $chunk) {
            $messages = array_map(
                fn (PushDevice $d) => $this->expoMessage((string) $d->token, $message, $message->badge ?? ($badgesByUser[$d->user_id] ?? null)),
                $chunk,
            );

            $response = $this->post($messages);

            if ($response === null || $response->status() === 429 || $response->serverError()) {
                array_push($retry, ...array_map(fn (PushDevice $d) => (int) $d->id, $chunk));

                continue;
            }

            if ($response->failed()) {
                $this->logRequestFailure($response);
                $failed += count($chunk);

                continue;
            }

            $tickets = $response->json('data');
            $tickets = is_array($tickets) ? array_values($tickets) : [];

            foreach ($chunk as $i => $device) {
                $outcome = $this->applyTicket($device, is_array($tickets[$i] ?? null) ? $tickets[$i] : [], $campaignId);

                match ($outcome) {
                    'sent' => $sent++,
                    'retry' => $retry[] = (int) $device->id,
                    'DeviceNotRegistered' => [$failed++, $deactivated++],
                    default => $failed++,
                };
            }
        }

        // Messages the provider refused straight away never get a receipt:
        // count them as failed for the campaign here.
        if ($campaignId !== null && $failed > 0) {
            PushCampaign::query()->whereKey($campaignId)->increment('delivery_failed_count', $failed);
        }

        return new PushBatchResult($sent, $failed, $deactivated, $retry);
    }

    public function isConfigured(): bool
    {
        return filled(config('services.expo.push_url'));
    }

    /**
     * Receipts for up to 1000 ticket ids (`push:check-receipts`).
     *
     * @param  array<int, string>  $ticketIds
     * @return array<string, array<string, mixed>>|null null = transient failure, try later
     */
    public function receipts(array $ticketIds): ?array
    {
        try {
            $response = $this->request()->post((string) config('services.expo.receipts_url'), ['ids' => array_values($ticketIds)]);
        } catch (ConnectionException) {
            return null;
        }

        if ($response->failed()) {
            if (! $response->serverError() && $response->status() !== 429) {
                $this->logRequestFailure($response);
            }

            return null;
        }

        $data = $response->json('data');

        return is_array($data) ? $data : [];
    }

    /**
     * @return array{notification_id: mixed, type: mixed, target: mixed, action_url: mixed}
     */
    public static function routingData(PushNotificationPayload $payload): array
    {
        return [
            'notification_id' => $payload->data['notification_id'] ?? null,
            'type' => $payload->data['type'] ?? null,
            'target' => $payload->data['target'] ?? null,
            'action_url' => $payload->data['action_url'] ?? $payload->actionUrl,
        ];
    }

    public static function isExpoToken(string $token): bool
    {
        return (bool) preg_match('/^(ExponentPushToken|ExpoPushToken)\[[^\]]+\]$/', $token);
    }

    /**
     * @return array<string, mixed>
     */
    private function expoMessage(string $token, PushMessage $message, ?int $badge): array
    {
        $payload = [
            'to' => $token,
            'title' => $message->title,
            'body' => $message->body,
            'data' => $message->routingData(),
            'priority' => $message->category === NotificationCategory::Promociones ? 'normal' : 'high',
            'channelId' => $message->channel ?? $message->category?->androidChannel() ?? (string) config('services.expo.android_channel_id', 'default'),
        ];

        if ($message->sound !== null) {
            $payload['sound'] = $message->sound;
        }

        if ($badge !== null) {
            $payload['badge'] = max(0, $badge);
        }

        if ($message->ttlSeconds !== null) {
            $payload['ttl'] = $message->ttlSeconds;
        }

        if (is_string($message->imageUrl) && str_starts_with($message->imageUrl, 'https://')) {
            $payload['richContent'] = ['image' => $message->imageUrl];
            // Lets an iOS Notification Service Extension download the image.
            $payload['mutableContent'] = true;
        }

        return $payload;
    }

    /**
     * The campaign a message belongs to (PushMessage data `campaign_id` = the
     * campaign uuid, see App\Actions\Notifications\CampaignMessage), so its
     * tickets can be counted per campaign. "Enviar prueba" messages carry
     * `test` and are never counted.
     */
    private function campaignId(PushMessage $message): ?int
    {
        $uuid = $message->data['campaign_id'] ?? null;

        if (! is_string($uuid) || $uuid === '' || ! empty($message->data['test'])) {
            return null;
        }

        $id = PushCampaign::query()->where('uuid', $uuid)->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * @param  array<string, mixed>  $ticket
     * @return string 'sent' | 'retry' | the Expo error code
     */
    private function applyTicket(PushDevice $device, array $ticket, ?int $campaignId = null): string
    {
        if (($ticket['status'] ?? null) === 'ok') {
            if (is_string($ticket['id'] ?? null) && $ticket['id'] !== '') {
                PushTicket::query()->firstOrCreate(
                    ['ticket_id' => $ticket['id']],
                    ['push_device_id' => $device->id, 'push_campaign_id' => $campaignId, 'created_at' => now()],
                );
            }

            return 'sent';
        }

        $error = $ticket['details']['error'] ?? null;

        if ($error === 'DeviceNotRegistered') {
            $device->forceFill(['active' => false])->save();

            Log::info('push.expo.device_deactivated', ['push_device_id' => $device->id, 'source' => 'ticket']);

            return 'DeviceNotRegistered';
        }

        if ($error === 'MessageRateExceeded') {
            return 'retry';
        }

        Log::warning('push.expo.ticket_error', [
            'push_device_id' => $device->id,
            'error' => $error,
            'message' => isset($ticket['message']) ? mb_strimwidth((string) $ticket['message'], 0, 200) : null,
        ]);

        return (string) ($error ?? 'Error desconocido del servicio de push.');
    }

    /**
     * @param  list<array<string, mixed>>  $messages
     */
    private function post(array $messages): ?Response
    {
        try {
            return $this->request()->post((string) config('services.expo.push_url'), $messages);
        } catch (ConnectionException) {
            return null;
        }
    }

    private function request(): PendingRequest
    {
        $request = Http::acceptJson()
            ->asJson()
            ->timeout(15)
            ->withHeaders(['Accept-Encoding' => 'gzip, deflate']);

        if (filled($accessToken = config('services.expo.access_token'))) {
            $request = $request->withToken((string) $accessToken);
        }

        return $request;
    }

    private function logRequestFailure(Response $response): void
    {
        Log::warning('push.expo.request_failed', [
            'status' => $response->status(),
            'errors' => $response->json('errors'),
        ]);
    }
}
