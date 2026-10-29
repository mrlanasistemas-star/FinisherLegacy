<?php

use App\Enums\IncidentStatus;
use App\Enums\LegacyTagStatus;
use App\Models\EventIncident;
use App\Models\LegacyTag;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('admin routes are blocked for users without dashboard.admin.view', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/admin')->assertForbidden();
});

test('an admin can see the dashboard with real metrics', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->get('/admin');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/Dashboard')
        ->has('general.athletes')
        ->has('general.open_incidents')
        ->has('kpis.pending_orders')
    );
});

test('an event_operator without dashboard.admin.view cannot reach admin lists', function () {
    $operator = User::factory()->create();
    $operator->assignRole('event_operator');

    $this->actingAs($operator)->get('/admin/plates')->assertForbidden();
});

test('an admin can list plates and legacy codes', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)->get('/admin/plates')->assertOk();
    $this->actingAs($admin)->get('/admin/legacy-codes')->assertOk();
    $this->actingAs($admin)->get('/admin/participants')->assertRedirect('/admin/events');
    $this->actingAs($admin)->get('/admin/preregistrations')->assertOk();
    $this->actingAs($admin)->get('/admin/events')->assertOk();
});

test('an admin can resolve an open incident but not resolve it twice', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $incident = EventIncident::factory()->create([
        'status' => IncidentStatus::Open,
        'reported_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->patch("/admin/incidents/{$incident->id}/resolve")
        ->assertRedirect();

    expect($incident->fresh()->status)->toBe(IncidentStatus::Resolved)
        ->and($incident->fresh()->resolved_by)->toBe($admin->id);

    $this->actingAs($admin)
        ->patch("/admin/incidents/{$incident->id}/resolve")
        ->assertStatus(409);
});

test('a signed in athlete is forbidden from every back-office area', function (string $url) {
    $athlete = User::factory()->create();
    $athlete->assignRole('athlete');

    $this->actingAs($athlete)->get($url)->assertForbidden();
})->with([
    '/admin', '/admin/orders', '/admin/products', '/admin/tags', '/admin/soporte', '/admin/settings',
    '/admin/users', '/admin/venta-rapida', '/admin/legacy-nfc', '/operator', '/imports', '/aliado', '/fotografo',
]);

test('guests visiting the admin are sent to the login page', function () {
    $this->get('/admin')->assertRedirect('/login');
});

test('the admin dashboard lists recent activations and open support for whoever can open them', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $tag = LegacyTag::factory()->create(['status' => LegacyTagStatus::Activated, 'activated_at' => now()->subHour()]);

    $this->actingAs($admin)->get('/admin')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('recentActivations', 1)
            ->where('recentActivations.0.uuid', $tag->uuid)
            ->where('support.open', 0)
            ->has('support.recent', 0));
});

test('the dashboard hides activations and support from staff without those permissions', function () {
    $role = Role::findOrCreate('dashboard_only');
    $role->syncPermissions(['dashboard.admin.view']);
    $user = User::factory()->create();
    $user->assignRole($role);

    $this->actingAs($user)->get('/admin')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('recentActivations', null)
            ->where('support', null));
});
