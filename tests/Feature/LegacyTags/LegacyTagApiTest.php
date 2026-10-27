<?php

use App\Enums\LegacyTagStatus;
use App\Enums\StoryVisibility;
use App\Models\Athlete;
use App\Models\EventParticipant;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

require_once __DIR__.'/story-helpers.php';

test('GET /tags/{code} resolves a fresh tag without PII', function () {
    $tag = deliveredTag();

    $response = $this->getJson('/api/v1/tags/'.strtolower($tag->displayCode()))
        ->assertOk()
        ->assertJsonPath('data.code', $tag->public_code)
        ->assertJsonPath('data.status', 'activatable')
        ->assertJsonPath('data.can_activate', true)
        ->assertJsonPath('data.owned_by_me', false)
        ->assertJsonPath('data.story_url', null)
        ->assertJsonPath('data.tag.collection.name', 'Cozumel 2026')
        ->assertJsonStructure(['data' => ['tag' => ['collection', 'design' => ['product_name', 'variant_name', 'image_url']]], 'meta']);

    expect(json_encode($response->json('data')))->not->toContain($tag->serial)->not->toContain('owner');
});

test('GET /tags/{code} 404s for unknown or malformed codes', function () {
    $this->getJson('/api/v1/tags/ZZZZZZZZZZZZ')->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');
    $this->getJson('/api/v1/tags/OOOOOOOOOOOO')->assertNotFound();
});

test('POST /tags/{code}/activate returns the owner story resource and is idempotent', function () {
    $user = User::factory()->create();
    $athlete = Athlete::factory()->create(['user_id' => $user->id]);
    $participant = EventParticipant::factory()->create(['athlete_id' => $athlete->id]);
    $tag = deliveredTag();
    Sanctum::actingAs($user);

    $this->postJson("/api/v1/tags/{$tag->public_code}/activate", ['source' => 'qr', 'race_mode' => 'participation', 'event_participant_id' => $participant->id])
        ->assertOk()
        ->assertJsonPath('data.is_owner', true)
        ->assertJsonPath('data.event.source', 'participation')
        ->assertJsonPath('data.visibility', 'unlisted')
        ->assertJsonPath('data.tag.serial', $tag->serial)
        ->assertJsonPath('data.tag.display_code', $tag->displayCode())
        ->assertJsonStructure(['data' => [
            'public_id', 'title', 'event' => ['name', 'edition', 'distance', 'result_time', 'date', 'location'],
            'dedication', 'recipient_label', 'visibility', 'cover_url', 'photos', 'audio_url', 'certificate_url',
            'tag' => ['serial', 'display_code', 'collection', 'design'], 'web_url', 'shares',
        ]]);

    $this->postJson("/api/v1/tags/{$tag->public_code}/activate")->assertOk();

    $this->getJson("/api/v1/tags/{$tag->public_code}")
        ->assertJsonPath('data.status', 'mine')
        ->assertJsonPath('data.owned_by_me', true);

    expect($tag->fresh()->status)->toBe(LegacyTagStatus::Activated);
});

test('activation API errors use the unified envelope', function () {
    [, , $tag] = ownedStory();
    Sanctum::actingAs(User::factory()->create());

    $this->postJson("/api/v1/tags/{$tag->public_code}/activate")
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'TAG_ALREADY_ACTIVATED');

    $disabled = deliveredTag(['status' => LegacyTagStatus::Disabled]);
    $this->postJson("/api/v1/tags/{$disabled->public_code}/activate")
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'TAG_NOT_ACTIVATABLE')
        ->assertJsonPath('error.details.reason', 'disabled');
});

test('activation API requires a token', function () {
    $tag = deliveredTag();

    $this->postJson("/api/v1/tags/{$tag->public_code}/activate")->assertUnauthorized();
    $this->getJson('/api/v1/me/stories')->assertUnauthorized();
});

test('GET /me/stories lists only my stories', function () {
    [$mine, $owner] = ownedStory();
    ownedStory();
    Sanctum::actingAs($owner);

    $this->getJson('/api/v1/me/stories')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.public_id', $mine->public_id);
});

test('GET /stories/{publicId} follows the visibility policy', function () {
    [$private, $owner] = ownedStory(StoryVisibility::Private);
    [$unlisted] = ownedStory(StoryVisibility::Unlisted);

    $this->getJson("/api/v1/stories/{$unlisted->public_id}")
        ->assertOk()
        ->assertJsonPath('data.is_owner', false)
        ->assertJsonPath('data.tag.serial', null)
        ->assertJsonPath('data.shares', []);

    $this->getJson("/api/v1/stories/{$private->public_id}")->assertNotFound();

    Sanctum::actingAs($owner);
    $this->getJson("/api/v1/stories/{$private->public_id}")->assertOk()->assertJsonPath('data.is_owner', true);
});
