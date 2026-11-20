<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * `payments.refund`: full OpenPay refund from the admin order page
 * (App\Actions\Commerce\RefundOnlinePayment). Given to `admin`;
 * `super_admin` passes every gate but gets it explicitly too so the roles
 * matrix shows it. Idempotent; down() removes only this permission.
 */
return new class extends Migration
{
    private const string KEY = 'payments.refund';

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
