<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * - `photographer` role + `photographer.portal` permission (the portal).
 * - `photos.manage` for admins (review queue, photographers, payouts).
 * - The internal, never-listed "Fotografía digital" product every photo
 *   OrderItem points at (order_items.product_id is required) — inactive
 *   and archived so the storefront never shows it.
 */
return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::findOrCreate('photographer.portal', 'web');
        Permission::findOrCreate('photos.manage', 'web');

        Role::findOrCreate('photographer', 'web')->givePermissionTo('photographer.portal');
        Role::query()->where('name', 'admin')->where('guard_name', 'web')->first()?->givePermissionTo(['photos.manage']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if (! DB::table('products')->where('slug', 'fotografia-digital')->exists()) {
            DB::table('products')->insert([
                'uuid' => (string) Str::uuid(),
                'name' => 'Fotografía digital',
                'slug' => 'fotografia-digital',
                'description' => 'Producto interno para las fotografías de evento vendidas por fotógrafos.',
                'type' => 'digital_photo',
                'brand' => 'Finisher Legacy',
                'status' => 'archived',
                'availability' => 'available',
                'sort_order' => 9999,
                'taxable' => false,
                'requires_shipping' => false,
                'qr_capable' => false,
                'tracks_inventory' => false,
                'active' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('products')->where('slug', 'fotografia-digital')->delete();
        Permission::query()->whereIn('name', ['photographer.portal', 'photos.manage'])->delete();
        Role::query()->where('name', 'photographer')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
