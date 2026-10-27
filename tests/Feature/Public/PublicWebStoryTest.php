<?php

use App\Enums\OrderPaymentStatus;
use App\Enums\ProductAvailability;
use App\Enums\ProductType;
use App\Models\InventoryLevel;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Services\Commerce\InventoryService;
use Illuminate\Support\Str;

/** A sellable product with one variant per [color, size, units]. */
function storeProduct(array $attributes = [], array $variants = [['Negro', 'M', 10]]): Product
{
    $product = Product::factory()->create($attributes);
    $location = app(InventoryService::class)->defaultLocation();

    foreach ($variants as [$color, $size, $units]) {
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => trim("{$color} / {$size}", ' /'),
            'attributes' => array_filter(['color' => $color, 'size' => $size]),
            'base_price_minor' => 50000,
        ]);
        InventoryLevel::create([
            'product_variant_id' => $variant->id,
            'inventory_location_id' => $location->id,
            'quantity_on_hand' => $units,
            'quantity_reserved' => 0,
        ]);
    }

    return $product;
}

test('home renders the tag story with real tag products only', function () {
    storeProduct(['name' => 'Trisuit', 'sort_order' => 0]);
    storeProduct(['name' => 'Legacy Plate', 'type' => ProductType::LegacyPlate, 'sort_order' => 1]);
    storeProduct(['name' => 'Finisher Legacy Tag', 'type' => ProductType::LegacyTag, 'sort_order' => 5]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Home')
            ->where('tagProducts.0.name', 'Finisher Legacy Tag')
            ->where('tagProducts.0.is_nfc', true)
            ->has('tagProducts', 1)
            ->has('collections'));
});

test('como funciona renders and the old url redirects there', function () {
    $this->get('/como-funciona')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('HowItWorks'));

    $this->get('/how-it-works')->assertRedirect('/como-funciona')->assertStatus(301);
    expect(route('how-it-works', absolute: false))->toBe('/como-funciona');
});

test('store home exposes its editorial sections without exact stock counts', function () {
    storeProduct(['name' => 'Trisuit', 'qr_capable' => true], [['Negro', 'S', 2], ['Negro', 'M', 0], ['Rojo', 'S', 1]]);

    $this->get('/tienda')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('store/Index')
            ->has('sections.featured', 1)
            ->has('sections.nfc', 1)
            ->where('sections.raceFavoritesBySales', false)
            ->where('products.0.stock_level', 'low')
            ->where('products.0.colors.0.name', 'Negro')
            ->where('products.0.sizes', [
                ['label' => 'S', 'in_stock' => true],
                ['label' => 'M', 'in_stock' => false],
            ])
            ->where('products.0.is_nfc', true)
            ->missing('products.0.quantity_on_hand'));
});

test('race favorites rank by units on paid orders when there are sales', function () {
    $popular = storeProduct(['name' => 'Popular']);
    $other = storeProduct(['name' => 'Other']);
    $quiet = storeProduct(['name' => 'Quiet']);

    foreach ([[$popular, 5, OrderPaymentStatus::Paid], [$other, 2, OrderPaymentStatus::Paid], [$quiet, 50, OrderPaymentStatus::Pending]] as [$product, $qty, $status]) {
        $order = Order::factory()->create(['payment_status' => $status]);
        OrderItem::create([
            'uuid' => (string) Str::uuid(),
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $product->variants->first()->id,
            'name' => $product->name,
            'sku' => 'SKU-'.Str::random(6),
            'quantity' => $qty,
            'unit_price_minor' => 1000,
            'line_total_minor' => 1000 * $qty,
            'currency' => 'MXN',
        ]);
    }

    $this->get('/tienda')->assertInertia(fn ($page) => $page
        ->where('sections.raceFavoritesBySales', true)
        ->where('sections.raceFavorites.0.name', 'Popular')
        ->where('sections.raceFavorites.1.name', 'Other')
        ->has('sections.raceFavorites', 2));
});

test('the legacy category query string redirects to the category page', function () {
    $category = ProductCategory::create(['name' => 'Textil', 'slug' => 'textil', 'active' => true]);

    $this->get('/tienda?category=textil')->assertRedirect(route('store.categories.show', $category));
});

test('category listing filters by size, nfc and availability and paginates', function () {
    $category = ProductCategory::create(['name' => 'Textil', 'slug' => 'textil', 'active' => true]);
    storeProduct(['name' => 'Jersey NFC', 'category_id' => $category->id, 'qr_capable' => true], [['Negro', 'L', 3]]);
    storeProduct(['name' => 'Short', 'category_id' => $category->id], [['Negro', 'M', 3]]);
    storeProduct(['name' => 'Gorra', 'category_id' => $category->id, 'availability' => ProductAvailability::Concept], [['Negro', 'L', 0]]);
    storeProduct(['name' => 'Fuera de categoría', 'qr_capable' => true], [['Negro', 'L', 3]]);

    $this->get(route('store.categories.show', $category))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('store/Catalog')
            ->where('category.slug', 'textil')
            ->where('products.total', 3)
            ->where('sizes', ['M', 'L']));

    $this->get(route('store.categories.show', ['category' => $category, 'talla' => 'L', 'disponibles' => 1]))
        ->assertInertia(fn ($page) => $page
            ->where('products.total', 1)
            ->where('products.data.0.name', 'Jersey NFC')
            ->where('filters.talla', 'L')
            ->where('filters.disponibles', true));

    $this->get(route('store.catalog', ['nfc' => 1]))
        ->assertInertia(fn ($page) => $page
            ->component('store/Catalog')
            ->where('category', null)
            ->where('products.total', 2));
});

test('catalog sorts by price', function () {
    $cheap = storeProduct(['name' => 'Barato']);
    $cheap->variants()->update(['base_price_minor' => 10000]);
    storeProduct(['name' => 'Caro']);

    $this->get(route('store.catalog', ['orden' => 'precio-asc']))
        ->assertInertia(fn ($page) => $page->where('products.data.0.name', 'Barato'));
    $this->get(route('store.catalog', ['orden' => 'precio-desc']))
        ->assertInertia(fn ($page) => $page->where('products.data.0.name', 'Caro'));
});

test('an inactive category and internal photo products are not browsable', function () {
    $category = ProductCategory::create(['name' => 'Oculta', 'slug' => 'oculta', 'active' => false]);
    $photo = Product::factory()->create(['type' => ProductType::DigitalPhoto, 'slug' => 'foto-digital']);

    $this->get(route('store.categories.show', $category))->assertNotFound();
    $this->get('/tienda/foto-digital')->assertNotFound();
    $this->get('/tienda')->assertInertia(fn ($page) => $page->has('products', 0));
    expect($photo->exists)->toBeTrue();
});

test('product detail serializes 3D media in the shared media contract', function () {
    $product = storeProduct(['name' => 'Chill Band', 'qr_capable' => true]);
    ProductMedia::factory()->create(['product_id' => $product->id, 'type' => 'image', 'is_primary' => true, 'sort_order' => 0, 'alt_text' => 'Frente']);
    ProductMedia::factory()->create([
        'product_id' => $product->id,
        'type' => 'model_3d',
        'path' => 'products/chill-band.glb',
        'mime' => 'model/gltf-binary',
        'poster_path' => 'products/chill-band-poster.jpg',
        'sort_order' => 1,
        'alt_text' => 'Chill Band en 3D',
    ]);

    $this->get("/tienda/{$product->slug}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('store/Show')
            ->has('product.gallery', 2)
            ->where('product.gallery.1.type', 'model_3d')
            ->where('product.gallery.1.alt', 'Chill Band en 3D')
            ->where('product.gallery.1.position', 1)
            ->where('product.gallery.1.is_primary', false)
            ->where('product.gallery.1.poster_url', fn ($url) => str_ends_with($url, 'products/chill-band-poster.jpg'))
            ->where('product.gallery.1.url', fn ($url) => str_ends_with($url, 'products/chill-band.glb'))
            ->has('product.gallery.1.uuid')
            ->where('product.is_nfc', true)
            ->where('product.variants.0.in_stock', true)
            ->where('product.variants.0.low_stock', false));
});

test('product detail works with no media at all', function () {
    $product = storeProduct(['name' => 'Sin fotos', 'slug' => 'sin-fotos']);

    $this->get('/tienda/sin-fotos')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('store/Show')->has('product.gallery', 0));
    expect($product->media()->count())->toBe(0);
});

test('store pages do not run a query per product card', function () {
    foreach (range(1, 6) as $i) {
        storeProduct(['name' => "Producto {$i}"], [['Negro', 'S', 3], ['Negro', 'M', 3], ['Rojo', 'S', 3]]);
    }

    $count = function (string $url): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get($url)->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    };

    // Warm request-independent caches (promotions, company settings) first.
    $this->get('/tienda/catalogo');
    $catalogQueries = $count('/tienda/catalogo');

    foreach (range(7, 18) as $i) {
        storeProduct(['name' => "Producto {$i}"], [['Negro', 'S', 3], ['Negro', 'M', 3]]);
    }

    expect($count('/tienda/catalogo'))->toBe($catalogQueries);
});
