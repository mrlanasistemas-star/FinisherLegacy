<?php

namespace App\Services\Notifications;

use App\Contracts\Notifications\PushNotificationGateway;
use App\Models\PushDevice;
use App\Support\Notifications\PushNotificationPayload;
use App\Support\Notifications\PushSendResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Expo Push Service (https://docs.expo.dev/push-notifications/sending-notifications/)
 * for the Expo mobile app. Bound when PUSH_PROVIDER=expo.
 *
 *  - One message per call (SendPushNotificationJob is already per-device).
 *  - `EXPO_ACCESS_TOKEN` only needed when "enhanced push security" is on in EAS.
 *  - Ticket error `DeviceNotRegistered` → the device row is deactivated so we
 *    stop sending to it (Expo's documented contract).
 *  - 429 / 5xx / network errors → retryable failure; the job backs off.
 *  - Never logs the push token.
 */
class ExpoPushNotificationGateway implements PushNotificationGateway
{
    public function send(PushDevice $device, PushNotificationPayload $payload): PushSendResult
    {
        $token = (string) $device->token;

        if (! $device->active) {
            return PushSendResult::failed('Dispositivo inactivo.');
        }

        if (! self::isExpoToken($token)) {
            return PushSendResult::failed('El token no es un token de Expo.');
        }

        $message = [
            'to' => $token,
            'title' => $payload->title,
            'body' => $payload->message,
            // Mobile routing contract: {notification_id, type, target,
            // action_url} — built by SendAthleteNotification. Sent as-is
            // (nulls included, so the app sees a stable shape).
            'data' => self::routingData($payload),
            'sound' => 'default',
            'priority' => 'high',
            'channelId' => config('services.expo.android_channel_id', 'default'),
        ];

        $request = Http::acceptJson()
            ->asJson()
            ->timeout(10)
            ->withHeaders(['Accept-Encoding' => 'gzip, deflate']);

        if (filled($accessToken = config('services.expo.access_token'))) {
            $request = $request->withToken($accessToken);
        }

        try {
            $response = $request->post((string) config('services.expo.push_url'), [$message]);
        } catch (ConnectionException) {
            return PushSendResult::failed('Sin conexión con el servicio de push.', retryable: true);
        }

        if ($response->status() === 429 || $response->serverError()) {
            return PushSendResult::failed("Servicio de push respondió {$response->status()}.", retryable: true);
        }

        if ($response->failed()) {
            Log::warning('push.expo.request_failed', [
                'push_device_id' => $device->id,
                'status' => $response->status(),
                'errors' => $response->json('errors'),
            ]);

            return PushSendResult::failed("Servicio de push respondió {$response->status()}.");
        }

        $ticket = $response->json('data.0') ?? $response->json('data');

        if (($ticket['status'] ?? null) === 'ok') {
            return PushSendResult::sent();
        }

        $error = $ticket['details']['error'] ?? null;

        if ($error === 'DeviceNotRegistered') {
            $device->forceFill(['active' => false])->save();

            Log::info('push.expo.device_deactivated', ['push_device_id' => $device->id]);

            return PushSendResult::failed('DeviceNotRegistered');
        }

        Log::warning('push.expo.ticket_error', [
            'push_device_id' => $device->id,
            'error' => $error,
            'message' => $ticket['message'] ?? null,
        ]);

        return PushSendResult::failed((string) ($error ?? 'Error desconocido del servicio de push.'), retryable: $error === 'MessageRateExceeded');
    }

    public function isConfigured(): bool
    {
        return filled(config('services.expo.push_url'));
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
}
