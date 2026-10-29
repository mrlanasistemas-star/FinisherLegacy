<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Admin-editable payment configuration (/admin/configuracion/pagos). One
 * row; any value left null falls back to config/finisher.php (.env). The
 * private key column is written through Laravel's `encrypted` cast
 * (APP_KEY), never stored in plain text.
 *
 * Also creates the `payments.configure` permission (config/permissions.php
 * stays the catalog) and grants it to `admin`, so an existing deployment
 * gets it without re-running RolePermissionSeeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_settings', function (Blueprint $table) {
            $table->id();
            $table->string('default_gateway', 20)->nullable();
            $table->string('openpay_merchant_id', 100)->nullable();
            $table->string('openpay_public_key', 191)->nullable();
            $table->text('openpay_private_key')->nullable();
            $table->string('openpay_private_key_last4', 4)->nullable();
            $table->string('openpay_country', 2)->nullable();
            $table->boolean('openpay_production_mode')->nullable();
            $table->boolean('openpay_use_3d_secure')->default(false);
            $table->timestamp('openpay_verified_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('payments.configure', 'web');
        Role::query()->where('name', 'admin')->where('guard_name', 'web')->first()?->givePermissionTo('payments.configure');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_settings');
        Permission::query()->where('name', 'payments.configure')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
