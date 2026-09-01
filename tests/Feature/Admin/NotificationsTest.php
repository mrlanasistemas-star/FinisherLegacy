<?php

use App\Actions\Notifications\SendAthleteNotification;
use App\Enums\NotificationType;
use App\Models\Athlete;
use App\Models\EventParticipant;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

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
