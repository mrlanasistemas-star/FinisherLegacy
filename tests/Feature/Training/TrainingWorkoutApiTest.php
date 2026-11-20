<?php

/**
 * Modo entrenamiento — `/api/v1/training/workouts/*`: idempotent start,
 * one running workout at a time, heartbeat, finish (idempotent, accepts a
 * late finish after an auto-close), discard, manual entries, owner-only
 * access, honest stats/records, stale auto-close and explicit sharing as a
 * Legacy Moment (private by default).
 */

use App\Enums\MomentType;
use App\Enums\WorkoutStatus;
use App\Models\LegacyMoment;
use App\Models\Sport;
use App\Models\TrainingWorkout;
use App\Models\User;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->running = Sport::factory()->create(['name' => 'Running', 'slug' => 'running']);
    $this->swim = Sport::factory()->create(['name' => 'Natación', 'slug' => 'natacion']);
});

function startLive(User $user, array $overrides = [])
{
    return test()->postJson('/api/v1/training/workouts', [
        'source' => 'live',
        'sport_id' => test()->running->id,
        'uses_gps' => true,
        'client_uuid' => (string) Str::uuid(),
        ...$overrides,
    ], apiAuthHeader($user));
}

test('sports list carries gps default and the pace or speed metric', function () {
    $this->getJson('/api/v1/training/sports', apiAuthHeader(User::factory()->create()))
        ->assertOk()
        ->assertJsonFragment(['slug' => 'running', 'gps_default' => true, 'metric' => 'pace'])
        ->assertJsonFragment(['slug' => 'natacion', 'gps_default' => false]);
});

test('a live start is idempotent by client uuid and refuses a second running workout', function () {
    $user = User::factory()->create();
    $client = (string) Str::uuid();

    $first = startLive($user, ['client_uuid' => $client])->assertCreated()
        ->assertJsonPath('data.status', 'in_progress')
        ->assertJsonPath('data.visibility', 'private');
    $uuid = $first->json('data.uuid');

    startLive($user, ['client_uuid' => $client])->assertOk()->assertJsonPath('data.uuid', $uuid);

    startLive($user)->assertStatus(409)
        ->assertJsonPath('error.details.reason', 'workout_in_progress')
        ->assertJsonPath('error.details.workout_uuid', $uuid);

    $this->getJson('/api/v1/training/workouts/active', apiAuthHeader($user))->assertJsonPath('data.uuid', $uuid);
    expect(TrainingWorkout::query()->count())->toBe(1);
});

test('heartbeat then finish derives pace only from real gps data and keeps plausible best efforts', function () {
    $user = User::factory()->create();
    $uuid = startLive($user, ['started_at' => now()->subMinutes(40)->toIso8601String()])->json('data.uuid');

    $this->patchJson("/api/v1/training/workouts/{$uuid}", ['duration_s' => 600, 'distance_m' => 1800], apiAuthHeader($user))
        ->assertOk()->assertJsonPath('data.distance_m', 1800);

    $this->postJson("/api/v1/training/workouts/{$uuid}/finish", [
        'duration_s' => 2100,
        'moving_s' => 2000,
        'distance_m' => 6000,
        'best_5k_s' => 1600,
        'best_10k_s' => 3000, // route never reached 10 km → dropped
        'route_polyline' => '_p~iF~ps|U_ulLnnqC_mqNvxq`@',
        'route_points_count' => 3,
    ], apiAuthHeader($user))
        ->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.avg_pace_s_per_km', 333)
        ->assertJsonPath('data.best_5k_s', 1600)
        ->assertJsonPath('data.best_10k_s', null);

    // Replay from the offline queue: unchanged.
    $this->postJson("/api/v1/training/workouts/{$uuid}/finish", ['duration_s' => 1], apiAuthHeader($user))
        ->assertOk()->assertJsonPath('data.duration_s', 2100);

    $this->getJson("/api/v1/training/workouts/{$uuid}", apiAuthHeader($user))
        ->assertJsonPath('data.route_polyline', '_p~iF~ps|U_ulLnnqC_mqNvxq`@');
});

test('an indoor workout keeps only the timer', function () {
    $user = User::factory()->create();
    $uuid = startLive($user, ['sport_id' => $this->swim->id, 'uses_gps' => false, 'started_at' => now()->subHour()->toIso8601String()])->json('data.uuid');

    $this->postJson("/api/v1/training/workouts/{$uuid}/finish", ['duration_s' => 1800, 'distance_m' => 5000], apiAuthHeader($user))
        ->assertOk()
        ->assertJsonPath('data.distance_m', null)
        ->assertJsonPath('data.avg_pace_s_per_km', null);
});

test('discard only applies to a running workout', function () {
    $user = User::factory()->create();
    $uuid = startLive($user)->json('data.uuid');

    $this->postJson("/api/v1/training/workouts/{$uuid}/discard", [], apiAuthHeader($user))->assertOk()->assertJsonPath('data.status', 'discarded');
    $this->postJson("/api/v1/training/workouts/{$uuid}/finish", ['duration_s' => 60], apiAuthHeader($user))
        ->assertStatus(409)->assertJsonPath('error.details.reason', 'workout_discarded');
    $this->getJson('/api/v1/training/workouts', apiAuthHeader($user))->assertJsonCount(0, 'data');
});

test('a past activity is registered as manual and never counts for records', function () {
    $user = User::factory()->create();

    $this->postJson('/api/v1/training/workouts', [
        'source' => 'manual',
        'sport_id' => $this->running->id,
        'started_at' => now()->subDays(2)->toIso8601String(),
        'duration_s' => 3600,
        'distance_m' => 12000,
    ], apiAuthHeader($user))
        ->assertCreated()
        ->assertJsonPath('data.source', 'manual')
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.uses_gps', false)
        ->assertJsonPath('data.avg_pace_s_per_km', 300);

    $this->postJson('/api/v1/training/workouts', [
        'source' => 'manual', 'sport_id' => $this->running->id,
        'started_at' => now()->addDay()->toIso8601String(), 'duration_s' => 3600,
    ], apiAuthHeader($user))->assertUnprocessable();

    $this->getJson('/api/v1/training/workouts/stats?period=all', apiAuthHeader($user))
        ->assertOk()
        ->assertJsonPath('data.totals.workouts', 1)
        ->assertJsonPath('data.totals.manual', 1)
        ->assertJsonPath('data.totals.distance_m', 12000)
        ->assertJsonPath('data.records.longest_distance', null)
        ->assertJsonPath('data.records.best_5k', null);
});

test('workouts are owner only and answer 404 to anyone else', function () {
    $owner = User::factory()->create();
    $uuid = startLive($owner)->json('data.uuid');
    $other = User::factory()->create();

    $this->getJson("/api/v1/training/workouts/{$uuid}", apiAuthHeader($other))->assertNotFound();
    $this->patchJson("/api/v1/training/workouts/{$uuid}", ['duration_s' => 5], apiAuthHeader($other))->assertNotFound();
    $this->postJson("/api/v1/training/workouts/{$uuid}/finish", ['duration_s' => 5], apiAuthHeader($other))->assertNotFound();
    $this->deleteJson("/api/v1/training/workouts/{$uuid}", [], apiAuthHeader($other))->assertNotFound();
    $this->getJson('/api/v1/training/workouts/active', apiAuthHeader($other))->assertJsonPath('data', null);

    $this->deleteJson("/api/v1/training/workouts/{$uuid}", [], apiAuthHeader($owner))->assertNoContent();
});

test('stale running workouts are auto closed and a late finish still wins', function () {
    $user = User::factory()->create();
    $uuid = startLive($user)->json('data.uuid');
    TrainingWorkout::query()->where('uuid', $uuid)->update([
        'started_at' => now()->subHours(15),
        'last_heartbeat_at' => now()->subHours(13),
        'duration_s' => 1200,
    ]);
    $empty = TrainingWorkout::query()->create([
        'user_id' => $user->id, 'sport_id' => $this->running->id, 'source' => 'live', 'status' => 'in_progress',
        'started_at' => now()->subHours(20), 'last_heartbeat_at' => now()->subHours(20), 'duration_s' => 5,
    ]);

    $this->artisan('training:close-stale-workouts')->assertSuccessful();

    expect(TrainingWorkout::query()->where('uuid', $uuid)->first()->status)->toBe(WorkoutStatus::Completed)
        ->and($empty->fresh()->status)->toBe(WorkoutStatus::Discarded);

    $this->postJson("/api/v1/training/workouts/{$uuid}/finish", ['duration_s' => 3000, 'ended_at' => now()->subHours(14)->toIso8601String()], apiAuthHeader($user))
        ->assertOk()
        ->assertJsonPath('data.duration_s', 3000)
        ->assertJsonPath('data.auto_closed', false);
});

test('sharing creates one private training moment only when asked', function () {
    $user = User::factory()->create();
    $uuid = startLive($user, ['started_at' => now()->subHour()->toIso8601String()])->json('data.uuid');

    $this->postJson("/api/v1/training/workouts/{$uuid}/share", [], apiAuthHeader($user))
        ->assertStatus(409)->assertJsonPath('error.details.reason', 'workout_not_completed');

    $this->postJson("/api/v1/training/workouts/{$uuid}/finish", ['duration_s' => 1800, 'distance_m' => 5000], apiAuthHeader($user))->assertOk();
    expect(LegacyMoment::query()->count())->toBe(0);

    $this->postJson("/api/v1/training/workouts/{$uuid}/share", ['caption' => 'Buen fondo'], apiAuthHeader($user))
        ->assertCreated()
        ->assertJsonPath('data.moment.visibility', 'private');

    $moment = LegacyMoment::query()->sole();
    expect($moment->type)->toBe(MomentType::Training)
        ->and($moment->metrics['distance_km'])->toEqual(5);

    $this->postJson("/api/v1/training/workouts/{$uuid}/share", [], apiAuthHeader($user))
        ->assertStatus(409)->assertJsonPath('error.details.reason', 'workout_already_shared');
});
