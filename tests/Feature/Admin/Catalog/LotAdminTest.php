<?php

use App\Actions\LegacyTags\CreateLot;
use App\Enums\InventoryLotStatus;
use App\Enums\LegacyTagStatus;
use App\Models\InventoryLevel;
use App\Models\InventoryLot;
use App\Models\LegacyTag;
use App\Models\Supplier;
use App\Support\LegacyTags\TagCode;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/../../LegacyTags/helpers.php';

test('admin creates a lot through CreateLot: one manufactured tag per piece', function () {
    $admin = tagStaff();
    $variant = tagVariant();
    $supplier = Supplier::query()->create(['name' => 'Fábrica', 'status' => 'active']);

    $this->actingAs($admin)->post('/admin/inventario/lotes', [
        'product_variant_id' => $variant->id,
        'quantity' => 4,
        'supplier_id' => $supplier->id,
        'unit_cost_minor' => 8500,
        'reference' => 'OC-77',
    ])->assertSessionHasNoErrors()->assertRedirect();

    $lot = InventoryLot::query()->latest('id')->firstOrFail();

    expect($lot->quantity)->toBe(4)
        ->and($lot->supplier_id)->toBe($supplier->id)
        ->and($lot->unit_cost_minor)->toBe(8500)
        ->and($lot->status)->toBe(InventoryLotStatus::Ordered)
        ->and(LegacyTag::query()->where('inventory_lot_id', $lot->id)->where('status', 'manufactured')->count())->toBe(4);

    $this->actingAs($admin)->get("/admin/inventario/lotes/{$lot->uuid}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/inventory/lots/Show')->has('tags.data', 4)->where('lot.code', $lot->code));

    $this->actingAs($admin)->get('/admin/inventario/lotes?supplier='.$supplier->id)
        ->assertInertia(fn (Assert $page) => $page->component('admin/inventory/lots/Index')->has('lots.data', 1));
});

test('lot quantity is bounded and the product must track units', function () {
    $admin = tagStaff();
    $variant = tagVariant();

    $this->actingAs($admin)->post('/admin/inventario/lotes', ['product_variant_id' => $variant->id, 'quantity' => 5001])
        ->assertSessionHasErrors('quantity');

    $variant->product->update(['tracks_units' => false]);
    $this->actingAs($admin)->post('/admin/inventario/lotes', ['product_variant_id' => $variant->id, 'quantity' => 2])
        ->assertSessionHasErrors('product_variant_id');
});

test('the factory CSV carries serial, code, display code and both URLs', function () {
    $admin = tagStaff();
    $variant = tagVariant();
    $this->actingAs($admin)->post('/admin/inventario/lotes', ['product_variant_id' => $variant->id, 'quantity' => 3]);
    $lot = InventoryLot::query()->latest('id')->firstOrFail();
    $tags = LegacyTag::query()->where('inventory_lot_id', $lot->id)->orderBy('id')->get();

    $response = $this->actingAs($admin)->get("/admin/inventario/lotes/{$lot->uuid}/codigos.csv");
    $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $lines = array_values(array_filter(explode("\n", str_replace("\xEF\xBB\xBF", '', $response->streamedContent()))));
    $rows = array_map('str_getcsv', $lines);

    // NFC only — the tag no longer carries a printed QR.
    expect($rows[0])->toBe(['serial', 'public_code', 'display_code', 'nfc_url'])
        ->and($rows)->toHaveCount(4)
        ->and($rows[1][0])->toBe($tags[0]->serial)
        ->and($rows[1][1])->toBe($tags[0]->public_code)
        ->and($rows[1][2])->toBe(TagCode::format($tags[0]->public_code))
        ->and($rows[1][3])->toEndWith('/t/'.$tags[0]->public_code)
        ->and($rows[1])->toHaveCount(4);
});

test('receiving a lot stocks good pieces and disables the defective ones (pasted URL or serial)', function () {
    $admin = tagStaff();
    $variant = tagVariant();
    $warehouse = tagLocation();
    $this->actingAs($admin)->post('/admin/inventario/lotes', ['product_variant_id' => $variant->id, 'quantity' => 5]);
    $lot = InventoryLot::query()->latest('id')->firstOrFail();
    $tags = LegacyTag::query()->where('inventory_lot_id', $lot->id)->orderBy('id')->get();

    $this->actingAs($admin)->post("/admin/inventario/lotes/{$lot->uuid}/recibir", [
        'inventory_location_id' => $warehouse->id,
        'defective' => "https://finisherlegacy.com/t/{$tags[0]->public_code}?src=qr\n{$tags[1]->serial}",
        'issues' => '2 correas rotas',
    ])->assertSessionHasNoErrors();

    $lot->refresh();
    expect($lot->status)->toBe(InventoryLotStatus::Received)
        ->and($lot->quantity_received)->toBe(3)
        ->and($lot->issues)->toBe('2 correas rotas')
        ->and((int) InventoryLevel::query()->where('product_variant_id', $variant->id)->where('inventory_location_id', $warehouse->id)->value('quantity_on_hand'))->toBe(3)
        ->and($tags[0]->fresh()->status)->toBe(LegacyTagStatus::Disabled)
        ->and($tags[1]->fresh()->status)->toBe(LegacyTagStatus::Disabled)
        ->and($tags[2]->fresh()->status)->toBe(LegacyTagStatus::InStock);

    // Second reception refused as a validation error (no 500).
    $this->actingAs($admin)->post("/admin/inventario/lotes/{$lot->uuid}/recibir", ['inventory_location_id' => $warehouse->id])
        ->assertSessionHasErrors('inventory_location_id');
});

test('unknown defective codes are rejected before anything is received', function () {
    $admin = tagStaff();
    $variant = tagVariant();
    $this->actingAs($admin)->post('/admin/inventario/lotes', ['product_variant_id' => $variant->id, 'quantity' => 2]);
    $lot = InventoryLot::query()->latest('id')->firstOrFail();

    $this->actingAs($admin)->post("/admin/inventario/lotes/{$lot->uuid}/recibir", [
        'inventory_location_id' => tagLocation()->id, 'defective' => 'ABCDEFGHJKMN',
    ])->assertSessionHasErrors('defective');

    expect($lot->fresh()->status)->toBe(InventoryLotStatus::Ordered);
});

test('sales staff can view lots but not create, export or receive', function () {
    $staff = tagStaff('sales_staff');
    $variant = tagVariant();
    $lot = app(CreateLot::class)->handle($variant, 1, [], tagStaff());

    $this->actingAs($staff)->get('/admin/inventario/lotes')->assertOk();
    $this->actingAs($staff)->get("/admin/inventario/lotes/{$lot->uuid}")->assertOk();
    $this->actingAs($staff)->get('/admin/inventario/lotes/crear')->assertForbidden();
    $this->actingAs($staff)->post('/admin/inventario/lotes', ['product_variant_id' => $variant->id, 'quantity' => 1])->assertForbidden();
    $this->actingAs($staff)->get("/admin/inventario/lotes/{$lot->uuid}/codigos.csv")->assertForbidden();
    $this->actingAs($staff)->post("/admin/inventario/lotes/{$lot->uuid}/recibir", ['inventory_location_id' => tagLocation()->id])->assertForbidden();
});
