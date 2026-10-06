<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * The two permissions added with the Nosotros/Contacto content admin and
 * community moderation (config/permissions.php stays the catalog). Also
 * created here so an existing deployment gets them — and the `admin`
 * role keeps "every permission" — without re-running
 * RolePermissionSeeder. Idempotent; creates no content.
 */
return new class extends Migration
{
    private const array KEYS = ['content.manage', 'community.moderate'];

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
