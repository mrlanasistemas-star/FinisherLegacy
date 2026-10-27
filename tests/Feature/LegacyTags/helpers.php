<?php

use App\Actions\LegacyTags\CreateLot;
use App\Actions\LegacyTags\ReceiveLot;
use App\Enums\InventoryLocationType;
use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\Ally;
use App\Models\Collection;
use App\Models\InventoryLocation;
use App\Models\LegacyTag;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Str;

/*
| Shared fixtures for tests/Feature/LegacyTags/* — required with
| require_once so each function is declared once per worker.
*/

function tagStaff(string $role = 'admin'): User
{
    test()->seed(RolePermissionSeeder::class);
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function tagVariant(int $priceMinor = 39900): ProductVariant
{
    $collection = Collection::factory()->create(['name' => 'Cozumel 2026', 'slug' => 'cozumel-2026-'.Str::lower(Str::random(4))]);
    $product = Product::factory()->create([
        'type' => ProductType::LegacyTag,
        'status' => ProductStatus::Active,
        'active' => true,
        'tracks_inventory' => true,
        'tracks_units' => true,
        'qr_capable' => false,
        'collection_id' => $collection->id,
    ]);

    return ProductVariant::factory()->create(['product_id' => $product->id, 'base_price_minor' => $priceMinor, 'sku' => 'FLT-COZ-'.Str::upper(Str::random(5))]);
}

function tagLocation(InventoryLocationType $type = InventoryLocationType::Warehouse, ?Ally $ally = null): InventoryLocation
{
    return InventoryLocation::query()->create([
        'name' => $type->label().' '.Str::random(4),
        'slug' => $type->value.'-'.Str::lower(Str::random(6)),
        'type' => $type,
        'ally_id' => $ally?->id,
        'active' => true,
    ]);
}

/**
 * A received lot of `$quantity` in-stock tags at `$location`.
 *
 * @return Illuminate\Support\Collection<int, LegacyTag>
 */
function tagStock(ProductVariant $variant, InventoryLocation $location, int $quantity, ?User $actor = null)
{
    $actor ??= User::factory()->create();
    $lot = app(CreateLot::class)->handle($variant, $quantity, [], $actor);
    app(ReceiveLot::class)->handle($lot, $location, $actor);

    return LegacyTag::query()->where('inventory_lot_id', $lot->id)->orderBy('id')->get();
}
