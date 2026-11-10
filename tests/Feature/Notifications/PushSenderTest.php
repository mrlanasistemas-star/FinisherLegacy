<?php

use App\Contracts\Notifications\PushNotificationGateway;
use App\Enums\NotificationCategory;
use App\Jobs\SendPushBatchJob;
use App\Models\NotificationPreference;
use App\Models\PushCampaign;
use App\Models\PushDevice;
use App\Models\PushTicket;
use App\Models\User;
use App\Support\Notifications\NotificationPreferences;
use App\Support\Notifications\PushMessage;
use App\Support\Notifications\PushSender;
use App\Support\Notifications\UnreadCounter;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/**
 * PushSender — the public push API (docs/renovacion/notificaciones.md §API
 * pública): batching (≤100 per Expo request), preferences, badges, rich
 * content, ticket storage, partial retries and the receipts command.
 */
const PUSH_SEND_URL = 'https://exp.host/--/api/v2/push/send';
const PUSH_RECEIPTS_URL = 'https://exp.host/--/api/v2/push/getReceipts';

function pushDeviceFor(User $user, array $overrides = []): PushDevice
{
    return PushDevice::create([
        'uuid' => (string) Str::uuid(),
        'user_id' => $user->id,
        'platform' => 'android',
        'provider' => 'expo',
        'token' => 'ExponentPushToken['.Str::random(12).']',
        'active' => true,
        ...$overrides,
    ]);
}

/** Expo answers one `ok` ticket per message, in order. */
function fakeExpoOk(): void
{
    Http::fake([PUSH_SEND_URL => function (Request $request) {
        return Http::response(['data' => array_map(fn () => ['status' => 'ok', 'id' => (string) Str::uuid()], $request->data())]);
    }]);
}

beforeEach(function () {
    config(['services.push.provider' => 'expo', 'services.expo.access_token' => null]);
});

test('sendToUsers queues one batch job per 100 active devices', function () {
    Queue::fake();
    $users = User::factory()->count(3)->create();

    foreach ($users as $user) {
        foreach (range(1, 84) as $i) {
            pushDeviceFor($user);
        }
    }
    pushDeviceFor($users[0], ['active' => false]);

    $summary = app(PushSender::class)->sendToUsers($users->pluck('id'), PushMessage::make('Hola', 'Mundo'));

    expect($summary->configured)->toBeTrue()
        ->and($summary->users)->toBe(3)
        ->and($summary->devices)->toBe(252)
        ->and($summary->batches)->toBe(3);

    Queue::assertPushed(SendPushBatchJob::class, 3);
    Queue::assertPushed(SendPushBatchJob::class, fn (SendPushBatchJob $job) => count($job->deviceIds) <= 100);
});

test('nothing is queued when no push provider is configured', function () {
    config(['services.push.provider' => 'null']);
    Queue::fake();
    $user = User::factory()->create();
    pushDeviceFor($user);

    $summary = app(PushSender::class)->sendToUsers([$user->id], PushMessage::make('a', 'b'));

    expect($summary->configured)->toBeFalse();
    Queue::assertNothingPushed();
});

test('users who turned push off for the category are skipped', function () {
    Queue::fake();
    [$keeps, $optedOut] = User::factory()->count(2)->create();
    pushDeviceFor($keeps);
    pushDeviceFor($optedOut);
    app(NotificationPreferences::class)->updateCustomer($optedOut, ['comunidad' => ['push' => false]]);

    $summary = app(PushSender::class)->sendToUsers([$keeps->id, $optedOut->id], PushMessage::make('a', 'b')->withCategory(NotificationCategory::Comunidad));

    expect($summary->skippedByPreference)->toBe(1)->and($summary->devices)->toBe(1);
});

test('promociones push is off by default', function () {
    $user = User::factory()->create();

    expect(app(NotificationPreferences::class)->allows($user, NotificationCategory::Promociones, 'push'))->toBeFalse()
        ->and(app(NotificationPreferences::class)->allows($user, NotificationCategory::Pedidos, 'push'))->toBeTrue()
        ->and(NotificationPreference::count())->toBe(0);
});

test('a batch sends channel, badge, sound, rich image and routing data to Expo and stores tickets', function () {
    fakeExpoOk();
    $user = User::factory()->create();
    $device = pushDeviceFor($user);

    app(PushSender::class)->sendToUser($user, PushMessage::make('Tu pedido va en camino', 'Guía 123')
        ->withCategory(NotificationCategory::Pedidos)
        ->withDeepLink('/mis-pedidos/abc')
        ->withImage('https://cdn.test/foto.jpg')
        ->withData(['notification_id' => 'n-1', 'type' => 'order_shipped', 'campaign' => 'x']));

    Http::assertSent(function (Request $request) {
        $message = $request->data()[0];

        return $request->url() === PUSH_SEND_URL
            && $message['channelId'] === 'pedidos'
            && $message['sound'] === 'default'
            && $message['badge'] === 0
            && $message['priority'] === 'high'
            && $message['richContent'] === ['image' => 'https://cdn.test/foto.jpg']
            && $message['mutableContent'] === true
            && $message['data'] === [
                'notification_id' => 'n-1',
                'type' => 'order_shipped',
                'target' => null,
                'action_url' => '/mis-pedidos/abc',
                'campaign' => 'x',
            ];
    });

    expect(PushTicket::where('push_device_id', $device->id)->count())->toBe(1);
});

test('the iOS badge is the recipient unread count unless the message sets one', function () {
    fakeExpoOk();
    $user = User::factory()->create();
    pushDeviceFor($user, ['platform' => 'ios']);
    $user->notifications()->create(['id' => (string) Str::uuid(), 'type' => 'App\\Notifications\\AthleteAlert', 'data' => ['title' => 'x']]);
    $user->notifications()->create(['id' => (string) Str::uuid(), 'type' => 'App\\Notifications\\AdminAlert', 'data' => ['title' => 'admin']]);

    app(PushSender::class)->sendToUser($user, PushMessage::make('a', 'b'));
    app(PushSender::class)->sendToUser($user, PushMessage::make('a', 'b')->withBadge(7));

    $badges = collect(Http::recorded())->map(fn ($pair) => $pair[0]->data()[0]['badge'] ?? null)->all();

    expect($badges)->toBe([1, 7]);
});

test('EXPO_ACCESS_TOKEN is sent as a bearer token on batch sends', function () {
    config(['services.expo.access_token' => 'expo-secret']);
    fakeExpoOk();
    $user = User::factory()->create();
    pushDeviceFor($user);

    app(PushSender::class)->sendToUser($user, PushMessage::make('a', 'b'));

    Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer expo-secret'));
});

test('DeviceNotRegistered tickets deactivate only that device; rate-limited ones are retried alone', function () {
    $user = User::factory()->create();
    $ok = pushDeviceFor($user);
    $dead = pushDeviceFor($user);
    $limited = pushDeviceFor($user);

    Http::fake([PUSH_SEND_URL => Http::response(['data' => [
        ['status' => 'ok', 'id' => 't-ok'],
        ['status' => 'error', 'message' => 'not registered', 'details' => ['error' => 'DeviceNotRegistered']],
        ['status' => 'error', 'message' => 'slow down', 'details' => ['error' => 'MessageRateExceeded']],
    ]])]);

    Queue::fake([SendPushBatchJob::class]);
    (new SendPushBatchJob([$ok->id, $dead->id, $limited->id], PushMessage::make('a', 'b')->withBadge(0)))->handle(
        app(PushNotificationGateway::class),
        app(UnreadCounter::class),
    );

    expect($ok->fresh()->active)->toBeTrue()
        ->and($dead->fresh()->active)->toBeFalse()
        ->and($limited->fresh()->active)->toBeTrue();

    Queue::assertPushed(SendPushBatchJob::class, fn (SendPushBatchJob $job) => $job->deviceIds === [$limited->id] && $job->attempt === 2);
});

test('a 5xx from Expo retries the whole chunk', function () {
    $user = User::factory()->create();
    $a = pushDeviceFor($user);
    $b = pushDeviceFor($user);
    Http::fake([PUSH_SEND_URL => Http::response(['errors' => [['code' => 'INTERNAL']]], 503)]);

    Queue::fake([SendPushBatchJob::class]);
    (new SendPushBatchJob([$a->id, $b->id], PushMessage::make('a', 'b')->withBadge(0)))->handle(
        app(PushNotificationGateway::class),
        app(UnreadCounter::class),
    );

    Queue::assertPushed(SendPushBatchJob::class, fn (SendPushBatchJob $job) => $job->deviceIds === [$a->id, $b->id]);
});

test('push:check-receipts deactivates DeviceNotRegistered devices and marks tickets checked', function () {
    $user = User::factory()->create();
    $good = pushDeviceFor($user);
    $gone = pushDeviceFor($user);
    PushTicket::create(['push_device_id' => $good->id, 'ticket_id' => 'r-ok', 'created_at' => now()->subMinutes(20)]);
    PushTicket::create(['push_device_id' => $gone->id, 'ticket_id' => 'r-gone', 'created_at' => now()->subMinutes(20)]);
    PushTicket::create(['push_device_id' => $good->id, 'ticket_id' => 'r-fresh', 'created_at' => now()]);

    Http::fake([PUSH_RECEIPTS_URL => Http::response(['data' => [
        'r-ok' => ['status' => 'ok'],
        'r-gone' => ['status' => 'error', 'details' => ['error' => 'DeviceNotRegistered']],
    ]])]);

    $this->artisan('push:check-receipts')->assertSuccessful();

    Http::assertSent(fn (Request $request) => $request->url() === PUSH_RECEIPTS_URL && $request->data() === ['ids' => ['r-ok', 'r-gone']]);

    expect($gone->fresh()->active)->toBeFalse()
        ->and($good->fresh()->active)->toBeTrue()
        ->and(PushTicket::where('ticket_id', 'r-ok')->value('status'))->toBe('ok')
        ->and(PushTicket::where('ticket_id', 'r-gone')->value('error'))->toBe('DeviceNotRegistered')
        ->and(PushTicket::where('ticket_id', 'r-fresh')->value('checked_at'))->toBeNull();
});

function pushCampaignFor(array $overrides = []): PushCampaign
{
    return PushCampaign::create([
        'title' => 'Nueva colección', 'body' => 'Ya está en la tienda.', 'audience_type' => 'all',
        'status' => PushCampaign::STATUS_SENDING, 'started_at' => now(), ...$overrides,
    ]);
}

test('a campaign send stamps its tickets with the campaign and counts provider rejections as failed', function () {
    $campaign = pushCampaignFor();
    $user = User::factory()->create();
    $ok = pushDeviceFor($user);
    $gone = pushDeviceFor($user);
    $other = pushDeviceFor($user);

    Http::fake([PUSH_SEND_URL => Http::sequence()
        ->push(['data' => [
            ['status' => 'ok', 'id' => 't-campaign'],
            ['status' => 'error', 'details' => ['error' => 'DeviceNotRegistered']],
        ]])
        ->push(['data' => [['status' => 'ok', 'id' => 't-test']]])
        ->push(['data' => [['status' => 'ok', 'id' => 't-plain']]])]);

    $gateway = app(PushNotificationGateway::class);
    $message = PushMessage::make('Hola', 'Texto')->withData(['type' => 'campaign', 'campaign_id' => $campaign->uuid]);

    $gateway->sendBatch([$ok, $gone], $message);
    // "Enviar prueba" and non-campaign pushes are never attributed.
    $gateway->sendBatch([$other], $message->withData(['test' => true]));
    $gateway->sendBatch([$other], PushMessage::make('Pedido', 'Enviado'));

    expect(PushTicket::where('ticket_id', 't-campaign')->value('push_campaign_id'))->toBe($campaign->id)
        ->and(PushTicket::where('ticket_id', 't-test')->value('push_campaign_id'))->toBeNull()
        ->and(PushTicket::where('ticket_id', 't-plain')->value('push_campaign_id'))->toBeNull()
        ->and($campaign->fresh()->delivery_failed_count)->toBe(1)
        ->and($campaign->fresh()->delivered_count)->toBe(0);
});

test('push:check-receipts counts delivered and failed receipts per campaign', function () {
    $campaign = pushCampaignFor(['sent_count' => 4]);
    $untouched = pushCampaignFor();
    $device = pushDeviceFor(User::factory()->create());

    foreach (['c-ok-1', 'c-ok-2', 'c-bad', 'c-late'] as $ticketId) {
        PushTicket::create(['push_device_id' => $device->id, 'push_campaign_id' => $campaign->id, 'ticket_id' => $ticketId, 'created_at' => now()->subMinutes(20)]);
    }
    PushTicket::create(['push_device_id' => $device->id, 'ticket_id' => 'plain-ok', 'created_at' => now()->subMinutes(20)]);

    Http::fake([PUSH_RECEIPTS_URL => Http::response(['data' => [
        'c-ok-1' => ['status' => 'ok'],
        'c-ok-2' => ['status' => 'ok'],
        'c-bad' => ['status' => 'error', 'details' => ['error' => 'MessageTooBig']],
        'plain-ok' => ['status' => 'ok'],
    ]])]);

    $this->artisan('push:check-receipts')->assertSuccessful();
    // A second run never counts the same receipt twice.
    $this->artisan('push:check-receipts')->assertSuccessful();

    expect($campaign->fresh()->delivered_count)->toBe(2)
        ->and($campaign->fresh()->delivery_failed_count)->toBe(1)
        ->and($untouched->fresh()->delivered_count)->toBe(0)
        ->and(PushTicket::where('ticket_id', 'c-late')->value('checked_at'))->toBeNull();
});
