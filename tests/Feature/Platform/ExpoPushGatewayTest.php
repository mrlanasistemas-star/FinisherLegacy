<?php

use App\Actions\Athletes\RegisterPushDevice;
use App\Actions\Notifications\SendAthleteNotification;
use App\Contracts\Notifications\PushNotificationGateway;
use App\Enums\NotificationType;
use App\Enums\PushSendStatus;
use App\Jobs\SendPushNotificationJob;
use App\Models\PushDevice;
use App\Models\User;
use App\Services\Notifications\ExpoPushNotificationGateway;
use App\Services\Notifications\NullPushNotificationGateway;
use App\Support\Notifications\NotificationTarget;
use App\Support\Notifications\PushNotificationPayload;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

const EXPO_URL = 'https://exp.host/--/api/v2/push/send';

function expoDevice(array $overrides = []): PushDevice
{
    return PushDevice::create([
        'uuid' => (string) Str::uuid(),
        'user_id' => User::factory()->create()->id,
        'platform' => 'ios',
        'provider' => 'expo',
        'token' => 'ExponentPushToken[abc123]',
        'active' => true,
        ...$overrides,
    ]);
}

beforeEach(function () {
    config(['services.push.provider' => 'expo', 'services.expo.access_token' => null]);
});

test('PUSH_PROVIDER picks the bound gateway', function () {
    expect(app(PushNotificationGateway::class))->toBeInstanceOf(ExpoPushNotificationGateway::class);

    config(['services.push.provider' => 'null']);
    expect(app(PushNotificationGateway::class))->toBeInstanceOf(NullPushNotificationGateway::class);
});

test('a push is sent to Expo with title, body and deep-link data', function () {
    Http::fake([EXPO_URL => Http::response(['data' => [['status' => 'ok', 'id' => 'ticket-1']]])]);
    $device = expoDevice();

    $result = app(PushNotificationGateway::class)->send(
        $device,
        new PushNotificationPayload('Nuevo seguidor', 'Ana empezó a seguirte.', '/@ana', [
            'notification_id' => 'n-1', 'type' => 'new_follower',
            'target' => ['type' => 'athlete', 'slug' => 'ana'], 'action_url' => '/@ana',
            'internal' => 'never sent',
        ]),
    );

    expect($result->status)->toBe(PushSendStatus::Sent);

    Http::assertSent(function (Request $request) {
        $message = $request->data()[0];

        return $request->url() === EXPO_URL
            && $message['to'] === 'ExponentPushToken[abc123]'
            && $message['title'] === 'Nuevo seguidor'
            && $message['body'] === 'Ana empezó a seguirte.'
            && $message['data'] === [
                'notification_id' => 'n-1',
                'type' => 'new_follower',
                'target' => ['type' => 'athlete', 'slug' => 'ana'],
                'action_url' => '/@ana',
            ]
            && ! $request->hasHeader('Authorization');
    });
});

test('SendAthleteNotification pushes {notification_id, type, target, action_url} matching the stored notification', function () {
    Http::fake([EXPO_URL => Http::response(['data' => [['status' => 'ok', 'id' => 't']]])]);
    $device = expoDevice();
    $user = $device->user;

    app(SendAthleteNotification::class)->handle(
        $user, 'Tu pedido', 'Se confirmó.', NotificationType::Custom, '/store/orders/u-1',
        ['kind' => 'order', 'order_uuid' => 'u-1', 'secret' => 'x'], push: true,
    );

    $stored = $user->notifications()->first();

    Http::assertSent(fn (Request $request) => $request->data()[0]['data'] === [
        'notification_id' => $stored->id,
        'type' => 'custom',
        'target' => ['type' => 'order', 'uuid' => 'u-1'],
        'action_url' => '/store/orders/u-1',
    ]);
});

test('notification targets are built from metadata with whitelisted keys only', function () {
    expect(NotificationTarget::fromMetadata(['kind' => 'moment', 'moment_uuid' => 'm-1']))
        ->toBe(['type' => 'moment', 'uuid' => 'm-1'])
        ->and(NotificationTarget::fromMetadata(['target' => ['type' => 'event', 'id' => '7', 'slug' => 'maraton', 'event_slug' => 'maraton', 'x' => 1]]))
        ->toBe(['type' => 'event', 'id' => 7, 'slug' => 'maraton', 'event_slug' => 'maraton'])
        ->and(NotificationTarget::fromMetadata(['kind' => 'unknown']))->toBeNull()
        ->and(NotificationTarget::fromMetadata(null))->toBeNull();
});

test('the optional EXPO_ACCESS_TOKEN is sent as a bearer token', function () {
    config(['services.expo.access_token' => 'expo-secret']);
    Http::fake([EXPO_URL => Http::response(['data' => [['status' => 'ok', 'id' => 't']]])]);

    app(PushNotificationGateway::class)->send(expoDevice(), new PushNotificationPayload('a', 'b'));

    Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer expo-secret'));
});

test('DeviceNotRegistered deactivates the device', function () {
    Http::fake([EXPO_URL => Http::response(['data' => [[
        'status' => 'error',
        'message' => '"ExponentPushToken[abc123]" is not a registered push notification recipient',
        'details' => ['error' => 'DeviceNotRegistered'],
    ]]])]);
    $device = expoDevice();

    $result = app(PushNotificationGateway::class)->send($device, new PushNotificationPayload('a', 'b'));

    expect($result->status)->toBe(PushSendStatus::Failed)
        ->and($result->reason)->toBe('DeviceNotRegistered')
        ->and($result->retryable)->toBeFalse()
        ->and($device->fresh()->active)->toBeFalse();
});

test('server errors and rate limits are retryable and keep the device active', function (int $status) {
    Http::fake([EXPO_URL => Http::response(['errors' => [['code' => 'X']]], $status)]);
    $device = expoDevice();

    $result = app(PushNotificationGateway::class)->send($device, new PushNotificationPayload('a', 'b'));

    expect($result->status)->toBe(PushSendStatus::Failed)
        ->and($result->retryable)->toBeTrue()
        ->and($device->fresh()->active)->toBeTrue();
})->with([429, 500, 503]);

test('a non-Expo token never reaches the network', function () {
    Http::fake();

    $result = app(PushNotificationGateway::class)->send(
        expoDevice(['token' => 'fcm-raw-token', 'provider' => 'fcm']),
        new PushNotificationPayload('a', 'b'),
    );

    expect($result->status)->toBe(PushSendStatus::Failed);
    Http::assertNothingSent();
});

test('the queued job sends through the gateway and skips inactive devices', function () {
    Http::fake([EXPO_URL => Http::response(['data' => [['status' => 'ok', 'id' => 't']]])]);

    SendPushNotificationJob::dispatchSync(expoDevice(), new PushNotificationPayload('a', 'b'));
    SendPushNotificationJob::dispatchSync(expoDevice(['active' => false]), new PushNotificationPayload('a', 'b'));

    Http::assertSentCount(1);
});

test('registering a token on a new account deactivates it on the previous account', function () {
    $first = User::factory()->create();
    $second = User::factory()->create();
    $register = app(RegisterPushDevice::class);

    $old = $register->handle($first, 'ios', 'expo', 'ExponentPushToken[shared]');
    $new = $register->handle($second, 'ios', 'expo', 'ExponentPushToken[shared]');

    expect($old->fresh()->active)->toBeFalse()
        ->and($new->fresh()->active)->toBeTrue()
        ->and($new->user_id)->toBe($second->id);
});
