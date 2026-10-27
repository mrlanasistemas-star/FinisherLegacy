<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Commerce + Product + Activation OS permissions (config/permissions.php
 * stays the catalog). Created here too so an existing deployment gets them
 * without re-running RolePermissionSeeder. Two new roles:
 *   sales_staff — sells in person (POS), consults tags, opens support cases
 *   ally        — a partner's seller; sees only their ally's inventory/sales
 * Idempotent.
 */
return new class extends Migration
{
    private const array KEYS = [
        'tags.view', 'tags.manage',
        'allies.view', 'allies.manage',
        'support.view', 'support.manage',
        'pos.sell', 'ally.portal',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::KEYS as $key) {
            Permission::findOrCreate($key, 'web');
        }

        $admin = Role::query()->where('name', 'admin')->where('guard_name', 'web')->first();
        $admin?->givePermissionTo(array_values(array_diff(self::KEYS, ['ally.portal'])));

        $staff = Role::findOrCreate('sales_staff', 'web');
        $staff->givePermissionTo(array_values(array_filter(
            ['pos.sell', 'tags.view', 'support.view', 'support.manage', 'orders.view', 'dashboard.admin.view'],
            fn (string $key) => Permission::query()->where('name', $key)->where('guard_name', 'web')->exists(),
        )));

        Role::findOrCreate('ally', 'web')->givePermissionTo(['ally.portal']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Role::query()->whereIn('name', ['sales_staff', 'ally'])->delete();
        Permission::query()->whereIn('name', self::KEYS)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
