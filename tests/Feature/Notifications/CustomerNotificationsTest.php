<?php

use App\Actions\Notifications\SendAthleteNotification;
use App\Actions\Social\NotifySocialActivity;
use App\Enums\NotificationCategory;
use App\Enums\NotificationType;
use App\Enums\SupportCaseStatus;
use App\Enums\SupportCaseType;
use App\Events\Commerce\OrderDelivered;
use App\Events\Commerce\OrderPaid;
use App\Events\Commerce\OrderRefunded;
use App\Events\Commerce\OrderShipped;
use App\Jobs\SendPushBatchJob;
use App\Models\Order;
use App\Models\PushDevice;
use App\Models\SupportCase;
use App\Models\User;
use App\Notifications\AdminAlert;
use App\Support\Notifications\NotificationPreferences;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/**
 * Customer parity (docs/renovacion/notificaciones.md §Tipos): every kind a
 * customer can receive lands in the shared notification center (web + app,
 * same rows, same read state) and goes out as push when they have a device,
 * honoring their per-category preferences.
 */
beforeEach(function () {
    $this->withoutVite();
    config(['services.push.provider' => 'expo']);
    Mail::fake();
    Queue::fake([SendPushBatchJob::class]);
});

function customerWithDevice(): User
{
    $user = User::factory()->create();
    PushDevice::create([
        'uuid' => (string) Str::uuid(), 'user_id' => $user->id, 'platform' => 'ios',
        'provider' => 'expo', 'token' => 'ExponentPushToken[cust]', 'active' => true,
    ]);

    return $user;
}

test('order lifecycle events notify the buyer in-app and by push', function (string $event, string $type) {
    $user = customerWithDevice();
    $order = Order::factory()->create(['user_id' => $user->id, 'tracking_number' => 'TRK1', 'carrier' => 'DHL']);

    match ($event) {
        'paid' => OrderPaid::dispatch($order),
        'shipped' => OrderShipped::dispatch($order),
        'delivered' => OrderDelivered::dispatch($order),
        'refunded' => OrderRefunded::dispatch($order, 50000, false),
    };

    $row = $user->notifications()->sole();

    expect($row->data['type'])->toBe($type)
        ->and($row->data['action_url'])->toBe("/mis-pedidos/{$order->uuid}")
        ->and($row->data['metadata']['category'])->toBe('pedidos');

    Queue::assertPushed(SendPushBatchJob::class, fn (SendPushBatchJob $job) => $job->message->data['notification_id'] === $row->id
        && $job->message->data['type'] === $type
        && $job->message->data['target'] === ['type' => 'order', 'uuid' => $order->uuid]
        && $job->message->resolvedChannel() === 'pedidos');
})->with([
    ['paid', 'order_paid'],
    ['shipped', 'order_shipped'],
    ['delivered', 'order_delivered'],
    ['refunded', 'order_refunded'],
]);

test('social activity now pushes too (category comunidad)', function () {
    $recipient = customerWithDevice();
    $follower = User::factory()->create();

    app(NotifySocialActivity::class)->newFollower($recipient, $follower);

    expect($recipient->notifications()->count())->toBe(1);
    Queue::assertPushed(SendPushBatchJob::class, fn (SendPushBatchJob $job) => $job->message->resolvedChannel() === 'comunidad');
});

test('in-app OFF skips the row, push OFF skips the push — enforced server-side', function () {
    $user = customerWithDevice();
    $prefs = app(NotificationPreferences::class);

    $prefs->updateCustomer($user, ['comunidad' => ['in_app' => false, 'push' => true]]);
    app(SendAthleteNotification::class)->handle($user, 'a', 'b', NotificationType::NewFollower, push: true);
    expect($user->notifications()->count())->toBe(0);
    Queue::assertPushed(SendPushBatchJob::class, 1);

    $prefs->updateCustomer($user, ['comunidad' => ['in_app' => true, 'push' => false]]);
    app(SendAthleteNotification::class)->handle($user, 'a', 'b', NotificationType::NewFollower, push: true);
    expect($user->notifications()->count())->toBe(1);
    Queue::assertPushed(SendPushBatchJob::class, 1);
});

test('an admin-sent message is always stored even with in-app off', function () {
    $user = customerWithDevice();
    app(NotificationPreferences::class)->updateCustomer($user, ['avisos' => ['in_app' => false]]);

    app(SendAthleteNotification::class)->handle($user, 'Aviso', 'Hola', NotificationType::Custom, sentBy: User::factory()->create());

    expect($user->notifications()->count())->toBe(1);
});

test('a support case update notifies the person who reported it', function () {
    $user = customerWithDevice();
    $case = SupportCase::create([
        'uuid' => (string) Str::uuid(), 'number' => 'SUP-0001', 'type' => SupportCaseType::cases()[0],
        'status' => SupportCaseStatus::Open, 'priority' => 'normal', 'reporter_user_id' => $user->id,
        'subject' => 'Mi tag no lee', 'description' => 'No funciona',
    ]);

    $case->update(['status' => SupportCaseStatus::Resolved, 'resolution' => 'Te enviamos uno nuevo.', 'resolved_at' => now()]);

    $row = $user->notifications()->sole();
    expect($row->data['type'])->toBe('support_reply')
        ->and($row->data['message'])->toContain('Te enviamos uno nuevo.');
    Queue::assertPushed(SendPushBatchJob::class);
});

test('web center and API list the same customer rows, never admin alerts, and share read state', function () {
    $user = customerWithDevice();
    app(SendAthleteNotification::class)->handle($user, 'Nuevo seguidor', 'Ana te sigue', NotificationType::NewFollower, '/@ana', ['kind' => 'athlete', 'username' => 'ana']);
    $user->notifications()->create(['id' => (string) Str::uuid(), 'type' => AdminAlert::class, 'data' => ['title' => 'Interno', 'category' => 'ventas']]);

    Sanctum::actingAs($user);
    $api = $this->getJson('/api/v1/me/notifications')->assertOk();

    expect($api->json('data.data'))->toHaveCount(1)
        ->and($api->json('meta.unread_count'))->toBe(1)
        ->and($api->json('data.data.0.category'))->toBe('comunidad')
        ->and($api->json('data.data.0.target'))->toMatchArray(['type' => 'athlete', 'slug' => 'ana', 'kind' => 'athlete', 'username' => 'ana']);

    $id = $api->json('data.data.0.id');
    $this->postJson("/api/v1/me/notifications/{$id}/read")->assertOk()->assertJsonPath('data.unread_count', 0);

    $this->actingAs($user)->getJson('/notifications/summary')
        ->assertOk()
        ->assertJsonPath('unread_count', 0)
        ->assertJsonCount(1, 'latest')
        ->assertJsonPath('latest.0.read_at', fn ($v) => $v !== null);
});

test('the app reads and updates preferences through the API', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/me/notifications/preferences')
        ->assertOk()
        ->assertJsonPath('data.categories.0.key', 'pedidos')
        ->assertJsonPath('data.push_available', true);

    $this->putJson('/api/v1/me/notifications/preferences', ['preferences' => ['comunidad' => ['push' => false]]])
        ->assertOk()
        ->assertJsonPath('data.categories.1.key', 'comunidad')
        ->assertJsonPath('data.categories.1.push', false)
        ->assertJsonPath('data.categories.1.in_app', true);

    $this->putJson('/api/v1/me/notifications/preferences', ['preferences' => ['nope' => ['push' => false]]])
        ->assertUnprocessable();
});

test('the web settings page shows and saves the same preferences', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/settings/notificaciones')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('settings/Notifications')->has('categories', 6));

    $this->actingAs($user)->put('/settings/notificaciones', ['preferences' => [
        ['key' => 'entrenamientos', 'in_app' => true, 'push' => false],
    ]])->assertRedirect();

    expect(app(NotificationPreferences::class)->allows($user, NotificationCategory::Entrenamientos, 'push'))->toBeFalse();
});
