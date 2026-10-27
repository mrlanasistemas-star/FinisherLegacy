<?php

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Models\Collection;
use App\Models\Event;
use App\Models\InventoryLevel;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Commerce\InventoryService;

/**
 * The public experience of the Finisher Legacy Tag
 * (docs/architecture/public-experience.md): Home, /colecciones, the tag
 * product page and the sitemap only ever show real, published data.
 */
function publicTag(array $attributes = [], int $units = 5, int $price = 89000): Product
{
    $product = Product::factory()->create([
        'type' => ProductType::LegacyTag,
        'qr_capable' => true,
        'tracks_units' => true,
        ...$attributes,
    ]);

    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'name' => 'Única',
        'attributes' => null,
        'base_price_minor' => $price,
    ]);

    InventoryLevel::create([
        'product_variant_id' => $variant->id,
        'inventory_location_id' => app(InventoryService::class)->defaultLocation()->id,
        'quantity_on_hand' => $units,
        'quantity_reserved' => 0,
    ]);

    return $product;
}

test('home renders the tag story with no collections, honestly empty', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Home')
            ->where('collections', [])
            ->where('tagProducts', []));
});

test('home lists only published collections with their real lowest price', function () {
    $published = Collection::factory()->create(['name' => 'Cozumel', 'year' => 2026, 'sort_order' => 1]);
    publicTag(['collection_id' => $published->id, 'name' => 'Tag Cozumel'], price: 99000);
    publicTag(['collection_id' => $published->id, 'name' => 'Tag Cozumel Edición'], price: 79000);
    Collection::factory()->create(['name' => 'Borrador', 'status' => ProductStatus::Draft]);
    Collection::factory()->create(['name' => 'Futura', 'launch_at' => now()->addMonth()]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Home')
            ->has('collections', 1)
            ->where('collections.0.name', 'Cozumel')
            ->where('collections.0.products_count', 2)
            ->where('collections.0.from_price_minor', 79000)
            ->where('collections.0.available', true)
            ->has('tagProducts', 2));
});

test('collections index shows only published collections', function () {
    Collection::factory()->create(['name' => 'Publicada', 'sort_order' => 2]);
    Collection::factory()->create(['name' => 'Primera', 'sort_order' => 1]);
    Collection::factory()->create(['name' => 'Borrador', 'status' => ProductStatus::Draft]);
    Collection::factory()->create(['name' => 'Archivada', 'status' => ProductStatus::Archived]);
    Collection::factory()->create(['name' => 'Futura', 'launch_at' => now()->addWeek()]);

    $this->get(route('collections.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('collections/Index')
            ->has('collections', 2)
            ->where('collections.0.name', 'Primera')
            ->where('collections.1.name', 'Publicada'));
});

test('an unpublished collection is a 404', function () {
    $draft = Collection::factory()->create(['status' => ProductStatus::Draft]);
    $future = Collection::factory()->create(['launch_at' => now()->addDay()]);

    $this->get(route('collections.show', $draft))->assertNotFound();
    $this->get(route('collections.show', $future))->assertNotFound();
    $this->get('/colecciones/no-existe')->assertNotFound();
});

test('collection page lists only its active products with price and availability', function () {
    $collection = Collection::factory()->create(['name' => 'Cozumel', 'accent_color' => '#1d6f8c']);
    publicTag(['collection_id' => $collection->id, 'name' => 'Disponible', 'sort_order' => 1], units: 3, price: 89000);
    publicTag(['collection_id' => $collection->id, 'name' => 'Agotado', 'sort_order' => 2], units: 0);
    publicTag(['collection_id' => $collection->id, 'name' => 'Inactivo', 'active' => false]);
    publicTag(['collection_id' => $collection->id, 'name' => 'Borrador', 'status' => ProductStatus::Draft]);
    publicTag(['name' => 'Otra colección']);
    $other = Collection::factory()->create(['name' => 'Otra']);

    $this->get(route('collections.show', $collection))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('collections/Show')
            ->where('collection.name', 'Cozumel')
            ->where('collection.accent_color', '#1d6f8c')
            ->has('collection.products', 2)
            ->where('collection.products.0.name', 'Disponible')
            ->where('collection.products.0.price_minor', 89000)
            ->where('collection.products.0.available', true)
            ->where('collection.products.1.name', 'Agotado')
            ->where('collection.products.1.available', false)
            ->has('otherCollections', 1)
            ->where('otherCollections.0.name', $other->name));
});

test('an accent color that is not a plain hex never reaches the page', function () {
    $collection = Collection::factory()->create(['accent_color' => 'red;x']);

    $this->get(route('collections.show', $collection))
        ->assertInertia(fn ($page) => $page->where('collection.accent_color', null));
});

test('legacy tag product page renders with its collection and only the specs it has', function () {
    $collection = Collection::factory()->create(['name' => 'Cozumel', 'year' => 2026]);
    $product = publicTag([
        'collection_id' => $collection->id,
        'slug' => 'tag-cozumel-2026',
        'strap_type' => 'Correa tejida',
        'material' => null,
    ], price: 89000);

    $this->get(route('store.products.show', $product))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('store/Show')
            ->where('product.type', 'legacy_tag')
            ->where('product.collection.name', 'Cozumel')
            ->where('product.collection.year', 2026)
            ->where('product.specs.strap_type', 'Correa tejida')
            ->where('product.specs.material', null)
            ->where('product.variants.0.base_price_minor', 89000)
            ->where('product.variants.0.in_stock', true)
            ->where('product.is_nfc', true));
});

test('a tag in an unpublished collection does not leak the collection', function () {
    $draft = Collection::factory()->create(['status' => ProductStatus::Draft]);
    $product = publicTag(['collection_id' => $draft->id]);

    $this->get(route('store.products.show', $product))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('product.collection', null));
});

test('store home features the tag ahead of a historical plate', function () {
    Product::factory()->legacyPlate()->create(['name' => 'Legacy Plate', 'sort_order' => 0]);
    publicTag(['name' => 'Finisher Legacy Tag', 'sort_order' => 5]);

    $this->get(route('store.products.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('store/Index')
            ->where('featured.name', 'Finisher Legacy Tag')
            ->where('featured.type', 'legacy_tag'));
});

test('sitemap lists public pages, published collections, active products and events only', function () {
    $published = Collection::factory()->create(['slug' => 'cozumel-2026']);
    $draft = Collection::factory()->create(['slug' => 'borrador-x', 'status' => ProductStatus::Draft]);
    publicTag(['slug' => 'tag-cozumel']);
    publicTag(['slug' => 'tag-inactivo', 'active' => false]);
    $event = Event::factory()->create(['slug' => 'maraton-publico']);

    $response = $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

    $xml = $response->getContent();

    expect($xml)
        ->toContain(route('home'))
        ->toContain(route('how-it-works'))
        ->toContain(route('collections.index'))
        ->toContain(route('collections.show', $published))
        ->toContain('/tienda/tag-cozumel')
        ->toContain(route('events.show', $event))
        ->not->toContain($draft->slug)
        ->not->toContain('tag-inactivo')
        ->not->toContain('/t/')
        ->not->toContain('/legacy/')
        ->not->toContain('/@');

    expect(simplexml_load_string($xml))->not->toBeFalse();
});

test('robots.txt points to the sitemap and keeps private surfaces out', function () {
    $robots = file_get_contents(public_path('robots.txt'));

    expect($robots)
        ->toContain('Sitemap:')
        ->toContain('/sitemap.xml')
        ->toContain('Disallow: /t/')
        ->toContain('Disallow: /mi-legacy');
    expect(file_exists(public_path('sitemap.xml')))->toBeFalse();
});
