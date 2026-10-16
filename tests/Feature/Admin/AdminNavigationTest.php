<?php

use App\Models\User;
use App\Support\PermissionCatalog;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

test('super_admin receives the full permission catalog plus roles.manage, driving a full sidebar', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super_admin');

    $response = $this->actingAs($superAdmin)->get('/admin');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('auth.isSuperAdmin', true)
        ->where('auth.permissions', fn ($permissions) => $permissions->contains('users.view')
            && $permissions->contains('roles.manage')
            && $permissions->contains('platetemplates.view')
            && $permissions->contains('operator.access')
            && $permissions->contains('production.access')
            && $permissions->count() === count(PermissionCatalog::allKeys()) + 1
        )
    );
});

test('event_operator only receives its own permissions — no users/roles access to build the menu with', function () {
    $operator = User::factory()->create();
    $operator->assignRole('event_operator');

    $response = $this->actingAs($operator)->get('/operator');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('auth.isSuperAdmin', false)
        ->where('auth.permissions', fn ($permissions) => $permissions->contains('operator.access')
            && ! $permissions->contains('users.view')
            && ! $permissions->contains('roles.manage')
        )
    );

    $this->actingAs($operator)->get('/admin/users')->assertForbidden();
    $this->actingAs($operator)->get('/admin/roles')->assertForbidden();
});

test('production_operator sees production access but not users/roles', function () {
    $productionOperator = User::factory()->create();
    $productionOperator->assignRole('production_operator');

    $response = $this->actingAs($productionOperator)->get('/admin/legacy-nfc/preparacion');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('auth.permissions', fn ($permissions) => $permissions->contains('production.access')
            && ! $permissions->contains('users.view')
            && ! $permissions->contains('roles.manage')
        )
    );

    $this->actingAs($productionOperator)->get('/admin/users')->assertForbidden();
    $this->actingAs($productionOperator)->get('/admin/roles')->assertForbidden();
});

test('a plain admin sees permissions for every module except roles.manage', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->get('/admin');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('auth.isSuperAdmin', false)
        ->where('auth.permissions', fn ($permissions) => $permissions->contains('users.view')
            && ! $permissions->contains('roles.manage')
        )
    );

    $this->actingAs($admin)->get('/admin/roles')->assertForbidden();
});

test('an athlete with no staff role never reaches the admin shell at all', function () {
    $athlete = User::factory()->create();
    $athlete->assignRole('athlete');

    $response = $this->actingAs($athlete)->get('/dashboard');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->where('auth.permissions', []));

    $this->actingAs($athlete)->get('/admin')->assertForbidden();
});

test('settings reached by a staff user still shares admin permissions (so app.ts keeps the admin shell)', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->get('/settings/profile');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('settings/Profile')
        ->where('auth.permissions', fn ($permissions) => $permissions->contains('dashboard.admin.view'))
    );
});

test('imports uses can:imports.manage just like every other admin surface', function () {
    $manager = User::factory()->create();
    $manager->assignRole('event_manager');

    $this->actingAs($manager)->get('/imports')->assertOk();

    $athlete = User::factory()->create();
    $athlete->assignRole('athlete');
    $this->actingAs($athlete)->get('/imports')->assertForbidden();
});
