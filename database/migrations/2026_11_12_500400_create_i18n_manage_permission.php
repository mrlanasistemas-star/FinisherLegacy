<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * `i18n.manage` (Idiomas y traducciones, docs/renovacion/idiomas.md):
 * review/edit translations, enable languages and launch a sync. Given to
 * `admin`; `super_admin` passes every gate but gets it explicitly too so
 * the roles matrix shows it. Idempotent; down() removes only this permission.
 */
return new class extends Migration
{
    private const string KEY = 'i18n.manage';

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::findOrCreate(self::KEY, 'web');

        Role::query()
            ->whereIn('name', ['super_admin', 'admin'])
            ->where('guard_name', 'web')
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo(self::KEY));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->where('name', self::KEY)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
