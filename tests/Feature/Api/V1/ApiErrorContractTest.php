<?php

use App\Models\Organizer;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

test('an unauthenticated request to a protected route gets the unified error envelope', function () {
    $response = $this->getJson('/api/v1/me');

    $response->assertUnauthorized();
    $response->assertJsonStructure(['error' => ['code', 'message', 'details'], 'request_id']);
    expect($response->json('error.code'))->toBe('UNAUTHENTICATED');
});

test('a forbidden request gets the unified error envelope with FORBIDDEN', function () {
    $user = User::factory()->create();
    $headers = ['Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken];
    $organizer = Organizer::factory()->create();

    $response = $this->withHeaders($headers)->getJson("/api/v1/admin/organizers/{$organizer->id}/data-source");

    $response->assertForbidden();
    expect($response->json('error.code'))->toBe('FORBIDDEN');
});

test('a not-found model gets the unified error envelope with NOT_FOUND', function () {
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    $headers = ['Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken];

    $response = $this->withHeaders($headers)->getJson('/api/v1/admin/organizers/999999/data-source');

    $response->assertStatus(404);
    expect($response->json('error.code'))->toBe('NOT_FOUND');
});

test('validation errors keep the standard Laravel shape, not the unified error envelope', function () {
    $response = $this->postJson('/api/v1/auth/login', ['email' => 'not-an-email']);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('email');
    $response->assertJsonMissingPath('error');
});

test('every response echoes an X-Request-ID header', function () {
    $response = $this->getJson('/api/v1/health');

    $response->assertHeader('X-Request-ID');
});

test('a client-supplied X-Request-ID is echoed back unchanged', function () {
    $response = $this->withHeaders(['X-Request-ID' => 'my-correlation-id'])->getJson('/api/v1/health');

    $response->assertHeader('X-Request-ID', 'my-correlation-id');
});
