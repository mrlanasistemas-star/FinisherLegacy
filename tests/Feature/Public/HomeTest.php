<?php

use App\Enums\EditionStatus;
use App\Enums\EventStatus;
use App\Models\EventEdition;
use App\Models\Product;
use App\Models\ProductVariant;

test('home page loads', function () {
    $response = $this->get(route('home'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('Home'));
});

test('home page only features published, upcoming editions', function () {
    $published = EventEdition::factory()->create([
        'event_date' => now()->addWeek(),
        'status' => EditionStatus::Published,
    ]);
    $published->event()->update(['status' => EventStatus::Published]);

    $draftEdition = EventEdition::factory()->create([
        'event_date' => now()->addWeek(),
        'status' => EditionStatus::Draft,
    ]);
    $draftEdition->event()->update(['status' => EventStatus::Published]);

    $response = $this->get(route('home'));

    $response->assertInertia(fn ($page) => $page
        ->component('Home')
        ->where('featuredEditions.0.id', $published->id)
        ->has('featuredEditions', 1)
    );
});

/**
 * The Home "Tienda" teaser (public store visibility, brief item A5) reuses
 * the same active-product query the storefront itself uses — never an
 * inactive product, never more than a handful.
 */
test('home features only active products, never an inactive one', function () {
    $visible = Product::factory()->create(['active' => true, 'status' => 'active', 'name' => 'Visible Product']);
    ProductVariant::factory()->create(['product_id' => $visible->id]);
    $hidden = Product::factory()->create(['active' => false, 'status' => 'active', 'name' => 'Hidden Product']);
    ProductVariant::factory()->create(['product_id' => $hidden->id]);

    $response = $this->get(route('home'));

    $response->assertInertia(fn ($page) => $page
        ->component('Home')
        ->where('featuredProducts.0.name', 'Visible Product')
        ->has('featuredProducts', 1)
    );
});

test('home features no products when the catalog is empty', function () {
    $response = $this->get(route('home'));

    $response->assertInertia(fn ($page) => $page
        ->component('Home')
        ->where('featuredProducts', [])
    );
});
