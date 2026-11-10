<?php

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->superAdmin = User::factory()->create();
    $this->superAdmin->assignRole('super_admin');
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

test('only super_admin can reach /admin/roles — admin gets 403', function () {
    $this->actingAs($this->superAdmin)->get(route('admin.roles.index'))->assertOk();
    $this->actingAs($this->admin)->get(route('admin.roles.index'))->assertForbidden();
});

test('the roles index shows labels, system flag, and counts', function () {
    $response = $this->actingAs($this->superAdmin)->get(route('admin.roles.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/roles/Index')
        ->where('roles', fn ($roles) => collect($roles)->firstWhere('name', 'admin')['label'] === 'Administrador'
            && collect($roles)->firstWhere('name', 'admin')['is_system'] === true
        )
    );
});

test('super_admin can create a custom role with a slugified technical name', function () {
    $response = $this->actingAs($this->superAdmin)->post(route('admin.roles.store'), [
        'label' => 'Coordinador de Logística',
        'description' => 'Apoya al operador de producción.',
        'permissions' => ['legacycodes.view', 'nfc.view'],
    ]);

    $role = Role::where('label', 'Coordinador de Logística')->firstOrFail();
    $response->assertRedirect(route('admin.roles.edit', $role));
    expect($role->name)->toBe('coordinador_de_logistica')
        ->and($role->permissions->pluck('name')->all())->toEqualCanonicalizing(['legacycodes.view', 'nfc.view']);
});

test('super_admin permissions cannot be edited, and the role stays read-only', function () {
    $superAdminRole = Role::where('name', 'super_admin')->firstOrFail();

    $edit = $this->actingAs($this->superAdmin)->get(route('admin.roles.edit', $superAdminRole));
    $edit->assertOk();
    $edit->assertInertia(fn ($page) => $page->where('role.is_super_admin', true));

    $this->actingAs($this->superAdmin)->patch(route('admin.roles.update', $superAdminRole), [
        'label' => 'Hackeado',
        'permissions' => [],
    ])->assertSessionHasErrors('permissions');

    expect($superAdminRole->fresh()->label)->toBe('Super Admin');
});

test('duplicating a role copies its permissions into a new role', function () {
    $eventManager = Role::where('name', 'event_manager')->firstOrFail();
    $originalPermissions = $eventManager->permissions->pluck('name')->sort()->values()->all();

    $this->actingAs($this->superAdmin)->post(route('admin.roles.duplicate', $eventManager))->assertRedirect();

    $clone = Role::where('label', 'Manager de evento (copia)')->firstOrFail();
    expect($clone->permissions->pluck('name')->sort()->values()->all())->toBe($originalPermissions);
});
