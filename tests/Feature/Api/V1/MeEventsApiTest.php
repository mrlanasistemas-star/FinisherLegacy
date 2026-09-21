<?php

use App\Actions\Athletes\EnsureAthleteForUser;
use App\Models\Athlete;
use App\Models\EventParticipant;
use App\Models\User;

test('GET /api/v1/me/events returns a paginated, participation-centric history', function () {
    $user = User::factory()->create();
    $athlete = app(EnsureAthleteForUser::class)->handle($user, 'test');
    EventParticipant::factory()->count(3)->create(['athlete_id' => $athlete->id]);

    $response = $this->withHeaders(['Authorization' => 'Bearer '.$user->createToken('t')->plainTextToken])
        ->getJson('/api/v1/me/events?per_page=2');

    $response->assertOk()
        ->assertJsonStructure(['data' => ['data', 'links'], 'meta'])
        ->assertJsonCount(2, 'data.data')
        ->assertJsonStructure(['data' => ['data' => [['id', 'event', 'result', 'legacy_plate_status', 'medal_count', 'gear_count', 'media_count']]]]);
});

test('GET /api/v1/me/history is the same filterable read model as /me/events', function () {
    $user = User::factory()->create();
    $athlete = app(EnsureAthleteForUser::class)->handle($user, 'test');
    EventParticipant::factory()->create(['athlete_id' => $athlete->id]);

    $response = $this->withHeaders(['Authorization' => 'Bearer '.$user->createToken('t')->plainTextToken])
        ->getJson('/api/v1/me/history');

    $response->assertOk()->assertJsonCount(1, 'data.data');
});

test('GET /api/v1/me/history filters participations by event_race_id', function () {
    $user = User::factory()->create();
    $athlete = app(EnsureAthleteForUser::class)->handle($user, 'test');
    $matching = EventParticipant::factory()->create(['athlete_id' => $athlete->id]);
    EventParticipant::factory()->create(['athlete_id' => $athlete->id]);

    $response = $this->withHeaders(['Authorization' => 'Bearer '.$user->createToken('t')->plainTextToken])
        ->getJson('/api/v1/me/history?event_race_id='.$matching->event_race_id);

    $response->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.id', $matching->id);
});

test('GET /api/v1/me/events/{participant} returns the same detail shape as the Web Mi Legado page', function () {
    $user = User::factory()->create();
    $athlete = app(EnsureAthleteForUser::class)->handle($user, 'test');
    $participant = EventParticipant::factory()->create(['athlete_id' => $athlete->id]);

    $response = $this->withHeaders(['Authorization' => 'Bearer '.$user->createToken('t')->plainTextToken])
        ->getJson("/api/v1/me/events/{$participant->id}");

    $response->assertOk()->assertJsonStructure([
        'data' => ['participant', 'result', 'medals', 'legacyPlate', 'plates', 'media', 'gearUsed', 'purchases', 'supportSession'],
    ]);
});

test('GET /api/v1/me/events/{participant} rejects a participant belonging to another athlete', function () {
    $user = User::factory()->create();
    app(EnsureAthleteForUser::class)->handle($user, 'test');
    $otherAthlete = Athlete::factory()->create();
    $participant = EventParticipant::factory()->create(['athlete_id' => $otherAthlete->id]);

    $response = $this->withHeaders(['Authorization' => 'Bearer '.$user->createToken('t')->plainTextToken])
        ->getJson("/api/v1/me/events/{$participant->id}");

    $response->assertForbidden();
});
