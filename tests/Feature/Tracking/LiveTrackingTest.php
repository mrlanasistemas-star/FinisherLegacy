<?php

use App\Actions\Tracking\IngestTrackingPoints;
use App\Actions\Tracking\StopTrackingSession;
use Tests\Feature\Tracking\TrackingFixtures;

beforeEach(function () {
    [$this->user, $this->participant] = TrackingFixtures::athleteWithParticipant(raceKm: 10);
    $this->participant->update(['email' => 'secreto@example.com', 'phone' => '5555555555', 'bib_number' => '4242']);
});

test('the snapshot follows the contract and never leaks PII or internals', function () {
    $session = TrackingFixtures::session($this->participant);
    $this->travel(5)->minutes();
    app(IngestTrackingPoints::class)->handle($session, TrackingFixtures::track(11, metersPerStep: 100, seconds: 20));
    $share = TrackingFixtures::share($this->participant, $this->user);

    $response = $this->getJson("/api/v1/live/{$share['token']}")
        ->assertOk()
        ->assertJsonStructure(['data' => [
            'athlete' => ['display_name', 'avatar_url'],
            'event' => ['name'],
            'status', 'last_point' => ['lat', 'lng', 'recorded_at'], 'distance_m', 'progress', 'battery', 'path',
        ]])
        ->assertJsonPath('data.status', 'active');

    $data = $response->json('data');
    expect($data['distance_m'])->toBeGreaterThan(990)
        ->and($data['progress'])->toBeGreaterThan(0.09)->toBeLessThan(0.11)
        ->and(count($data['path']))->toBeLessThanOrEqual(500)->toBeGreaterThan(1);

    $raw = $response->getContent();
    foreach (['secreto@example.com', '5555555555', '4242', $session->uuid, $share['token'], 'anchor', 'accuracy', 'athlete_id', 'event_participant_id'] as $needle) {
        expect($raw)->not->toContain($needle);
    }
});

test('a share pinned to a session never shows another session of the same participant', function () {
    $first = TrackingFixtures::session($this->participant);
    $this->travel(2)->minutes();
    app(IngestTrackingPoints::class)->handle($first, TrackingFixtures::track(3, metersPerStep: 50));
    $pinned = TrackingFixtures::share($this->participant, $this->user, $first);
    app(StopTrackingSession::class)->handle($first);

    $second = TrackingFixtures::session($this->participant);
    $this->travel(10)->minutes();
    app(IngestTrackingPoints::class)->handle($second, TrackingFixtures::track(20, metersPerStep: 50));

    $this->getJson("/api/v1/live/{$pinned['token']}")
        ->assertOk()
        ->assertJsonPath('data.status', 'stopped')
        ->assertJsonPath('data.distance_m', $first->fresh()->distance_m);
});

test('the path is simplified to at most the configured number of points', function () {
    config(['tracking.live_path_max_points' => 10]);
    $session = TrackingFixtures::session($this->participant);
    $this->travel(30)->minutes();
    app(IngestTrackingPoints::class)->handle($session, TrackingFixtures::track(100, metersPerStep: 20, seconds: 5));
    $share = TrackingFixtures::share($this->participant, $this->user);

    $path = $this->getJson("/api/v1/live/{$share['token']}")->json('data.path');

    expect(count($path))->toBeLessThanOrEqual(10)->toBeGreaterThan(5);
});

test('before the start the page shows a waiting state', function () {
    $share = TrackingFixtures::share($this->participant, $this->user);

    $this->getJson("/api/v1/live/{$share['token']}")
        ->assertOk()
        ->assertJsonPath('data.status', 'waiting')
        ->assertJsonPath('data.last_point', null)
        ->assertJsonPath('data.path', []);
});

test('the web live page renders for a valid token and a friendly page for a revoked one', function () {
    $share = TrackingFixtures::share($this->participant, $this->user);

    $this->get("/live/{$share['token']}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('live/Show')->where('available', true)->has('snapshot.channel'));

    $share['share']->update(['revoked_at' => now()]);

    $this->get("/live/{$share['token']}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('live/Show')->where('available', false)->where('snapshot', null));

    $this->get('/live/'.str_repeat('a', 48))
        ->assertInertia(fn ($page) => $page->where('available', false));
});

test('the live page is rate limited', function () {
    config(['tracking.live_view_per_minute' => 2]);
    $share = TrackingFixtures::share($this->participant, $this->user);

    $this->getJson("/api/v1/live/{$share['token']}")->assertOk();
    $this->getJson("/api/v1/live/{$share['token']}")->assertOk();
    $this->getJson("/api/v1/live/{$share['token']}")->assertStatus(429);
});
