<?php

use App\Actions\Notifications\SendAthleteNotification;
use App\Contracts\Notifications\PushNotificationGateway;
use App\Enums\NotificationType;
use App\Enums\PushSendStatus;
use App\Jobs\SendPushNotificationJob;
use App\Models\Athlete;
use App\Models\EventParticipant;
use App\Models\PushDevice;
use App\Models\User;
use App\Support\Notifications\PushNotificationPayload;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/**
 * Admin-to-athlete notifications (product UX consolidation brief §36-§38,
 * §52-§56) — Laravel's Notifiable/database channel, encapsulated behind
 * App\Actions\Notifications\SendAthleteNotification so every send path
 * (Participant Profile now, a future bulk-reminder flow) goes through the
 * same place.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

test('sending an athlete notification creates a database notification the athlete can read', function () {
    $athleteUser = User::factory()->create();
    $athlete = Athlete::factory()->create(['user_id' => $athleteUser->id]);
    $participant = EventParticipant::factory()->create(['athlete_id' => $athlete->id]);

    $this->actingAs($this->admin)->post("/admin/participants/{$participant->id}/notify", [
        'type' => 'payment_pending',
        'title' => 'Pago pendiente',
        'message' => 'Completa el pago de tu Legacy Plate.',
    ])->assertRedirect();

    expect($athleteUser->fresh()->notifications()->count())->toBe(1);

    $response = $this->actingAs($athleteUser)->get('/notifications');
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Notifications')
        ->has('notifications.data', 1)
        ->where('notifications.data.0.title', 'Pago pendiente'));
});

test('a participant with no linked user account cannot be notified', function () {
    $athlete = Athlete::factory()->create(['user_id' => null]);
    $participant = EventParticipant::factory()->create(['athlete_id' => $athlete->id]);

    $response = $this->actingAs($this->admin)->post("/admin/participants/{$participant->id}/notify", [
        'type' => 'custom',
        'title' => 'Hola',
        'message' => 'Mensaje de prueba.',
    ]);

    $response->assertRedirect();
    expect($athlete->fresh())->not->toBeNull();
});

test('an athlete can only see and mark read their own notifications', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();

    app(SendAthleteNotification::class)->handle($owner, 'Título', 'Mensaje', NotificationType::Custom);
    $notification = $owner->notifications()->first();

    $this->actingAs($stranger)->post("/notifications/{$notification->id}/read")->assertNotFound();

    expect($notification->fresh()->read_at)->toBeNull();
});

test('marking a notification read updates it and clears the unread count', function () {
    $user = User::factory()->create();
    app(SendAthleteNotification::class)->handle($user, 'Título', 'Mensaje', NotificationType::Custom);
    $notification = $user->notifications()->first();

    $this->actingAs($user)->post("/notifications/{$notification->id}/read")->assertRedirect();

    expect($user->fresh()->unreadNotifications()->count())->toBe(0);
});

test('push=true queues one SendPushNotificationJob per active device and none for inactive ones', function () {
    Queue::fake();
    $user = User::factory()->create();
    $activeDevice = PushDevice::create([
        'uuid' => (string) Str::uuid(), 'user_id' => $user->id, 'platform' => 'ios',
        'provider' => 'apns', 'token' => 'tok-active', 'active' => true,
    ]);
    PushDevice::create([
        'uuid' => (string) Str::uuid(), 'user_id' => $user->id, 'platform' => 'ios',
        'provider' => 'apns', 'token' => 'tok-inactive', 'active' => false,
    ]);

    app(SendAthleteNotification::class)->handle($user, 'Título', 'Mensaje', NotificationType::Custom, push: true);

    Queue::assertPushed(SendPushNotificationJob::class, 1);
    Queue::assertPushed(SendPushNotificationJob::class, fn ($job) => $job->device->id === $activeDevice->id);
});

test('push=false never queues a push job even if the user has active devices', function () {
    Queue::fake();
    $user = User::factory()->create();
    PushDevice::create([
        'uuid' => (string) Str::uuid(), 'user_id' => $user->id, 'platform' => 'ios',
        'provider' => 'apns', 'token' => 'tok-1', 'active' => true,
    ]);

    app(SendAthleteNotification::class)->handle($user, 'Título', 'Mensaje', NotificationType::Custom);

    Queue::assertNotPushed(SendPushNotificationJob::class);
});

test('the NullPushNotificationGateway reports not_configured without throwing', function () {
    $device = PushDevice::create([
        'uuid' => (string) Str::uuid(), 'user_id' => User::factory()->create()->id, 'platform' => 'ios',
        'provider' => 'apns', 'token' => 'tok-1', 'active' => true,
    ]);

    $result = app(PushNotificationGateway::class)
        ->send($device, new PushNotificationPayload('Hola', 'Mensaje'));

    expect($result->status)->toBe(PushSendStatus::NotConfigured);
});

test('sending a notification from the Athlete Show page reaches the linked user', function () {
    $athleteUser = User::factory()->create();
    $athlete = Athlete::factory()->create(['user_id' => $athleteUser->id]);

    $this->actingAs($this->admin)->post("/admin/athletes/{$athlete->id}/notify", [
        'type' => 'custom',
        'title' => 'Hola desde Atletas',
        'message' => 'Mensaje de prueba.',
    ])->assertRedirect();

    expect($athleteUser->fresh()->notifications()->count())->toBe(1);
});

test('action_url rejects javascript: and external URLs but accepts an internal relative path', function () {
    $athleteUser = User::factory()->create();
    $athlete = Athlete::factory()->create(['user_id' => $athleteUser->id]);

    $this->actingAs($this->admin)->post("/admin/athletes/{$athlete->id}/notify", [
        'type' => 'custom', 'title' => 'Hola', 'message' => 'Mensaje',
        'action_url' => 'javascript:alert(1)',
    ])->assertSessionHasErrors('action_url');

    $this->actingAs($this->admin)->post("/admin/athletes/{$athlete->id}/notify", [
        'type' => 'custom', 'title' => 'Hola', 'message' => 'Mensaje',
        'action_url' => 'https://evil.example.com/phish',
    ])->assertSessionHasErrors('action_url');

    $this->actingAs($this->admin)->post("/admin/athletes/{$athlete->id}/notify", [
        'type' => 'custom', 'title' => 'Hola', 'message' => 'Mensaje',
        'action_url' => '/dashboard/legado',
    ])->assertSessionDoesntHaveErrors('action_url');

    expect($athleteUser->fresh()->notifications()->latest()->first()->data['action_url'])->toBe('/dashboard/legado');
});

test('the Participant Profile Comunicación tab shows notification history', function () {
    $athleteUser = User::factory()->create();
    $athlete = Athlete::factory()->create(['user_id' => $athleteUser->id]);
    $participant = EventParticipant::factory()->create(['athlete_id' => $athlete->id]);

    app(SendAthleteNotification::class)->handle(
        $athleteUser,
        'Resultado disponible',
        'Ya puedes ver tu tiempo.',
        NotificationType::ResultAvailable,
        sentBy: $this->admin,
    );

    $response = $this->actingAs($this->admin)->get("/admin/participants/{$participant->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/participants/Show')
        ->has('comunicacion', 1)
        ->where('comunicacion.0.title', 'Resultado disponible')
        ->where('canNotify', true));
});
