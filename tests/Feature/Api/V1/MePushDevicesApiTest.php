<?php

use App\Models\PushDevice;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * `/api/v1/me/push-devices*` (product consolidation brief §32) —
 * backend-only token registry; sending an actual push notification is
 * decided in the Mobile repo, never here.
 */
test('POST /api/v1/me/push-devices registers a new device', function () {
    $user = User::factory()->create();
    $headers = ['Authorization' => 'Bearer '.$user->createToken('t')->plainTextToken];

    $response = $this->withHeaders($headers)->postJson('/api/v1/me/push-devices', [
        'platform' => 'ios',
        'provider' => 'apns',
        'token' => 'device-token-123',
        'device_name' => 'iPhone de Jesús',
    ]);

    $response->assertCreated();
    expect(PushDevice::where('user_id', $user->id)->count())->toBe(1);
});

test('registering the same token again updates the existing device instead of duplicating it', function () {
    $user = User::factory()->create();
    $headers = ['Authorization' => 'Bearer '.$user->createToken('t')->plainTextToken];

    $this->withHeaders($headers)->postJson('/api/v1/me/push-devices', [
        'platform' => 'android', 'provider' => 'fcm', 'token' => 'same-token',
    ])->assertCreated();

    $this->withHeaders($headers)->postJson('/api/v1/me/push-devices', [
        'platform' => 'android', 'provider' => 'fcm', 'token' => 'same-token',
    ])->assertCreated();

    $devices = PushDevice::where('user_id', $user->id)->get();

    expect($devices)->toHaveCount(1);
    expect($devices->first()->token)->toBe('same-token');
});

test('DELETE /api/v1/me/push-devices/{uuid} unregisters a device', function () {
    $user = User::factory()->create();
    $headers = ['Authorization' => 'Bearer '.$user->createToken('t')->plainTextToken];
    $this->withHeaders($headers)->postJson('/api/v1/me/push-devices', [
        'platform' => 'web', 'provider' => 'webpush', 'token' => 'web-token',
    ]);
    $uuid = PushDevice::where('user_id', $user->id)->firstOrFail()->uuid;

    $this->withHeaders($headers)->deleteJson("/api/v1/me/push-devices/{$uuid}")->assertOk();

    expect(PushDevice::where('user_id', $user->id)->count())->toBe(0);
});

test('a user cannot delete another user\'s push device', function () {
    // The owner's device is seeded directly (not via an authenticated HTTP
    // call) — Laravel's test HTTP client reuses one booted application
    // instance across calls in a single test, and Sanctum's guard caches
    // its resolved user on that instance; making two different users
    // authenticate via sequential calls in one test can read back the
    // first user's cached resolution instead of re-checking the second
    // Bearer token. Real requests never share that in-process state, so
    // this only ever matters for how the test itself is written.
    $owner = User::factory()->create();
    $device = PushDevice::create([
        'uuid' => (string) Str::uuid(),
        'user_id' => $owner->id,
        'platform' => 'ios',
        'provider' => 'apns',
        'token' => 'owner-token',
        'active' => true,
    ]);

    $intruder = User::factory()->create();
    $intruderHeaders = ['Authorization' => 'Bearer '.$intruder->createToken('t')->plainTextToken];

    $this->withHeaders($intruderHeaders)->deleteJson("/api/v1/me/push-devices/{$device->uuid}")->assertNotFound();

    expect(PushDevice::where('user_id', $owner->id)->count())->toBe(1);
});
