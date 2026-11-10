<?php

use App\Enums\ProductType;
use App\Models\Product;
use App\Models\ProductVariant;

/*
 * Since 2026-10-27 the Home tells the Finisher Legacy Tag story and only
 * receives published collections + active tag products (the event and
 * community teasers left the Home; they live in Eventos / Comunidad).
 * Collections are covered in PublicExperienceTest.
 */

test('home page loads', function () {
    $response = $this->get(route('home'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('Home'));
});

test('home features only active tag products, never an inactive one or another type', function () {
    $visible = Product::factory()->create(['type' => ProductType::LegacyTag, 'name' => 'Visible Tag']);
    ProductVariant::factory()->create(['product_id' => $visible->id]);
    $hidden = Product::factory()->create(['type' => ProductType::LegacyTag, 'active' => false, 'name' => 'Hidden Tag']);
    ProductVariant::factory()->create(['product_id' => $hidden->id]);
    $apparel = Product::factory()->create(['name' => 'Trisuit']);
    ProductVariant::factory()->create(['product_id' => $apparel->id]);

    $this->get(route('home'))->assertInertia(fn ($page) => $page
        ->component('Home')
        ->where('tagProducts.0.name', 'Visible Tag')
        ->has('tagProducts', 1)
    );
});

test('home features no products when the catalog is empty', function () {
    $this->get(route('home'))->assertInertia(fn ($page) => $page
        ->component('Home')
        ->where('tagProducts', [])
        ->where('collections', [])
    );
});

test('home lists the priced products of the rest of the store apart from the tags', function () {
    $tag = Product::factory()->create(['type' => ProductType::LegacyTag, 'name' => 'Visible Tag']);
    ProductVariant::factory()->create(['product_id' => $tag->id]);
    $gear = Product::factory()->create(['name' => 'Racepack']);
    ProductVariant::factory()->create(['product_id' => $gear->id]);
    $hidden = Product::factory()->create(['name' => 'Hidden gear', 'active' => false]);
    ProductVariant::factory()->create(['product_id' => $hidden->id]);

    $this->get(route('home'))->assertInertia(fn ($page) => $page
        ->component('Home')
        ->has('gearProducts', 1)
        ->where('gearProducts.0.name', 'Racepack')
    );
});
