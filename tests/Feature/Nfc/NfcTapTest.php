<?php

require_once __DIR__.'/helpers.php';

use App\Actions\Athletes\EnsureAthleteForUser;
use App\Actions\Nfc\AssignNfcTag;
use App\Models\AthleteProfile;
use App\Models\Event;
use App\Models\NfcTag;
use App\Models\User;

test('an unclaimed tag renders the activation page with login/register that come back here', function () {
    [, $tag] = nfcPlateWithTag();

    $this->get("/n/{$tag->token}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('nfc/Tap')
            ->where('status', 'unclaimed')
            ->where('canClaim', true)
            ->where('target.type', 'legacy_plate'));

    $this->get("/n/{$tag->token}/continue/register")->assertRedirect(route('register'));
    expect(session('url.intended'))->toBe(route('nfc.tap', $tag->token));

    expect($tag->fresh()->tap_count)->toBe(1)
        ->and($tag->fresh()->last_tapped_at)->not->toBeNull();
});

test('claiming on the web connects the plate and lands in Mi equipo', function () {
    [$plate, $tag] = nfcPlateWithTag();
    $user = User::factory()->create();

    $this->actingAs($user)->post("/n/{$tag->token}/claim")->assertRedirect(route('dashboard.my-gear'));

    $this->actingAs($user)->get(route('dashboard.my-gear'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('dashboard/MyGear')
            ->where('items.0.kind', 'legacy_plate')
            ->where('items.0.connected', true));

    // Now connected: tapping goes straight to the Legacy Plate story.
    $this->get("/n/{$tag->token}")->assertRedirect(route('legacy-code.show', $plate->legacyCode->code));
});

test('claiming on the web requires authentication', function () {
    [, $tag] = nfcPlateWithTag();

    $this->post("/n/{$tag->token}/claim")->assertRedirect(route('login'));
});

test('a claimed plate claimed through the old /l/{code} flow is connected for NFC too', function () {
    [$plate, $tag] = nfcPlateWithTag();
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('legacy-code.claim', $plate->legacyCode->code));

    $this->getJson("/api/v1/nfc/{$tag->token}")->assertJsonPath('data.status', 'claimed');
});

test('disabled and not-ready tags show an honest page', function () {
    $disabled = NfcTag::factory()->disabled()->create();
    $blank = NfcTag::factory()->create();

    $this->get("/n/{$disabled->token}")->assertOk()
        ->assertInertia(fn ($page) => $page->component('nfc/Tap')->where('status', 'disabled')->where('reason', 'disabled'));
    $this->get("/n/{$blank->token}")->assertOk()
        ->assertInertia(fn ($page) => $page->component('nfc/Tap')->where('reason', 'not_ready'));

    $this->get('/n/'.str_repeat('Q', 32))->assertNotFound();
    $this->get('/n/not-a-token')->assertNotFound();
});

test('event and profile tags redirect to their public pages', function () {
    $staff = User::factory()->create();
    $event = Event::factory()->create();
    $eventTag = NfcTag::factory()->create();
    app(AssignNfcTag::class)->toEvent($eventTag, $event, $staff);

    $this->get("/n/{$eventTag->token}")->assertRedirect(route('events.show', $event->slug));

    $profile = AthleteProfile::factory()->create();
    $owner = app(EnsureAthleteForUser::class)->handle($profile->user, 'test');
    $profileTag = NfcTag::factory()->create();
    app(AssignNfcTag::class)->toProfile($profileTag, $owner, $staff);

    $this->get("/n/{$profileTag->token}")->assertRedirect(route('profile.public', $profile->username));
});

test('app link files are 404 until configured, then served as JSON', function () {
    $this->get('/.well-known/apple-app-site-association')->assertNotFound();
    $this->get('/.well-known/assetlinks.json')->assertNotFound();

    config([
        'nfc.app.apple_team_id' => 'ABCDE12345',
        'nfc.app.android_sha256' => ['AA:BB:CC', 'DD:EE:FF'],
    ]);

    $this->get('/.well-known/apple-app-site-association')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonPath('applinks.details.0.appID', 'ABCDE12345.com.finisherlegacy.app')
        ->assertJsonPath('applinks.details.0.paths', ['/n/*', '/t/*']);

    $this->get('/.well-known/assetlinks.json')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonPath('0.target.package_name', 'com.finisherlegacy.app')
        ->assertJsonPath('0.target.sha256_cert_fingerprints', ['AA:BB:CC', 'DD:EE:FF']);
});
