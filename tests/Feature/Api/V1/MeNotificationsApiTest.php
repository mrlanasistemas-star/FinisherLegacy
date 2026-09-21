<?php

use App\Actions\Notifications\SendAthleteNotification;
use App\Enums\NotificationType;
use App\Models\User;

/**
 * `GET/POST /api/v1/me/notifications*` (product consolidation brief §31)
 * — same Laravel database notifications the Web inbox
 * (App\Http\Controllers\NotificationController) already reads, never a
 * second notification system.
 */
test('GET /api/v1/me/notifications lists the authenticated user\'s notifications', function () {
    $user = User::factory()->create();
    app(SendAthleteNotification::class)->handle(
        recipient: $user,
        title: 'Pago pendiente',
        message: 'Completa el pago de tu Legacy Plate.',
        type: NotificationType::PaymentPending,
    );

    $response = $this->withHeaders(['Authorization' => 'Bearer '.$user->createToken('t')->plainTextToken])
        ->getJson('/api/v1/me/notifications');

    $response->assertOk()->assertJsonCount(1, 'data.data');
});

test('POST /api/v1/me/notifications/{id}/read marks a single notification read', function () {
    $user = User::factory()->create();
    app(SendAthleteNotification::class)->handle(
        recipient: $user,
        title: 'Pago pendiente',
        message: 'Completa el pago de tu Legacy Plate.',
        type: NotificationType::PaymentPending,
    );
    $notificationId = $user->notifications()->first()->id;
    $headers = ['Authorization' => 'Bearer '.$user->createToken('t')->plainTextToken];

    $this->withHeaders($headers)->postJson("/api/v1/me/notifications/{$notificationId}/read")->assertOk();

    expect($user->fresh()->unreadNotifications()->count())->toBe(0);
});

test('a user cannot mark another user\'s notification as read', function () {
    $owner = User::factory()->create();
    app(SendAthleteNotification::class)->handle(
        recipient: $owner,
        title: 'Pago pendiente',
        message: 'Completa el pago de tu Legacy Plate.',
        type: NotificationType::PaymentPending,
    );
    $notificationId = $owner->notifications()->first()->id;

    $intruder = User::factory()->create();
    $headers = ['Authorization' => 'Bearer '.$intruder->createToken('t')->plainTextToken];

    $this->withHeaders($headers)->postJson("/api/v1/me/notifications/{$notificationId}/read")->assertNotFound();

    expect($owner->fresh()->unreadNotifications()->count())->toBe(1);
});

test('POST /api/v1/me/notifications/read-all marks every unread notification read', function () {
    $user = User::factory()->create();
    app(SendAthleteNotification::class)->handle($user, 'A', 'A', NotificationType::PaymentPending);
    app(SendAthleteNotification::class)->handle($user, 'B', 'B', NotificationType::PaymentPending);

    $headers = ['Authorization' => 'Bearer '.$user->createToken('t')->plainTextToken];
    $this->withHeaders($headers)->postJson('/api/v1/me/notifications/read-all')->assertOk();

    expect($user->fresh()->unreadNotifications()->count())->toBe(0);
});
