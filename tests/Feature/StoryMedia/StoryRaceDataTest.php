<?php

use App\Enums\ResultStatus;
use App\Enums\StoryVisibility;
use App\Models\EventParticipant;
use App\Models\EventResult;
use App\Models\Sport;
use App\Models\User;
use App\Support\LegacyTags\StoryPresenter;
use App\Support\LegacyTags\StoryRaceData;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

require_once __DIR__.'/story-media-helpers.php';

/*
| Race data on the memory: the new fields, official results winning over
| what the athlete typed, the suggested pace, and the owner API
| (GET|PATCH /api/v1/me/stories/{publicId}).
*/

beforeEach(function () {
    Storage::fake('media_tmp');
    Storage::fake('local');
    Bus::fake();
});

test('the athlete types the race data and the story shows it', function () {
    [$story, $owner] = ownedStory();
    $sport = Sport::factory()->create(['name' => 'Trail', 'active' => true]);

    $this->actingAs($owner)->patch(route('my-legacy.update', $story->public_id), [
        'visibility' => 'unlisted',
        'race_mode' => 'manual',
        'event_name' => 'Maratón de prueba',
        'distance' => '42.195 km',
        'result_time' => '3:45:10',
        'bib_number' => '1234',
        'athlete_display_name' => 'Ana "La Flecha" Ruiz',
        'pace' => '5:20 min/km',
        'category' => 'Libre femenil',
        'category_position' => '12',
        'gender_position' => '40',
        'finish_status' => 'finished',
        'elevation_gain_m' => 850,
        'sport_id' => $sport->id,
    ])->assertSessionHasNoErrors();

    $race = app(StoryPresenter::class)->story($story->fresh())['race'];

    expect($race)->toMatchArray([
        'source' => 'manual',
        'bib_number' => '1234',
        'athlete_name' => 'Ana "La Flecha" Ruiz',
        'pace' => '5:20 min/km',
        'suggested_pace' => '5:20 min/km',
        'category' => 'Libre femenil',
        'category_position' => '12',
        'gender_position' => '40',
        'finish_status' => 'finished',
        'elevation_gain_m' => 850,
        'sport' => 'Trail',
    ])->and(array_filter($race['official']))->toBe([]);
});

test('the display name defaults to the athlete full name and field lengths are enforced', function () {
    [$story, $owner] = ownedStory();

    expect(StoryRaceData::for($story)['values']['athlete_display_name'])->toBe($story->athlete->full_name);

    $this->actingAs($owner)->patch(route('my-legacy.update', $story->public_id), [
        'visibility' => 'unlisted',
        'bib_number' => str_repeat('9', 13),
        'athlete_display_name' => str_repeat('a', 81),
        'pace' => str_repeat('1', 21),
        'category' => str_repeat('c', 41),
        'finish_status' => 'ganó',
    ])->assertSessionHasErrors(['bib_number', 'athlete_display_name', 'pace', 'category', 'finish_status']);
});

test('the pace is suggested from distance and time when both parse', function () {
    expect(StoryRaceData::suggestPace('10 km', '50:00'))->toBe('5:00 min/km')
        ->and(StoryRaceData::suggestPace('21.0975 km', '1:45:30'))->toBe('5:00 min/km')
        ->and(StoryRaceData::suggestPace('21K', '2:06:00'))->toBe('6:00 min/km')
        ->and(StoryRaceData::suggestPace('13.1 mi', '1:44:48'))->toBe('8:00 min/mi')
        ->and(StoryRaceData::suggestPace('Maratón', '3:45:10'))->toBeNull()
        ->and(StoryRaceData::suggestPace('10 km', 'rápido'))->toBeNull()
        ->and(StoryRaceData::suggestPace(null, '50:00'))->toBeNull()
        // Not a human pace: the inputs were not a race distance + time.
        ->and(StoryRaceData::suggestPace('100 km', '0:30'))->toBeNull();
});

test('official results win over what the athlete typed; fields without an official record keep the typed value', function () {
    [$story, $owner] = ownedStory();
    $participant = EventParticipant::factory()->create(['athlete_id' => $story->athlete_id, 'bib_number' => '777', 'category' => 'Master A']);
    EventResult::factory()->create([
        'event_participant_id' => $participant->id,
        'official_time' => '01:45:30',
        'pace' => '5:00',
        'overall_position' => 120,
        'category_position' => 9,
        'gender_position' => null,
        'status' => ResultStatus::Finished,
    ]);

    // Typed before / next to the official record.
    $story->forceFill([
        'event_participant_id' => $participant->id,
        'bib_number' => '1',
        'category' => 'Otra',
        'pace' => '4:00 min/km',
        'category_position' => '1',
        'gender_position' => '33',
        'athlete_display_name' => 'Mi apodo',
        'elevation_gain_m' => 300,
    ])->save();

    $data = StoryRaceData::for($story->fresh());

    expect($data['values'])->toMatchArray([
        'bib_number' => '777',
        'category' => 'Master A',
        'pace' => '5:00',
        'result_time' => '01:45:30',
        'result_position' => '120',
        'category_position' => '9',
        'finish_status' => 'finished',
        // No official record → what the athlete typed.
        'gender_position' => '33',
        'athlete_display_name' => 'Mi apodo',
        'elevation_gain_m' => 300,
    ])->and($data['official'])->toMatchArray([
        'bib_number' => true, 'category' => true, 'pace' => true, 'result_time' => true, 'result_position' => true,
        'category_position' => true, 'finish_status' => true,
        'gender_position' => false, 'athlete_display_name' => false, 'elevation_gain_m' => false,
    ]);

    // An edit of an official field is ignored; a non-official one is stored.
    $this->patchJson("/api/v1/me/stories/{$story->public_id}", ['bib_number' => '999', 'gender_position' => '34'], apiAuthHeader($owner))
        ->assertOk()
        ->assertJsonPath('data.bib_number', '777')
        ->assertJsonPath('data.gender_position', '34')
        ->assertJsonPath('data.official.bib_number', true)
        ->assertJsonPath('data.official.gender_position', false);

    expect($story->fresh()->bib_number)->toBe('1');
});

test('api: the owner reads and edits the full story; anyone else gets 404', function () {
    [$story, $owner] = ownedStory(StoryVisibility::Public);

    $this->getJson("/api/v1/me/stories/{$story->public_id}", apiAuthHeader($owner))
        ->assertOk()
        ->assertJsonStructure(['data' => [
            'public_id', 'title', 'event_name', 'distance', 'result_time', 'result_position', 'bib_number', 'athlete_display_name',
            'pace', 'suggested_pace', 'category', 'category_position', 'gender_position', 'finish_status', 'elevation_gain_m', 'sport',
            'race_date', 'location', 'dedication', 'body', 'visibility', 'official', 'race_source', 'share_url', 'cover_url',
        ]])
        ->assertJsonPath('data.share_url', $story->publicUrl())
        ->assertJsonPath('data.official.bib_number', false);

    $this->patchJson("/api/v1/me/stories/{$story->public_id}", [
        'title' => 'Mi primer maratón',
        'event_name' => 'Maratón de prueba',
        'distance' => '42.195 km',
        'result_time' => '3:30:00',
        'result_position' => '250',
        'bib_number' => 'A-88',
        'athlete_display_name' => 'Ana Ruiz',
        'category' => 'Libre',
        'category_position' => '20',
        'gender_position' => '60',
        'race_date' => '2026-03-01',
        'location' => 'Ciudad de prueba',
        'dedication' => 'Para mamá',
        'body' => 'Lo logré.',
        'visibility' => 'private',
    ], apiAuthHeader($owner))
        ->assertOk()
        ->assertJsonPath('data.title', 'Mi primer maratón')
        ->assertJsonPath('data.bib_number', 'A-88')
        ->assertJsonPath('data.suggested_pace', '4:59 min/km')
        ->assertJsonPath('data.race_source', 'manual')
        ->assertJsonPath('data.visibility', 'private');

    $fresh = $story->fresh();
    expect($fresh->event_name)->toBe('Maratón de prueba')
        ->and($fresh->visibility)->toBe(StoryVisibility::Private)
        ->and($fresh->dedication)->toBe('Para mamá');

    // A partial PATCH only touches what it sends.
    $this->patchJson("/api/v1/me/stories/{$story->public_id}", ['pace' => '5:00 min/km'], apiAuthHeader($owner))
        ->assertOk()
        ->assertJsonPath('data.pace', '5:00 min/km')
        ->assertJsonPath('data.title', 'Mi primer maratón');

    $this->patchJson("/api/v1/me/stories/{$story->public_id}", ['bib_number' => str_repeat('1', 13)], apiAuthHeader($owner))
        ->assertStatus(422)
        ->assertJsonValidationErrors('bib_number');

    $stranger = apiAuthHeader(User::factory()->create());
    $this->getJson("/api/v1/me/stories/{$story->public_id}", $stranger)->assertNotFound();
    $this->patchJson("/api/v1/me/stories/{$story->public_id}", ['title' => 'x'], $stranger)->assertNotFound();
});

test('api: the story editor requires a token', function () {
    [$story] = ownedStory(StoryVisibility::Public);

    $this->getJson("/api/v1/me/stories/{$story->public_id}")->assertUnauthorized();
    $this->getJson("/api/v1/me/stories/{$story->public_id}/media")->assertUnauthorized();
    $this->getJson('/api/v1/me/purchased-photos')->assertUnauthorized();
});

test('the public story resource carries the race data', function () {
    [$story] = ownedStory(StoryVisibility::Public);
    $story->forceFill(['event_name' => 'Carrera', 'distance' => '10 km', 'result_time' => '50:00', 'bib_number' => '55', 'category' => 'Libre'])->save();

    $this->getJson("/api/v1/stories/{$story->public_id}")
        ->assertOk()
        ->assertJsonPath('data.event.bib_number', '55')
        ->assertJsonPath('data.event.category', 'Libre')
        ->assertJsonPath('data.event.pace', '5:00 min/km')
        ->assertJsonPath('data.videos', []);

    $this->get(route('legacy.story.show', $story->public_id))->assertOk();
});
