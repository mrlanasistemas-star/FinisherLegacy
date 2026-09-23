<?php

use App\Models\Medal;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

test('a user can list only their own medals', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    Medal::factory()->count(2)->create(['user_id' => $user->id]);
    Medal::factory()->create(['user_id' => $other->id]);

    $response = $this->withHeaders(apiAuthHeader($user))->getJson('/api/v1/medals');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2);
    $response->assertJsonStructure(['data', 'links', 'meta']);
});

test('a user can create a medal via the api using its public uuid, not the incrementing id', function () {
    $user = User::factory()->create();

    $response = $this->withHeaders(apiAuthHeader($user))->postJson('/api/v1/medals', [
        'origin' => 'manual',
        'event_name_manual' => 'Maratón de Prueba',
        'event_date' => '2026-01-01',
        'distance_label' => '21K',
        'visibility' => 'public',
        'front_image' => UploadedFile::fake()->image('front.jpg'),
    ]);

    $response->assertCreated();
    $medal = Medal::where('user_id', $user->id)->firstOrFail();

    expect($response->json('data.id'))->toBe($medal->uuid)
        ->and($response->json('data.id'))->not->toBe((string) $medal->id);
});

test('a user cannot view another users medal', function () {
    $owner = User::factory()->create();
    $medal = Medal::factory()->create(['user_id' => $owner->id]);
    $intruder = User::factory()->create();

    $this->withHeaders(apiAuthHeader($intruder))
        ->getJson("/api/v1/medals/{$medal->uuid}")
        ->assertForbidden();
});

test('a user can update their own medal', function () {
    $user = User::factory()->create();
    $medal = Medal::factory()->create(['user_id' => $user->id, 'event_participant_id' => null]);

    $response = $this->withHeaders(apiAuthHeader($user))->patchJson("/api/v1/medals/{$medal->uuid}", [
        'story' => 'Una historia actualizada.',
        'visibility' => 'private',
    ]);

    $response->assertOk();
    expect($medal->fresh()->story)->toBe('Una historia actualizada.');
});

test('a user can delete (archive) their own medal', function () {
    $user = User::factory()->create();
    $medal = Medal::factory()->create(['user_id' => $user->id]);

    $this->withHeaders(apiAuthHeader($user))
        ->deleteJson("/api/v1/medals/{$medal->uuid}")
        ->assertOk();

    expect(Medal::find($medal->id))->toBeNull();
    expect(Medal::withTrashed()->find($medal->id))->not->toBeNull();
});

test('medal endpoints require authentication', function () {
    $this->getJson('/api/v1/medals')->assertUnauthorized();
});
