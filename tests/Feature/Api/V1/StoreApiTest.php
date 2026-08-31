<?php

use App\Models\Athlete;
use App\Models\AthleteOwnedProduct;
use App\Models\InventoryLocation;
use App\Models\LegacyPlateModel;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Commerce\InventoryService;
use Illuminate\Support\Str;

/**
 * API E2E using only /api/v1 (brief §175): store browse → cart → checkout →
 * order, entirely through HTTP, no Inertia involved. Reuses
 * apiAuthHeader() declared in tests/Feature/Api/V1/MedalTest.php — PHP
 * function declarations are global, and Pest loads every test file, so a
 * second declaration here would fatal on "cannot redeclare".
 */
test('GET /api/v1/store/products lists only active products with no exact stock exposed', function () {
    $product = Product::factory()->create(['active' => true, 'status' => 'active']);
    ProductVariant::factory()->create(['product_id' => $product->id]);
    Product::factory()->create(['active' => false]);

    $response = $this->getJson('/api/v1/store/products');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0'))->not->toHaveKey('stock');
});

test('GET /api/v1/store/products/{slug} returns variants', function () {
    $product = Product::factory()->create(['active' => true, 'status' => 'active']);
    ProductVariant::factory()->create(['product_id' => $product->id, 'name' => 'M']);

    $response = $this->getJson("/api/v1/store/products/{$product->slug}");

    $response->assertOk()
        ->assertJsonPath('data.slug', $product->slug)
        ->assertJsonCount(1, 'data.variants');
});

test('a full cart-to-order flow works entirely over HTTP', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['tracks_inventory' => true]);
    $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'base_price_minor' => 30000]);
    $location = InventoryLocation::create(['name' => 'Main Warehouse', 'slug' => config('finisher.commerce.default_inventory_location_slug'), 'active' => true]);
    app(InventoryService::class)->receive($variant, $location, 10);

    $headers = apiAuthHeader($user);

    $add = $this->withHeaders($headers)->postJson('/api/v1/cart/items', [
        'product_variant_id' => $variant->id,
        'quantity' => 2,
    ]);
    $add->assertOk();
    expect($add->json('data.items'))->toHaveCount(1);

    $checkout = $this->withHeaders($headers)->postJson('/api/v1/checkout');
    $checkout->assertCreated();
    $orderUuid = $checkout->json('data.uuid');

    expect($checkout->json('data.total_minor'))->toBe(60000);

    $show = $this->withHeaders($headers)->getJson("/api/v1/orders/{$orderUuid}");
    $show->assertOk()->assertJsonPath('data.uuid', $orderUuid);

    $index = $this->withHeaders($headers)->getJson('/api/v1/orders');
    $index->assertOk();
    expect($index->json('data'))->toHaveCount(1);
});

test('GET /api/v1/legacy-plate-models only returns active models', function () {
    LegacyPlateModel::factory()->create(['active' => true, 'name' => 'Núcleo Reveal']);
    LegacyPlateModel::factory()->create(['active' => false]);

    $response = $this->getJson('/api/v1/legacy-plate-models');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});

test('GET /api/v1/gear/{code} is public and leaks no owner PII', function () {
    $athlete = Athlete::factory()->create();
    $owned = AthleteOwnedProduct::create([
        'uuid' => (string) Str::uuid(),
        'athlete_id' => $athlete->id,
        'product_id' => Product::factory()->create()->id,
        'asset_code' => 'AST-TEST1234',
        'status' => 'active',
        'acquired_at' => now(),
    ]);

    $response = $this->getJson("/api/v1/gear/{$owned->asset_code}");

    $response->assertOk();
    $body = $response->json('data');
    expect($body)->not->toHaveKey('athlete')
        ->and(json_encode($body))->not->toContain($athlete->email ?? 'unlikely-marker');
});

test('an unauthenticated request to a protected store endpoint is rejected', function () {
    $this->getJson('/api/v1/cart')->assertUnauthorized();
});
