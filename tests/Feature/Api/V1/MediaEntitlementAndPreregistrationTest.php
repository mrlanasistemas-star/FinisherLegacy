<?php

use App\Actions\Athletes\EnsureAthleteForUser;
use App\Enums\EditionStatus;
use App\Enums\EventStatus;
use App\Models\Athlete;
use App\Models\AthleteEventMedia;
use App\Models\EventEdition;
use App\Models\EventParticipant;
use App\Models\EventRace;
use App\Models\MediaEntitlement;
use App\Models\User;
use Illuminate\Support\Str;

function mediaRow(EventParticipant $participant, int $sort): AthleteEventMedia
{
    return AthleteEventMedia::create([
        'uuid' => (string) Str::uuid(),
        'athlete_id' => $participant->athlete_id,
        'event_participant_id' => $participant->id,
        'type' => 'image',
        'disk' => 'athlete_media',
        'path' => "event-media/{$participant->id}/{$sort}.jpg",
        'mime' => 'image/jpeg',
        'size_bytes' => 1000,
        'checksum' => str_repeat('a', 64),
        'sort_order' => $sort,
    ]);
}

test('the media entitlement endpoint reports used/limit/remaining from the backend config', function () {
    $user = User::factory()->create();
    $athlete = app(EnsureAthleteForUser::class)->handle($user, 'test');
    $participant = EventParticipant::factory()->create(['athlete_id' => $athlete->id]);
    mediaRow($participant, 0);

    $this->withHeaders(apiAuthHeader($user))->getJson("/api/v1/me/events/{$participant->id}/media-entitlement")
        ->assertOk()
        ->assertJsonPath('data.images.used', 1)
        ->assertJsonPath('data.images.limit', 5)
        ->assertJsonPath('data.images.remaining', 4)
        ->assertJsonPath('data.videos.limit', 1)
        ->assertJsonPath('data.memory_packs_enabled', false);

    expect($this->withHeaders(apiAuthHeader($user))->getJson("/api/v1/me/events/{$participant->id}/media-entitlement")->json('data.videos.allowed_mimes'))
        ->toContain('video/quicktime');
});

test('memory pack entitlements add to the free tier only when the feature is on', function () {
    $user = User::factory()->create();
    $athlete = app(EnsureAthleteForUser::class)->handle($user, 'test');
    $participant = EventParticipant::factory()->create(['athlete_id' => $athlete->id]);
    MediaEntitlement::create(['user_id' => $user->id, 'event_participant_id' => $participant->id, 'extra_images' => 20]);

    $this->withHeaders(apiAuthHeader($user))->getJson("/api/v1/me/events/{$participant->id}/media-entitlement")
        ->assertJsonPath('data.images.limit', 5);

    config(['finisher.memory_packs.enabled' => true]);

    $this->withHeaders(apiAuthHeader($user))->getJson("/api/v1/me/events/{$participant->id}/media-entitlement")
        ->assertJsonPath('data.images.limit', 25);
});

test('event media can be reordered by uuid', function () {
    $user = User::factory()->create();
    $athlete = app(EnsureAthleteForUser::class)->handle($user, 'test');
    $participant = EventParticipant::factory()->create(['athlete_id' => $athlete->id]);
    $first = mediaRow($participant, 0);
    $second = mediaRow($participant, 1);

    $this->withHeaders(apiAuthHeader($user))
        ->postJson("/api/v1/me/events/{$participant->id}/media/reorder", ['media_uuids' => [$second->uuid, $first->uuid]])
        ->assertOk()
        ->assertJsonPath('data.0.uuid', $second->uuid);
});

test('reordering rejects media that belongs to another participation', function () {
    $user = User::factory()->create();
    $athlete = app(EnsureAthleteForUser::class)->handle($user, 'test');
    $participant = EventParticipant::factory()->create(['athlete_id' => $athlete->id]);
    $foreign = mediaRow(EventParticipant::factory()->create(['athlete_id' => Athlete::factory()]), 0);

    $this->withHeaders(apiAuthHeader($user))
        ->postJson("/api/v1/me/events/{$participant->id}/media/reorder", ['media_uuids' => [$foreign->uuid]])
        ->assertStatus(422);
});

test('every race gets a public uuid automatically', function () {
    expect(EventRace::factory()->create()->uuid)->toBeString()->not->toBeEmpty();
});

test('a preregistration can target a race by its public uuid, as the event detail exposes it', function () {
    $edition = EventEdition::factory()->create([
        'status' => EditionStatus::Published,
        'event_date' => now()->addMonth(),
        'registration_open_at' => now()->subDay(),
        'registration_close_at' => now()->addWeek(),
    ]);
    $edition->event()->update(['status' => EventStatus::Published]);
    $race = EventRace::factory()->create(['event_edition_id' => $edition->id, 'active' => true]);

    $detail = $this->getJson("/api/v1/events/{$edition->event->slug}")->assertOk();
    expect($detail->json('data.edition.id'))->toBe($edition->id)
        ->and($detail->json('data.edition.races.0.uuid'))->toBe($race->uuid)
        ->and($detail->json('data.edition.preregistration_open'))->toBeTrue();

    $this->postJson("/api/v1/events/{$edition->id}/preregister", [
        'event_race_uuid' => $race->uuid,
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'email' => 'ada@example.com',
    ])->assertCreated();

    // A race of another edition is never accepted.
    $foreignRace = EventRace::factory()->create();
    $this->postJson("/api/v1/events/{$edition->id}/preregister", [
        'event_race_uuid' => $foreignRace->uuid, 'first_name' => 'A', 'last_name' => 'B', 'email' => 'b@example.com',
    ])->assertNotFound();
});
