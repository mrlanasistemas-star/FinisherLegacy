<?php

use App\Actions\Athletes\EnsureAthleteForUser;
use App\Enums\LegacyPlateEntitlementStatus;
use App\Models\EventEdition;
use App\Models\EventPreregistration;
use App\Models\LegacyPlateEntitlement;
use App\Models\LegacyPlateModel;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Preregistro al evento and preventa Legacy Plate stay two different
 * things (brief §16), but the admin needs to see Legacy Plate payment
 * status right on the preregistrations table (brief §17-§20).
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

test('a preregistration shows its Legacy Plate payment status inline, batched not per-row', function () {
    $edition = EventEdition::factory()->create();
    $model = LegacyPlateModel::factory()->create();

    $paidUser = User::factory()->create();
    $paidAthlete = app(EnsureAthleteForUser::class)->handle($paidUser, 'test');
    EventPreregistration::factory()->create([
        'event_edition_id' => $edition->id,
        'user_id' => $paidUser->id,
        'first_name' => 'Paid',
        'last_name' => 'Athlete',
    ]);
    LegacyPlateEntitlement::create([
        'uuid' => (string) Str::uuid(),
        'athlete_id' => $paidAthlete->id,
        'event_edition_id' => $edition->id,
        'legacy_plate_model_id' => $model->id,
        'status' => LegacyPlateEntitlementStatus::Paid,
    ]);

    $noPlateUser = User::factory()->create();
    app(EnsureAthleteForUser::class)->handle($noPlateUser, 'test');
    EventPreregistration::factory()->create([
        'event_edition_id' => $edition->id,
        'user_id' => $noPlateUser->id,
        'first_name' => 'No',
        'last_name' => 'Plate',
    ]);

    $queryCount = 0;
    DB::listen(function () use (&$queryCount) {
        $queryCount++;
    });

    $response = $this->actingAs($this->admin)->get('/admin/preregistrations');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->has(
        'preregistrations.data',
        2,
        fn ($row) => $row->where('name', fn ($name) => in_array($name, ['Paid Athlete', 'No Plate'], true))
            ->where('legacy_plate_status', fn ($status) => in_array($status, ['paid', 'none'], true))
            ->etc(),
    ));

    // A handful of queries regardless of row count (brief §20: "no N+1") —
    // 25 rows would mean 25+ queries if entitlements were fetched one row
    // at a time.
    expect($queryCount)->toBeLessThan(30);
});

test('counters are computed server-side and reflect paid vs pending presales', function () {
    $edition = EventEdition::factory()->create();
    $model = LegacyPlateModel::factory()->create();

    $paidUser = User::factory()->create();
    $paidAthlete = app(EnsureAthleteForUser::class)->handle($paidUser, 'test');
    EventPreregistration::factory()->create(['event_edition_id' => $edition->id, 'user_id' => $paidUser->id]);
    LegacyPlateEntitlement::create([
        'uuid' => (string) Str::uuid(),
        'athlete_id' => $paidAthlete->id,
        'event_edition_id' => $edition->id,
        'legacy_plate_model_id' => $model->id,
        'status' => LegacyPlateEntitlementStatus::Paid,
    ]);

    $pendingUser = User::factory()->create();
    $pendingAthlete = app(EnsureAthleteForUser::class)->handle($pendingUser, 'test');
    EventPreregistration::factory()->create(['event_edition_id' => $edition->id, 'user_id' => $pendingUser->id]);
    LegacyPlateEntitlement::create([
        'uuid' => (string) Str::uuid(),
        'athlete_id' => $pendingAthlete->id,
        'event_edition_id' => $edition->id,
        'legacy_plate_model_id' => $model->id,
        'status' => LegacyPlateEntitlementStatus::PendingPayment,
    ]);

    EventPreregistration::factory()->create(['event_edition_id' => $edition->id]);

    $response = $this->actingAs($this->admin)->get('/admin/preregistrations');

    $response->assertInertia(fn ($page) => $page
        ->where('counters.preregistrations', 3)
        ->where('counters.presales', 2)
        ->where('counters.paid', 1)
        ->where('counters.pending', 1)
    );
});

test('the legacy_plate filter narrows the list to only paid presales', function () {
    $edition = EventEdition::factory()->create();
    $model = LegacyPlateModel::factory()->create();

    $paidUser = User::factory()->create();
    $paidAthlete = app(EnsureAthleteForUser::class)->handle($paidUser, 'test');
    EventPreregistration::factory()->create(['event_edition_id' => $edition->id, 'user_id' => $paidUser->id, 'first_name' => 'Paid']);
    LegacyPlateEntitlement::create([
        'uuid' => (string) Str::uuid(),
        'athlete_id' => $paidAthlete->id,
        'event_edition_id' => $edition->id,
        'legacy_plate_model_id' => $model->id,
        'status' => LegacyPlateEntitlementStatus::Paid,
    ]);
    EventPreregistration::factory()->create(['event_edition_id' => $edition->id, 'first_name' => 'NoPlate']);

    $response = $this->actingAs($this->admin)->get('/admin/preregistrations?legacy_plate=paid');

    $response->assertInertia(fn ($page) => $page->has('preregistrations.data', 1)
        ->where('preregistrations.data.0.legacy_plate_status', 'paid'));
});
