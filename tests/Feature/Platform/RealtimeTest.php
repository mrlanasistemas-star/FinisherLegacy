<?php

use App\Actions\Notifications\SendAthleteNotification;
use App\Enums\NotificationType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\Realtime\OrderUpdated;
use App\Events\Realtime\PaymentUpdated;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\AthleteAlert;
use App\Support\Realtime;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Notifications\Events\BroadcastNotificationCreated;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/**
 * Realtime layer (spec §69–74): broadcastable notifications, order/payment
 * hints, private-channel authorization (web session + Sanctum).
 */
function useReverbBroadcaster(): void
{
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => '1',
        'broadcasting.connections.reverb.options.host' => '127.0.0.1',
    ]);

    // Channel definitions live on the driver instance — re-register them on
    // the freshly configured one.
    app(BroadcastManager::class)->forgetDrivers();
    require base_path('routes/channels.php');
}

test('realtime is off with the null broadcaster and with reverb lacking credentials', function () {
    config(['broadcasting.default' => 'null']);
    expect(Realtime::enabled())->toBeFalse();

    config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => null]);
    expect(Realtime::enabled())->toBeFalse();

    config(['broadcasting.default' => 'log']);
    expect(Realtime::enabled())->toBeTrue();
});

test('AthleteAlert only adds the broadcast channel when realtime is enabled', function () {
    $user = User::factory()->create();
    $alert = new AthleteAlert('Hola', 'Mensaje', NotificationType::Custom);

    config(['broadcasting.default' => 'null']);
    expect($alert->via($user))->toBe(['database']);

    config(['broadcasting.default' => 'log']);
    expect($alert->via($user))->toBe(['database', 'broadcast'])
        ->and($alert->broadcastAs())->toBe('notification.created');
});

test('sending a notification broadcasts notification.created on the user private channel without admin metadata', function () {
    config(['broadcasting.default' => 'log']);
    Event::fake([BroadcastNotificationCreated::class]);

    $user = User::factory()->create();
    $admin = User::factory()->create();

    app(SendAthleteNotification::class)->handle(
        $user, 'Pago pendiente', 'Completa tu pago.', NotificationType::Custom, '/store/orders',
        ['kind' => 'order', 'order_uuid' => 'abc', 'internal_note' => 'secreto'], $admin,
    );

    Event::assertDispatched(BroadcastNotificationCreated::class, function (BroadcastNotificationCreated $event) use ($user) {
        $channels = collect($event->broadcastOn())->map(fn ($c) => $c->name)->all();
        $payload = $event->broadcastWith();

        return $channels === ['private-App.Models.User.'.$user->id]
            && $event->broadcastAs() === 'notification.created'
            && $payload['title'] === 'Pago pendiente'
            && $payload['id'] === $event->notification->id
            && $payload['target'] === ['type' => 'order', 'uuid' => 'abc']
            && $payload['notification_id'] === $user->notifications()->first()?->id
            && ! array_key_exists('metadata', $payload)
            && ! array_key_exists('sent_by_user_id', $payload);
    });

    expect($user->notifications()->count())->toBe(1);
});

test('order and payment status changes dispatch order.updated / payment.updated', function () {
    config(['broadcasting.default' => 'log']);
    Event::fake([OrderUpdated::class, PaymentUpdated::class]);

    $user = User::factory()->create();
    $order = Order::factory()->create(['user_id' => $user->id]);

    $payment = Payment::create([
        'uuid' => (string) Str::uuid(), 'order_id' => $order->id, 'provider' => 'stripe', 'method' => 'online_card',
        'status' => PaymentStatus::Pending, 'amount_minor' => 100000, 'currency' => 'MXN', 'provider_reference' => 'pi_rt',
    ]);
    $payment->update(['status' => PaymentStatus::Paid]);

    $order->update(['status' => OrderStatus::Confirmed]);
    $order->update(['customer_snapshot' => ['name' => 'x']]); // not a status change

    Event::assertDispatchedTimes(PaymentUpdated::class, 2);
    Event::assertDispatchedTimes(OrderUpdated::class, 1);
    Event::assertDispatched(OrderUpdated::class, function (OrderUpdated $event) use ($order, $user) {
        $channels = collect($event->broadcastOn())->map(fn ($c) => $c->name)->all();

        return $event->broadcastAs() === 'order.updated'
            && $event->broadcastWith()['uuid'] === $order->uuid
            && $event->broadcastWith()['status'] === 'confirmed'
            && $channels === ['private-App.Models.User.'.$user->id, 'private-admin.commerce'];
    });
});

test('no realtime events are dispatched with the null broadcaster', function () {
    config(['broadcasting.default' => 'null']);
    Event::fake([OrderUpdated::class]);

    Order::factory()->create()->update(['status' => OrderStatus::Confirmed]);

    Event::assertNotDispatched(OrderUpdated::class);
});

test('a user can authorize only their own private notification channel (web session)', function () {
    useReverbBroadcaster();
    $user = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($user)
        ->post('/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => 'private-App.Models.User.'.$user->id])
        ->assertOk()
        ->assertJsonStructure(['auth']);

    $this->actingAs($user)
        ->post('/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => 'private-App.Models.User.'.$other->id])
        ->assertForbidden();
});

test('guests cannot authorize private channels', function () {
    useReverbBroadcaster();
    $user = User::factory()->create();

    $this->post('/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => 'private-App.Models.User.'.$user->id])
        ->assertForbidden();
});

test('the mobile app authorizes private channels with its Sanctum token', function () {
    useReverbBroadcaster();
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => 'private-App.Models.User.'.$user->id])
        ->assertOk()
        ->assertJsonStructure(['auth']);

    $this->postJson('/api/v1/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => 'private-App.Models.User.999999'])
        ->assertForbidden();
});

test('admin.commerce is limited to users who can view orders', function () {
    $this->seed(RolePermissionSeeder::class);
    useReverbBroadcaster();

    $staff = User::factory()->create();
    $staff->givePermissionTo('orders.view');
    $athlete = User::factory()->create();

    $this->actingAs($staff)
        ->post('/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => 'private-admin.commerce'])
        ->assertOk();

    $this->actingAs($athlete)
        ->post('/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => 'private-admin.commerce'])
        ->assertForbidden();
});
