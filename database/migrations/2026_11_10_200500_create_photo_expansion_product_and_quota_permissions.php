<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * `quotas.manage` (Límites y tarifas) for admin, plus the internal,
 * never-listed product every photo-expansion OrderItem points at (same
 * pattern as `fotografia-digital`). No price is created here: tariffs
 * are configured by admin at /admin/limites-y-tarifas. Idempotent.
 */
return new class extends Migration
{
    private const array KEYS = ['quotas.manage'];

    private const string PRODUCT_SLUG = 'ampliacion-fotos-recuerdo';

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::KEYS as $key) {
            Permission::findOrCreate($key, 'web');
        }

        Role::query()->where('name', 'admin')->where('guard_name', 'web')->first()?->givePermissionTo(self::KEYS);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if (! DB::table('products')->where('slug', self::PRODUCT_SLUG)->exists()) {
            DB::table('products')->insert([
                'uuid' => (string) Str::uuid(),
                'name' => 'Ampliación de fotos del recuerdo',
                'slug' => self::PRODUCT_SLUG,
                'description' => 'Producto interno: espacio extra de fotos para un recuerdo Finisher Legacy. Sus precios se configuran en Límites y tarifas.',
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
        // The internal product is removed only if nothing was priced or sold with it.
        $productId = DB::table('products')->where('slug', self::PRODUCT_SLUG)->value('id');
        if ($productId !== null
            && ! DB::table('order_items')->where('product_id', $productId)->exists()
            && ! DB::table('product_price_schedules')->where('product_id', $productId)->exists()) {
            DB::table('products')->where('id', $productId)->delete();
        }

        Permission::query()->whereIn('name', self::KEYS)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
