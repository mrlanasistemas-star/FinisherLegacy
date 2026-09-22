<?php

use App\Contracts\Notifications\PushNotificationGateway;
use App\Enums\PushSendStatus;
use App\Models\PushDevice;
use App\Models\User;
use App\Support\Notifications\PushNotificationPayload;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Str;

/**
 * REST equivalent of the Web "Comunicación" send button (consolidation
 * brief §36-§42) — same App\Actions\Notifications\SendAthleteNotification
 * the Web controllers already use.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

test('an admin can send a notification via the REST API', function () {
    $recipient = User::factory()->create();

    $response = $this->withHeaders(apiAuthHeader($this->admin))->postJson("/api/v1/admin/users/{$recipient->id}/notifications", [
        'type' => 'custom',
        'title' => 'Hola desde API',
        'message' => 'Mensaje de prueba.',
    ]);

    $response->assertCreated();
    expect($response->json('data.push_requested'))->toBeFalse()
        ->and($response->json('data.push_available'))->toBeFalse();

    expect($recipient->fresh()->notifications()->count())->toBe(1);
});

test('a non-admin user cannot send a notification via the REST API', function () {
    $stranger = User::factory()->create();
    $recipient = User::factory()->create();

    $this->withHeaders(apiAuthHeader($stranger))->postJson("/api/v1/admin/users/{$recipient->id}/notifications", [
        'type' => 'custom',
        'title' => 'Hola',
        'message' => 'Mensaje',
    ])->assertForbidden();

    expect($recipient->fresh()->notifications()->count())->toBe(0);
});

test('the REST API rejects an unsafe action_url the same way the Web form does', function () {
    $recipient = User::factory()->create();

    $this->withHeaders(apiAuthHeader($this->admin))->postJson("/api/v1/admin/users/{$recipient->id}/notifications", [
        'type' => 'custom', 'title' => 'Hola', 'message' => 'Mensaje',
        'action_url' => 'javascript:alert(1)',
    ])->assertStatus(422);
});

test('push_available reflects the NullPushNotificationGateway honestly', function () {
    $device = PushDevice::create([
        'uuid' => (string) Str::uuid(), 'user_id' => User::factory()->create()->id,
        'platform' => 'ios', 'provider' => 'apns', 'token' => 'tok-1', 'active' => true,
    ]);

    $result = app(PushNotificationGateway::class)
        ->send($device, new PushNotificationPayload('Hola', 'Mensaje'));

    expect($result->status)->toBe(PushSendStatus::NotConfigured)
        ->and(app(PushNotificationGateway::class)->isConfigured())->toBeFalse();
});
