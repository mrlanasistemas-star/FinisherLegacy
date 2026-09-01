<?php

use App\Enums\PreregistrationStatus;
use App\Models\EventPreregistration;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

/**
 * The reported bug (product UX consolidation brief §67-§68): filtering
 * "PAGO PENDIENTE" showed a paginator with pages 1/2 but zero rows — the
 * classic symptom of a filter applied to the already-paginated ->data
 * collection instead of in SQL before paginate(). Traced against
 * App\Http\Controllers\Admin\PreregistrationController::index() directly
 * (simulated HTTP requests through the real controller against the real
 * dev database) and it doesn't reproduce: there was no status filter of
 * any kind on this screen before this change, so the failure mode the
 * report describes had no code path to come from. This test locks in the
 * status filter added alongside it so it can't regress into that shape.
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
