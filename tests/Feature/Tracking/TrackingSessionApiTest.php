<?php

use App\Actions\Tracking\CreateTrackingDevicePairing;
use App\Models\TrackingSession;
use App\Models\TrackingShare;
use App\Models\User;
use Tests\Feature\Tracking\TrackingFixtures;

beforeEach(function () {
    [$this->user, $this->participant] = TrackingFixtures::athleteWithParticipant();
});

test('the athlete starts a session; a second start returns the same active one', function () {
    $url = "/api/v1/me/events/{$this->participant->id}/tracking/sessions";

    $first = $this->postJson($url, [], apiAuthHeader($this->user))
        ->assertCreated()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.source', 'mobile_gps')
        ->assertJsonPath('data.batch_max', 100)
        ->assertJsonStructure(['data' => ['uuid', 'status', 'started_at', 'source', 'batch_max']]);

    $this->postJson($url, [], apiAuthHeader($this->user))
        ->assertOk()
        ->assertJsonPath('data.uuid', $first->json('data.uuid'));

    expect(TrackingSession::count())->toBe(1);
});

test('another athlete cannot start, read, stop or share someone else\'s tracking', function () {
    $stranger = User::factory()->create();
    $session = TrackingFixtures::session($this->participant);

    $this->postJson("/api/v1/me/events/{$this->participant->id}/tracking/sessions", [], apiAuthHeader($stranger))->assertForbidden();
    $this->getJson("/api/v1/me/events/{$this->participant->id}/tracking", apiAuthHeader($stranger))->assertForbidden();
    $this->getJson("/api/v1/tracking/sessions/{$session->uuid}", apiAuthHeader($stranger))->assertForbidden();
    $this->postJson("/api/v1/tracking/sessions/{$session->uuid}/stop", [], apiAuthHeader($stranger))->assertForbidden();
    $this->postJson("/api/v1/tracking/sessions/{$session->uuid}/shares", [], apiAuthHeader($stranger))->assertForbidden();

    expect($session->fresh()->status->value)->toBe('active')
        ->and(TrackingShare::count())->toBe(0);
});

test('owner reads and stops the session (stop is idempotent)', function () {
    $session = TrackingFixtures::session($this->participant);

    $this->getJson("/api/v1/tracking/sessions/{$session->uuid}", apiAuthHeader($this->user))
        ->assertOk()->assertJsonPath('data.uuid', $session->uuid);

    $this->postJson("/api/v1/tracking/sessions/{$session->uuid}/stop", [], apiAuthHeader($this->user))
        ->assertOk()->assertJsonPath('data.status', 'stopped');
    $this->postJson("/api/v1/tracking/sessions/{$session->uuid}/stop", [], apiAuthHeader($this->user))
        ->assertOk()->assertJsonPath('data.status', 'stopped');
});

test('share lifecycle: create returns the token once, stores only its hash, revoke disables it', function () {
    $session = TrackingFixtures::session($this->participant);

    $response = $this->postJson("/api/v1/tracking/sessions/{$session->uuid}/shares", ['expires_in_hours' => 6], apiAuthHeader($this->user))
        ->assertCreated()
        ->assertJsonStructure(['data' => ['uuid', 'token', 'url', 'expires_at']]);

    $token = $response->json('data.token');
    $share = TrackingShare::where('uuid', $response->json('data.uuid'))->firstOrFail();

    expect(strlen($token))->toBeGreaterThanOrEqual(40)
        ->and($response->json('data.url'))->toEndWith("/live/{$token}")
        ->and($share->token_hash)->toBe(hash('sha256', $token))
        ->and(TrackingShare::where('token_hash', $token)->exists())->toBeFalse()
        ->and($share->expires_at->diffInHours(now(), true))->toBeGreaterThan(5.9)->toBeLessThan(6.1);

    $this->getJson("/api/v1/live/{$token}")->assertOk();

    $this->deleteJson("/api/v1/tracking/shares/{$share->uuid}", [], apiAuthHeader($this->user))->assertNoContent();
    $this->getJson("/api/v1/live/{$token}")->assertNotFound();
});

test('another athlete cannot revoke a share', function () {
    $share = TrackingFixtures::share($this->participant, $this->user);

    $this->deleteJson("/api/v1/tracking/shares/{$share['share']->uuid}", [], apiAuthHeader(User::factory()->create()))->assertForbidden();
    expect($share['share']->fresh()->revoked_at)->toBeNull();
});

test('an expired share stops working', function () {
    $share = TrackingFixtures::share($this->participant, $this->user);

    $this->getJson("/api/v1/live/{$share['token']}")->assertOk();
    $this->travel(73)->hours();
    $this->getJson("/api/v1/live/{$share['token']}")->assertNotFound();
});

test('a paired gps band can start its session and post points with its device token', function () {
    $code = app(CreateTrackingDevicePairing::class)
        ->handle($this->participant->athlete, $this->participant, 'Banda')['code'];

    $pair = $this->postJson('/api/v1/tracking/devices/pair', ['code' => $code])->assertCreated();
    $token = $pair->json('data.token');
    $this->postJson('/api/v1/tracking/devices/pair', ['code' => $code])->assertStatus(422); // single use

    $start = $this->postJson('/api/v1/tracking/devices/session', [], ['X-Tracking-Device-Token' => $token])
        ->assertCreated()
        ->assertJsonPath('data.source', 'gps_band');

    $this->postJson("/api/v1/tracking/sessions/{$start->json('data.uuid')}/points", ['points' => TrackingFixtures::track(2)], ['X-Tracking-Device-Token' => $token])
        ->assertStatus(202);
    $this->postJson("/api/v1/tracking/sessions/{$start->json('data.uuid')}/points", ['points' => TrackingFixtures::track(2)], ['X-Tracking-Device-Token' => 'nope'])
        ->assertForbidden();
});

test('me/tracking/sessions/active lists only the caller\'s running sessions', function () {
    $this->getJson('/api/v1/me/tracking/sessions/active', apiAuthHeader($this->user))
        ->assertOk()->assertJsonCount(0, 'data');

    $session = TrackingFixtures::session($this->participant);
    [, $otherParticipant] = TrackingFixtures::athleteWithParticipant();
    TrackingFixtures::session($otherParticipant);

    $this->getJson('/api/v1/me/tracking/sessions/active', apiAuthHeader($this->user))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.uuid', $session->uuid)
        ->assertJsonPath('data.0.event_participant_id', $this->participant->id)
        ->assertJsonStructure(['data' => [['uuid', 'event_participant_id', 'event_name', 'started_at', 'distance_m', 'last_point_at']]]);
});

test('session shares are listed for the owner only, never with a token', function () {
    $session = TrackingFixtures::session($this->participant);
    $share = TrackingFixtures::share($this->participant, $this->user, $session);

    $response = $this->getJson("/api/v1/tracking/sessions/{$session->uuid}/shares", apiAuthHeader($this->user))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.uuid', $share['share']->uuid)
        ->assertJsonPath('data.0.url', null)
        ->assertJsonPath('data.0.revoked_at', null);

    expect($response->getContent())->not->toContain($share['token']);

    $this->getJson("/api/v1/tracking/sessions/{$session->uuid}/shares", apiAuthHeader(User::factory()->create()))
        ->assertForbidden();
});
