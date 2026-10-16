<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * NFC tag administration (config/permissions.php stays the catalog). Also
 * created here so an existing deployment gets them — admin keeps "every
 * permission", production operators can assign tags while preparing
 * plates — without re-running RolePermissionSeeder. Idempotent.
 */
return new class extends Migration
{
    private const array KEYS = ['nfc.view', 'nfc.manage'];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::KEYS as $key) {
            Permission::findOrCreate($key, 'web');
        }

        Role::query()->where('name', 'admin')->where('guard_name', 'web')->first()?->givePermissionTo(self::KEYS);
        Role::query()->where('name', 'production_operator')->where('guard_name', 'web')->first()?->givePermissionTo(['nfc.view']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->whereIn('name', self::KEYS)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
