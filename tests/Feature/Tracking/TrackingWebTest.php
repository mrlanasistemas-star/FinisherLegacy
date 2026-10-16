<?php

use App\Actions\Tracking\IngestTrackingPoints;
use App\Models\TrackingDevice;
use App\Models\TrackingPoint;
use App\Models\TrackingShare;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Tests\Feature\Tracking\TrackingFixtures;

beforeEach(function () {
    [$this->user, $this->participant] = TrackingFixtures::athleteWithParticipant();
});

test('the participation detail carries the tracking card data', function () {
    TrackingFixtures::share($this->participant, $this->user);

    $this->actingAs($this->user)
        ->get("/dashboard/legado/{$this->participant->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard/LegadoShow')
            ->where('tracking.session', null)
            ->has('tracking.shares', 1)
            ->missing('tracking.shares.0.token'));
});

test('the athlete creates a share from the web and gets the link once via flash', function () {
    $this->actingAs($this->user)
        ->post("/dashboard/legado/{$this->participant->id}/tracking/shares")
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data.trackingShare.url');

    expect(TrackingShare::where('event_participant_id', $this->participant->id)->count())->toBe(1);
});

test('another athlete cannot create or revoke shares on the web', function () {
    $stranger = User::factory()->create();
    $share = TrackingFixtures::share($this->participant, $this->user);

    $this->actingAs($stranger)->post("/dashboard/legado/{$this->participant->id}/tracking/shares")->assertForbidden();
    $this->actingAs($stranger)->delete("/dashboard/tracking/shares/{$share['share']->uuid}")->assertForbidden();
    $this->actingAs($stranger)->post("/dashboard/legado/{$this->participant->id}/tracking/devices")->assertForbidden();

    expect($share['share']->fresh()->revoked_at)->toBeNull();
});

test('the owner revokes a share and pairs / disconnects a device', function () {
    $share = TrackingFixtures::share($this->participant, $this->user);

    $this->actingAs($this->user)->delete("/dashboard/tracking/shares/{$share['share']->uuid}")->assertRedirect();
    expect($share['share']->fresh()->revoked_at)->not->toBeNull();

    $this->actingAs($this->user)->post("/dashboard/legado/{$this->participant->id}/tracking/devices")->assertRedirect();
    $device = TrackingDevice::firstOrFail();
    expect($device->status->value)->toBe('pending')
        ->and($device->pairing_code_hash)->toHaveLength(64);

    $this->actingAs($this->user)->delete("/dashboard/tracking/devices/{$device->uuid}")->assertRedirect();
    expect($device->fresh()->status->value)->toBe('revoked');
});

test('admin sees the event tracking overview; athletes cannot', function () {
    $this->seed(RolePermissionSeeder::class);
    $session = TrackingFixtures::session($this->participant);
    $this->travel(5)->minutes();
    app(IngestTrackingPoints::class)->handle($session, TrackingFixtures::track(3));

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get("/admin/events/{$this->participant->event_edition_id}/tracking")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/events/Tracking')
            ->where('stats.active', 1)
            ->has('sessions.data', 1)
            ->where('sessions.data.0.status', 'active'));

    $this->actingAs($this->user)
        ->get("/admin/events/{$this->participant->event_edition_id}/tracking")
        ->assertForbidden();
});

test('prune command deletes old points only', function () {
    $session = TrackingFixtures::session($this->participant);
    $this->travel(5)->minutes();
    app(IngestTrackingPoints::class)->handle($session, TrackingFixtures::track(3));
    $this->travel(100)->days();
    $fresh = TrackingFixtures::session(TrackingFixtures::athleteWithParticipant()[1]);
    $this->travel(5)->minutes();
    app(IngestTrackingPoints::class)->handle($fresh, TrackingFixtures::track(2));

    $this->artisan('tracking:prune-points', ['--days' => 90])->assertSuccessful();

    expect(TrackingPoint::count())->toBe(2);
});
