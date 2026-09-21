<?php

use App\Actions\Athletes\EnsureAthleteForUser;
use App\Enums\LegacyPlateEntitlementStatus;
use App\Enums\PreregistrationStatus;
use App\Models\EventEdition;
use App\Models\EventPreregistration;
use App\Models\LegacyPlateEntitlement;
use App\Models\LegacyPlateModel;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Str;

/**
 * The reported bug (product UX consolidation brief §67-§68 / item 45):
 * filtering "PAGO PENDIENTE" showed a paginator with pages 1/2 but zero
 * rows — the classic symptom of a filter applied to the already-paginated
 * ->data collection instead of in SQL before paginate(). The plain
 * `status` filter never had this problem (it always ran in SQL), but the
 * `legacy_plate` filter did: it filtered the already-paginated page's 25
 * mapped rows in PHP, so the paginator's total/last_page reflected the
 * *unfiltered* count while a given page could show 0 of its 25 rows after
 * filtering — see "the legacy_plate filter never returns a phantom page"
 * below for the regression test.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

test('filtering by a status with zero matches returns an honest empty page, not a phantom page 2', function () {
    EventPreregistration::factory()->count(30)->create(['status' => PreregistrationStatus::Pending]);

    expect(EventPreregistration::query()->where('status', PreregistrationStatus::Cancelled)->count())->toBe(0);

    $response = $this->actingAs($this->admin)->get('/admin/preregistrations?status=cancelled');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/preregistrations/Index')
        ->where('preregistrations.data', [])
        ->where('preregistrations.total', 0)
        ->where('preregistrations.last_page', 1)
        ->where('preregistrations.current_page', 1));
});

test('the filtered total and page count always describe the filtered set, not the unfiltered one', function () {
    EventPreregistration::factory()->count(30)->create(['status' => PreregistrationStatus::Pending]);
    EventPreregistration::factory()->count(3)->create(['status' => PreregistrationStatus::Confirmed]);

    $response = $this->actingAs($this->admin)->get('/admin/preregistrations?status=confirmed');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/preregistrations/Index')
        ->has('preregistrations.data', 3)
        ->where('preregistrations.total', 3)
        ->where('preregistrations.last_page', 1));
});

test('an unrecognized status value is ignored rather than silently matching everything', function () {
    EventPreregistration::factory()->count(5)->create(['status' => PreregistrationStatus::Pending]);

    $response = $this->actingAs($this->admin)->get('/admin/preregistrations?status=not_a_real_status');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/preregistrations/Index')
        ->where('preregistrations.total', 5));
});

test('the legacy_plate filter never returns a phantom page (total/last_page always describe the filtered set)', function () {
    $edition = EventEdition::factory()->create();
    $model = LegacyPlateModel::factory()->create();

    // 30 preregistrations with no Legacy Plate at all — enough to fill
    // page 1 (25/page) with rows the "pending" filter must exclude.
    EventPreregistration::factory()->count(30)->create(['event_edition_id' => $edition->id]);

    // A single pending-payment presale — under the old PHP-side filter,
    // if this landed on page 2 of the *unfiltered* pagination, filtering
    // "pending" against page 1's 25 rows alone would report a paginator
    // with 2 pages but page 1 showing zero of them.
    $user = User::factory()->create();
    $athlete = app(EnsureAthleteForUser::class)->handle($user, 'test');
    EventPreregistration::factory()->create(['event_edition_id' => $edition->id, 'user_id' => $user->id]);
    LegacyPlateEntitlement::create([
        'uuid' => (string) Str::uuid(),
        'athlete_id' => $athlete->id,
        'event_edition_id' => $edition->id,
        'legacy_plate_model_id' => $model->id,
        'status' => LegacyPlateEntitlementStatus::PendingPayment,
    ]);

    $response = $this->actingAs($this->admin)->get('/admin/preregistrations?legacy_plate=pending');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/preregistrations/Index')
        ->has('preregistrations.data', 1)
        ->where('preregistrations.total', 1)
        ->where('preregistrations.last_page', 1)
        ->where('preregistrations.data.0.legacy_plate_status', 'pending_payment'));
});
