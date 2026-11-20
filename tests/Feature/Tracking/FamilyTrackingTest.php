<?php

use App\Actions\Tracking\IngestTrackingPoints;
use App\Enums\FamilyLinkStatus;
use App\Models\AthleteFamilyLink;
use App\Models\TrackingShare;
use App\Models\User;
use Illuminate\Support\Str;
use Tests\Feature\Tracking\TrackingFixtures;

/*
| Familia y enlaces de seguimiento (docs/renovacion/familia.md): invites,
| accept/decline/revoke, explicit shares and — above all — that nobody
| without a live, addressed, unexpired share ever gets a position.
*/

beforeEach(function () {
    [$this->athlete, $this->participant] = TrackingFixtures::athleteWithParticipant();
    $this->mom = User::factory()->create(['email' => 'mama@example.com']);
});

/** Accepted link athlete → $contact, straight in the DB. */
function familyLink(User $athlete, User $contact, string $label = 'Mamá'): AthleteFamilyLink
{
    return AthleteFamilyLink::create([
        'uuid' => (string) Str::uuid(),
        'athlete_user_id' => $athlete->id,
        'contact_user_id' => $contact->id,
        'label' => $label,
        'status' => FamilyLinkStatus::Accepted,
        'accepted_at' => now(),
    ]);
}

test('an athlete invites an existing account by email and that person accepts', function () {
    $response = $this->postJson('/api/v1/family/links', ['email' => 'MAMA@example.com', 'label' => 'Mamá'], apiAuthHeader($this->athlete))
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.invite_url', null);

    $uuid = $response->json('data.uuid');

    $this->getJson('/api/v1/family', apiAuthHeader($this->mom))
        ->assertOk()
        ->assertJsonPath('data.invitations.0.uuid', $uuid)
        ->assertJsonPath('data.invitations.0.label', 'Mamá');

    // Someone else cannot answer it.
    $this->postJson("/api/v1/family/links/{$uuid}/accept", [], apiAuthHeader(User::factory()->create()))->assertNotFound();

    $this->postJson("/api/v1/family/links/{$uuid}/accept", [], apiAuthHeader($this->mom))->assertOk();

    expect(AthleteFamilyLink::where('uuid', $uuid)->value('status'))->toBe(FamilyLinkStatus::Accepted)
        ->and($this->athlete->notifications()->count())->toBe(1)
        ->and($this->mom->notifications()->count())->toBe(1);

    // No duplicate invite to the same person.
    $this->postJson('/api/v1/family/links', ['email' => 'mama@example.com'], apiAuthHeader($this->athlete))->assertUnprocessable();
});

test('an invite link works once, for whoever opens it signed in', function () {
    $url = $this->postJson('/api/v1/family/links', ['label' => 'Papá'], apiAuthHeader($this->athlete))
        ->assertCreated()
        ->json('data.invite_url');
    $token = Str::afterLast($url, '/');
    $link = AthleteFamilyLink::firstOrFail();

    expect($link->invite_token_hash)->toBe(hash('sha256', $token))
        ->and(AthleteFamilyLink::where('invite_token_hash', $token)->exists())->toBeFalse();

    $this->get("/familia/invitacion/{$token}")->assertOk()
        ->assertInertia(fn ($page) => $page->component('family/Invite')->where('available', true));

    // The athlete cannot accept their own invite.
    $this->postJson("/api/v1/family/invites/{$token}/accept", [], apiAuthHeader($this->athlete))->assertUnprocessable();

    $this->postJson("/api/v1/family/invites/{$token}/accept", [], apiAuthHeader($this->mom))->assertOk();
    expect($link->fresh())->contact_user_id->toBe($this->mom->id)->invite_token_hash->toBeNull();

    // Used up.
    $this->postJson("/api/v1/family/invites/{$token}/accept", [], apiAuthHeader(User::factory()->create()))->assertNotFound();
    $this->get("/familia/invitacion/{$token}")->assertNotFound();
});

test('only the owner can rename a link; a stranger gets 404', function () {
    $link = familyLink($this->athlete, $this->mom);

    $this->patchJson("/api/v1/family/links/{$link->uuid}", ['label' => 'Ma'], apiAuthHeader($this->mom))->assertNotFound();
    $this->patchJson("/api/v1/family/links/{$link->uuid}", ['label' => 'Ma'], apiAuthHeader(User::factory()->create()))->assertNotFound();
    $this->patchJson("/api/v1/family/links/{$link->uuid}", ['label' => 'Ma'], apiAuthHeader($this->athlete))->assertOk()->assertJsonPath('data.label', 'Ma');
});

test('a family link alone never shows a location', function () {
    familyLink($this->athlete, $this->mom);
    $session = TrackingFixtures::session($this->participant);
    app(IngestTrackingPoints::class)->handle($session, TrackingFixtures::track(5));

    $this->getJson('/api/v1/family/following', apiAuthHeader($this->mom))->assertOk()->assertJsonCount(0, 'data');
});

test('the athlete shares with chosen family; only they see the position', function () {
    $mom = familyLink($this->athlete, $this->mom);
    $dad = familyLink($this->athlete, $dadUser = User::factory()->create(), 'Papá');
    $session = TrackingFixtures::session($this->participant);
    app(IngestTrackingPoints::class)->handle($session, TrackingFixtures::track(5));

    $this->postJson("/api/v1/tracking/sessions/{$session->uuid}/sharing", [
        'family' => 'selected', 'family_link_ids' => [$mom->uuid], 'duration' => '2h',
    ], apiAuthHeader($this->athlete))->assertCreated()->assertJsonCount(1, 'data.shares');

    $share = TrackingShare::where('athlete_family_link_id', $mom->id)->firstOrFail();
    expect($share->audience)->toBe('family')->and($share->ends_with_session)->toBeTrue()
        ->and($this->mom->notifications()->count())->toBe(1);

    $this->getJson('/api/v1/family/following', apiAuthHeader($this->mom))->assertOk()->assertJsonPath('data.0.uuid', $share->uuid);
    $this->getJson("/api/v1/family/following/{$share->uuid}", apiAuthHeader($this->mom))
        ->assertOk()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonStructure(['data' => ['last_point' => ['lat', 'lng', 'recorded_at'], 'path']]);

    // Not addressed to Dad, nor to a stranger.
    $this->getJson("/api/v1/family/following/{$share->uuid}", apiAuthHeader($dadUser))->assertNotFound();
    $this->getJson("/api/v1/family/following/{$share->uuid}", apiAuthHeader(User::factory()->create()))->assertNotFound();
    $this->getJson('/api/v1/family/following', apiAuthHeader($dadUser))->assertJsonCount(0, 'data');

    // Someone else cannot share or stop the athlete's session.
    $this->postJson("/api/v1/tracking/sessions/{$session->uuid}/sharing", ['family' => 'all', 'duration' => '2h'], apiAuthHeader($this->mom))->assertForbidden();
    $this->deleteJson("/api/v1/tracking/sessions/{$session->uuid}/sharing", [], apiAuthHeader($this->mom))->assertForbidden();
});

test('an expired share gives no location and the list drops it', function () {
    $link = familyLink($this->athlete, $this->mom);
    $session = TrackingFixtures::session($this->participant);

    $this->postJson("/api/v1/tracking/sessions/{$session->uuid}/sharing", ['family' => 'all', 'duration' => '2h'], apiAuthHeader($this->athlete))->assertCreated();
    $share = TrackingShare::where('athlete_family_link_id', $link->id)->firstOrFail();

    $this->travel(121)->minutes();

    $this->getJson("/api/v1/family/following/{$share->uuid}", apiAuthHeader($this->mom))->assertStatus(410)->assertJsonMissingPath('data');
    $this->getJson('/api/v1/family/following', apiAuthHeader($this->mom))->assertJsonCount(0, 'data');
});

test('stopping the session ends family and public shares (410) with no position', function () {
    $link = familyLink($this->athlete, $this->mom);
    $session = TrackingFixtures::session($this->participant);
    app(IngestTrackingPoints::class)->handle($session, TrackingFixtures::track(5));

    $token = Str::afterLast($this->postJson("/api/v1/tracking/sessions/{$session->uuid}/sharing", [
        'family' => 'all', 'public_link' => true, 'duration' => 'until_stop',
    ], apiAuthHeader($this->athlete))->assertCreated()->json('data.link.url'), '/');
    $share = TrackingShare::where('athlete_family_link_id', $link->id)->firstOrFail();

    $this->getJson("/api/v1/live/{$token}")->assertOk()->assertJsonPath('data.status', 'active');

    $this->postJson("/api/v1/tracking/sessions/{$session->uuid}/stop", [], apiAuthHeader($this->athlete))->assertOk();

    $this->getJson("/api/v1/live/{$token}")->assertStatus(410)->assertJsonMissingPath('data');
    $this->get("/live/{$token}")->assertStatus(410)
        ->assertInertia(fn ($page) => $page->where('available', false)->where('reason', 'ended')->where('snapshot', null));
    $this->getJson("/api/v1/family/following/{$share->uuid}", apiAuthHeader($this->mom))->assertStatus(410);
});

test('stop sharing now and removing a person revoke access at once (404)', function () {
    $link = familyLink($this->athlete, $this->mom);
    $session = TrackingFixtures::session($this->participant);

    $token = Str::afterLast($this->postJson("/api/v1/tracking/sessions/{$session->uuid}/sharing", [
        'family' => 'all', 'public_link' => true, 'duration' => '6h',
    ], apiAuthHeader($this->athlete))->json('data.link.url'), '/');

    $this->deleteJson("/api/v1/tracking/sessions/{$session->uuid}/sharing", [], apiAuthHeader($this->athlete))
        ->assertOk()->assertJsonPath('data.revoked', 2);
    $this->getJson("/api/v1/live/{$token}")->assertNotFound();

    // Share again, then remove the person.
    $this->postJson("/api/v1/tracking/sessions/{$session->uuid}/sharing", ['family' => 'all', 'duration' => '6h'], apiAuthHeader($this->athlete))->assertCreated();
    $share = TrackingShare::where('athlete_family_link_id', $link->id)->usable()->firstOrFail();

    $this->deleteJson("/api/v1/family/links/{$link->uuid}", [], apiAuthHeader($this->athlete))->assertNoContent();

    expect($share->fresh()->revoked_at)->not->toBeNull();
    $this->getJson("/api/v1/family/following/{$share->uuid}", apiAuthHeader($this->mom))->assertNotFound();
});

test('a family share token never opens the public page', function () {
    familyLink($this->athlete, $this->mom);
    $session = TrackingFixtures::session($this->participant);
    $this->postJson("/api/v1/tracking/sessions/{$session->uuid}/sharing", ['family' => 'all', 'duration' => '2h'], apiAuthHeader($this->athlete))->assertCreated();

    // Even if the hash leaked, only `link` shares are readable by token.
    $share = TrackingShare::where('audience', 'family')->firstOrFail();
    $token = Str::random(48);
    $share->update(['token_hash' => hash('sha256', $token)]);

    $this->getJson("/api/v1/live/{$token}")->assertNotFound();
});

test('a stopped participation-wide link keeps the status but drops the position', function () {
    $session = TrackingFixtures::session($this->participant);
    app(IngestTrackingPoints::class)->handle($session, TrackingFixtures::track(5));
    $share = TrackingFixtures::share($this->participant, $this->athlete);

    $this->postJson("/api/v1/tracking/sessions/{$session->uuid}/stop", [], apiAuthHeader($this->athlete))->assertOk();

    $this->getJson("/api/v1/live/{$share['token']}")
        ->assertOk()
        ->assertJsonPath('data.status', 'stopped')
        ->assertJsonPath('data.last_point', null)
        ->assertJsonPath('data.path', [])
        ->assertJsonPath('data.battery', null);
});

test('a silent active session shows as paused', function () {
    $session = TrackingFixtures::session($this->participant);
    app(IngestTrackingPoints::class)->handle($session, TrackingFixtures::track(3));
    $share = TrackingFixtures::share($this->participant, $this->athlete);

    $this->travel(5)->minutes();

    $this->getJson("/api/v1/live/{$share['token']}")->assertOk()->assertJsonPath('data.status', 'paused');
});
