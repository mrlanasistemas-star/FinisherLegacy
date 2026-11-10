<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * `photographers.commissions.manage`: edit the photographer commission
 * terms (global rate, per-photographer overrides, gateway fee), reverse
 * sales and read the commission reports. Granted to admin (the role
 * seeder also syncs every permission to admin). Idempotent.
 */
return new class extends Migration
{
    private const array KEYS = ['photographers.commissions.manage'];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::KEYS as $key) {
            Permission::findOrCreate($key, 'web');
        }

        Role::query()->where('name', 'admin')->where('guard_name', 'web')->first()?->givePermissionTo(self::KEYS);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->whereIn('name', self::KEYS)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
