<?php

use App\Events\TrackingPointRecorded;
use App\Models\TrackingPoint;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Tests\Feature\Tracking\TrackingFixtures;

beforeEach(function () {
    [$this->user, $this->participant] = TrackingFixtures::athleteWithParticipant();
    $this->session = TrackingFixtures::session($this->participant);
    $this->travel(30)->minutes();
    $this->url = "/api/v1/tracking/sessions/{$this->session->uuid}/points";
});

test('a valid batch is accepted, stored and moves the session snapshot', function () {
    $points = TrackingFixtures::track(11, metersPerStep: 30, seconds: 10);

    $this->postJson($this->url, ['points' => $points], apiAuthHeader($this->user))
        ->assertStatus(202)
        ->assertJsonPath('data.accepted', 11)
        ->assertJsonStructure(['data' => ['accepted', 'last_recorded_at']]);

    $session = $this->session->fresh();
    expect(TrackingPoint::where('tracking_session_id', $session->id)->count())->toBe(11)
        ->and($session->distance_m)->toBeGreaterThanOrEqual(295)->toBeLessThanOrEqual(305)
        ->and($session->points_count)->toBe(11)
        ->and($session->last_battery)->toBe(80)
        ->and($session->last_lat)->toEqualWithDelta(19.4 + 300 / 111_195, 0.00001);
});

test('distance accumulates incrementally across batches', function () {
    $points = TrackingFixtures::track(21, metersPerStep: 50, seconds: 10);

    $this->postJson($this->url, ['points' => array_slice($points, 0, 11)], apiAuthHeader($this->user))->assertStatus(202);
    $this->postJson($this->url, ['points' => array_slice($points, 10)], apiAuthHeader($this->user))
        ->assertStatus(202)
        ->assertJsonPath('data.accepted', 10); // first point of 2nd batch is a duplicate

    expect($this->session->fresh()->distance_m)->toBeGreaterThanOrEqual(995)->toBeLessThanOrEqual(1005);
});

test('duplicates are dropped within a batch and against stored points (idempotent retry)', function () {
    $points = TrackingFixtures::track(5);
    $withDupes = [...$points, $points[2], $points[4]];

    $this->postJson($this->url, ['points' => $withDupes], apiAuthHeader($this->user))->assertJsonPath('data.accepted', 5);
    $this->postJson($this->url, ['points' => $points], apiAuthHeader($this->user))->assertJsonPath('data.accepted', 0);

    expect(TrackingPoint::count())->toBe(5)
        ->and($this->session->fresh()->points_count)->toBe(5);
});

test('impossible jumps and low-accuracy points never add distance', function () {
    $points = TrackingFixtures::track(5, metersPerStep: 20, seconds: 10);
    // A 5 km teleport 10 s later, then back on track.
    $jump = ['lat' => 19.45, 'lng' => -99.1, 'accuracy' => 5, 'recorded_at' => now()->subSeconds(5)->toIso8601String()];
    $blurry = ['lat' => 19.41, 'lng' => -99.1, 'accuracy' => 400, 'recorded_at' => now()->subSeconds(3)->toIso8601String()];

    $this->postJson($this->url, ['points' => [...$points, $jump, $blurry]], apiAuthHeader($this->user))
        ->assertJsonPath('data.accepted', 7);

    expect($this->session->fresh()->distance_m)->toBeGreaterThanOrEqual(78)->toBeLessThanOrEqual(82);
});

test('points outside the recorded_at window are dropped', function () {
    $tooOld = ['lat' => 19.4, 'lng' => -99.1, 'accuracy' => 5, 'recorded_at' => now()->subHours(3)->toIso8601String()];
    $future = ['lat' => 19.4, 'lng' => -99.1, 'accuracy' => 5, 'recorded_at' => now()->addHour()->toIso8601String()];
    $ok = ['lat' => 19.4, 'lng' => -99.1, 'accuracy' => 5, 'recorded_at' => now()->subMinute()->toIso8601String()];

    $this->postJson($this->url, ['points' => [$tooOld, $future, $ok]], apiAuthHeader($this->user))
        ->assertJsonPath('data.accepted', 1);
});

test('batch shape is validated', function (array $payload) {
    $this->postJson($this->url, $payload, apiAuthHeader($this->user))->assertStatus(422);
})->with([
    'empty batch' => [['points' => []]],
    'missing points' => [[]],
    'latitude out of range' => [['points' => [['lat' => 91, 'lng' => 0, 'accuracy' => 5, 'recorded_at' => '2026-10-16T10:00:00Z']]]],
    'longitude out of range' => [['points' => [['lat' => 10, 'lng' => -181, 'accuracy' => 5, 'recorded_at' => '2026-10-16T10:00:00Z']]]],
    'missing accuracy' => [['points' => [['lat' => 10, 'lng' => 10, 'recorded_at' => '2026-10-16T10:00:00Z']]]],
    'bad date' => [['points' => [['lat' => 10, 'lng' => 10, 'accuracy' => 5, 'recorded_at' => 'ayer']]]],
]);

test('more than 100 points per batch is rejected', function () {
    $this->postJson($this->url, ['points' => TrackingFixtures::track(101, seconds: 1)], apiAuthHeader($this->user))
        ->assertStatus(422);
});

test('another athlete cannot post points to the session', function () {
    $stranger = User::factory()->create();

    $this->postJson($this->url, ['points' => TrackingFixtures::track(2)], apiAuthHeader($stranger))->assertForbidden();
    expect(TrackingPoint::count())->toBe(0);
});

test('anonymous ingest is rejected', function () {
    $this->postJson($this->url, ['points' => TrackingFixtures::track(2)])->assertUnauthorized();
});

test('a stopped session rejects new points with 409', function () {
    $this->postJson("/api/v1/tracking/sessions/{$this->session->uuid}/stop", [], apiAuthHeader($this->user))->assertOk();

    $this->postJson($this->url, ['points' => TrackingFixtures::track(2)], apiAuthHeader($this->user))->assertStatus(409);
});

test('ingest broadcasts tracking.point to every usable share channel only', function () {
    Event::fake([TrackingPointRecorded::class]);
    $live = TrackingFixtures::share($this->participant, $this->user);
    $revoked = TrackingFixtures::share($this->participant, $this->user);
    $revoked['share']->update(['revoked_at' => now()]);

    $this->postJson($this->url, ['points' => TrackingFixtures::track(3)], apiAuthHeader($this->user))->assertStatus(202);

    Event::assertDispatched(TrackingPointRecorded::class, function (TrackingPointRecorded $event) use ($live) {
        $channels = array_map(fn ($c) => $c->name, $event->broadcastOn());
        $payload = $event->broadcastWith();

        return $channels === ['live.'.$live['share']->channel_key]
            && $event->broadcastAs() === 'tracking.point'
            && array_keys($payload) === ['lat', 'lng', 'recorded_at', 'distance_m', 'battery'];
    });
});

test('the broadcast channel is never the access token', function () {
    $share = TrackingFixtures::share($this->participant, $this->user);

    expect($share['share']->channel_key)->not->toBe($share['token'])
        ->and($share['share']->token_hash)->toBe(hash('sha256', $share['token']));
});

test('ingest is rate limited per session', function () {
    config(['tracking.ingest_per_minute' => 2]);
    $headers = apiAuthHeader($this->user);
    $points = TrackingFixtures::track(3);

    $this->postJson($this->url, ['points' => [$points[0]]], $headers)->assertStatus(202);
    $this->postJson($this->url, ['points' => [$points[1]]], $headers)->assertStatus(202);
    $this->postJson($this->url, ['points' => [$points[2]]], $headers)->assertStatus(429);
});
