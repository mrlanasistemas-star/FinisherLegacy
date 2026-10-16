<?php

use App\Actions\Commerce\MarkOrderPaid;
use App\Enums\InventoryMovementType;
use App\Models\InventoryLevel;
use App\Models\InventoryLocation;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Commerce\InventoryService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Admin COMERCIO workspace (spec §10-§16/§23): Resumen, Inventario
 * (table + matrix + safe adjust), Ventas, Product Show tabs, returns and
 * 3D product media — rendering and authorization.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $this->location = InventoryLocation::query()->firstOrCreate(
        ['slug' => config('finisher.commerce.default_inventory_location_slug')],
        ['name' => 'Main', 'active' => true],
    );
});

function a2Apparel(array $grid): Product
{
    $product = Product::factory()->create(['name' => 'Trisuit', 'type' => 'apparel', 'tracks_inventory' => true]);

    foreach ($grid as [$color, $size, $stock]) {
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => "{$color} {$size}",
            'attributes' => ['color' => $color, 'size' => $size],
        ]);
        app(InventoryService::class)->receive($variant, InventoryLocation::query()->firstOrFail(), $stock);
    }

    return $product;
}

test('Resumen answers the §11 questions in one screen', function () {
    $product = a2Apparel([['Negro', 'S', 0], ['Negro', 'M', 3], ['Negro', 'L', 20]]);
    $variant = $product->variants()->firstWhere('name', 'Negro L');
    $order = Order::factory()->create(['total_minor' => 90000, 'currency' => 'MXN']);
    $order->items()->create(['uuid' => (string) Str::uuid(), 'product_id' => $product->id, 'product_variant_id' => $variant->id, 'name' => 'Trisuit Negro L', 'sku' => $variant->sku, 'quantity' => 1, 'unit_price_minor' => 90000, 'line_total_minor' => 90000, 'currency' => 'MXN']);
    app(MarkOrderPaid::class)->handle($order);

    $this->actingAs($this->admin)->get('/admin/comercio')->assertOk()->assertInertia(fn ($page) => $page
        ->component('admin/commerce/Index')
        ->where('kpis.today.total_minor', 90000)
        ->where('kpis.month.orders', 1)
        ->where('kpis.orders.to_deliver', 1)
        ->where('topProducts.0.name', 'Trisuit')
        ->where('stock.counts.out', 1)
        ->where('stock.counts.low', 1)
        ->where('stock.counts.attention', 2)
        ->has('toDeliver', 1)
        ->has('series', 14));
});

test('Inventario table shows stock, reserved, available, sold and alert per variant', function () {
    a2Apparel([['Negro', 'XS', 2], ['Negro', 'M', 8]]);

    $this->actingAs($this->admin)->get('/admin/comercio/inventario')->assertOk()->assertInertia(fn ($page) => $page
        ->component('admin/commerce/Inventory')
        ->where('view', 'table')
        ->has('rows.data', 2)
        ->has('rows.data.0.sold')
        ->has('rows.data.0.reserved')
        ->has('reasons'));

    $this->actingAs($this->admin)->get('/admin/comercio/inventario?estado=low')
        ->assertInertia(fn ($page) => $page
            ->has('rows.data', 1)
            ->where('rows.data.0.size', 'XS')
            ->where('rows.data.0.color', 'Negro')
            ->where('rows.data.0.alert', 'low')
            ->where('rows.data.0.available', 2));

    $this->actingAs($this->admin)->get('/admin/comercio/inventario?q=Trisuit')
        ->assertInertia(fn ($page) => $page->has('rows.data', 2));

    // The historical URL still works and renders the same workspace page.
    $this->actingAs($this->admin)->get('/admin/inventory')->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/commerce/Inventory'));
});

test('the apparel matrix is colour × size in size order', function () {
    a2Apparel([['Negro', 'L', 3], ['Negro', 'XS', 2], ['Blanco', 'M', 0]]);

    $this->actingAs($this->admin)->get('/admin/comercio/inventario?vista=matriz')->assertOk()->assertInertia(fn ($page) => $page
        ->where('view', 'matrix')
        ->where('matrix.data.0.sizes', ['XS', 'M', 'L'])
        ->where('matrix.data.0.cells.Negro.XS.available', 2)
        ->where('matrix.data.0.cells.Blanco.M.alert', 'out')
        ->where('matrix.data.0.cells.Blanco.L', null));
});

test('the drawer adjust goes through the audited action with reason and user', function () {
    $product = a2Apparel([['Negro', 'M', 5]]);
    $variant = $product->variants()->first();

    $this->actingAs($this->admin)->post('/admin/comercio/inventario/ajustar', [
        'product_variant_id' => $variant->id, 'mode' => 'count', 'quantity' => 9, 'reason' => 'count', 'notes' => 'Inventario mensual',
    ])->assertRedirect();

    expect(InventoryLevel::query()->where('product_variant_id', $variant->id)->value('quantity_on_hand'))->toBe(9);
    $movement = InventoryMovement::query()->where('type', InventoryMovementType::Adjustment)->latest('id')->first();
    expect($movement->actor_id)->toBe($this->admin->id)->and($movement->reason)->toBe('count');

    $this->actingAs($this->admin)->getJson("/admin/comercio/inventario/variantes/{$variant->id}/movimientos")
        ->assertOk()
        ->assertJsonPath('data.0.reason', 'Conteo físico')
        ->assertJsonPath('data.0.quantity', 4);

    $this->actingAs($this->admin)->patch("/admin/comercio/inventario/variantes/{$variant->id}/minimo", ['minimum_stock' => 3])->assertRedirect();
    expect($variant->fresh()->minimum_stock)->toBe(3);

    $this->actingAs($this->admin)->post('/admin/comercio/inventario/ajustar', [
        'product_variant_id' => $variant->id, 'mode' => 'adjust', 'quantity' => 0,
    ])->assertSessionHasErrors('quantity');
});

test('Ventas renders sales by day and by product', function () {
    $this->actingAs($this->admin)->get('/admin/comercio/ventas?periodo=7')->assertOk()->assertInertia(fn ($page) => $page
        ->component('admin/commerce/Sales')
        ->where('period', 7)
        ->has('series', 7)
        ->has('products')
        ->has('totals'));
});

test('product show carries the five tabs of data', function () {
    $product = a2Apparel([['Negro', 'S', 1], ['Negro', 'M', 4]]);

    $this->actingAs($this->admin)->get("/admin/products/{$product->id}")->assertOk()->assertInertia(fn ($page) => $page
        ->component('admin/products/Show')
        ->has('variants', 2)
        ->has('variants.0.alert')
        ->has('matrix.cells.Negro')
        ->has('sales.series', 30)
        ->has('recentOrders')
        ->has('reasons'));
});

test('a delivered item can be returned from the admin order', function () {
    $product = a2Apparel([['Negro', 'M', 5]]);
    $variant = $product->variants()->first();
    $order = Order::factory()->create(['total_minor' => 10000]);
    $item = $order->items()->create(['uuid' => (string) Str::uuid(), 'product_id' => $product->id, 'product_variant_id' => $variant->id, 'name' => 'Trisuit', 'sku' => $variant->sku, 'quantity' => 1, 'unit_price_minor' => 10000, 'line_total_minor' => 10000, 'currency' => 'MXN', 'fulfilled_at' => now()]);

    $this->actingAs($this->admin)->post("/admin/orders/items/{$item->id}/return", ['quantity' => 1])->assertRedirect();

    expect(InventoryLevel::query()->where('product_variant_id', $variant->id)->value('quantity_on_hand'))->toBe(6);

    $this->actingAs($this->admin)->get("/admin/orders/{$order->uuid}")
        ->assertInertia(fn ($page) => $page->where('items.0.returned_quantity', 1)->has('order.delivery'));
});

test('people without commerce permissions cannot open the workspace', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('dashboard.admin.view');

    $this->actingAs($user)->get('/admin/comercio')->assertForbidden();
    $this->actingAs($user)->get('/admin/comercio/inventario')->assertForbidden();
    $this->actingAs($user)->get('/admin/comercio/ventas')->assertForbidden();
    $this->actingAs($user)->post('/admin/comercio/inventario/ajustar', ['product_variant_id' => 1, 'quantity' => 1])->assertForbidden();

    $customer = User::factory()->create();
    $this->actingAs($customer)->get('/admin/comercio')->assertForbidden();
});

test('a GLB model uploads under products/{uuid}/models3d with a poster and serializes in the shared shape', function () {
    Storage::fake('product_media');
    $product = Product::factory()->create();
    $glb = UploadedFile::fake()->createWithContent('trisuit.glb', 'glTF'.str_repeat("\0", 64));

    $this->actingAs($this->admin)->post("/admin/products/{$product->id}/media", [
        'model' => $glb,
        'poster' => UploadedFile::fake()->image('poster.jpg'),
        'alt_text' => 'Trisuit en 3D',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $media = ProductMedia::query()->where('product_id', $product->id)->firstOrFail();
    expect($media->type->value)->toBe('model_3d')
        ->and($media->path)->toStartWith("products/{$product->uuid}/models3d/")
        ->and($media->path)->toEndWith('.glb')
        ->and($media->poster_path)->toStartWith("products/{$product->uuid}/images/")
        ->and($media->uuid)->not->toBeNull();
    Storage::disk('product_media')->assertExists($media->path);

    expect(array_keys($media->toMediaArray()))->toBe(['uuid', 'type', 'url', 'poster_url', 'alt', 'is_primary', 'position'])
        ->and($media->toMediaArray()['alt'])->toBe('Trisuit en 3D');
});

test('a fake GLB or another extension is rejected', function () {
    Storage::fake('product_media');
    $product = Product::factory()->create();

    $this->actingAs($this->admin)->post("/admin/products/{$product->id}/media", [
        'model' => UploadedFile::fake()->createWithContent('evil.glb', 'not a model'),
    ])->assertSessionHasErrors('model');

    $this->actingAs($this->admin)->post("/admin/products/{$product->id}/media", [
        'model' => UploadedFile::fake()->createWithContent('model.obj', 'v 0 0 0'),
    ])->assertSessionHasErrors('model');

    expect(ProductMedia::query()->count())->toBe(0);
});

test('a poster can be set on a video', function () {
    Storage::fake('product_media');
    $product = Product::factory()->create();
    $video = ProductMedia::factory()->for($product)->create(['type' => 'video', 'disk' => 'product_media']);

    $this->actingAs($this->admin)->post("/admin/products/media/{$video->id}/poster", [
        'poster' => UploadedFile::fake()->image('p.jpg'),
    ])->assertRedirect();

    expect($video->fresh()->poster_path)->not->toBeNull();
});
