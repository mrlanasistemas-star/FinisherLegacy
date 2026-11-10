<?php

use App\Enums\LegacyTagStatus;
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
        ->has('general.new_messages')
        ->has('tags.in_stock')
        ->has('tags.activated')
        ->missing('production')
        ->has('kpis.pending_orders')
    );
});

test('an event_operator without dashboard.admin.view cannot reach admin lists', function () {
    $operator = User::factory()->create();
    $operator->assignRole('event_operator');

    $this->actingAs($operator)->get('/admin/legacy-codes')->assertForbidden();
});

test('an admin can list historical legacy codes and events', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)->get('/admin/legacy-codes')->assertOk();
    $this->actingAs($admin)->get('/admin/participants')->assertRedirect('/admin/events');
    $this->actingAs($admin)->get('/admin/preregistrations')->assertOk();
    $this->actingAs($admin)->get('/admin/events')->assertOk();
});

test('a signed in athlete is forbidden from every back-office area', function (string $url) {
    $athlete = User::factory()->create();
    $athlete->assignRole('athlete');

    $this->actingAs($athlete)->get($url)->assertForbidden();
})->with([
    '/admin', '/admin/orders', '/admin/products', '/admin/tags', '/admin/soporte', '/admin/settings',
    '/admin/users', '/admin/venta-rapida', '/admin/legacy-nfc', '/imports', '/aliado', '/fotografo',
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
