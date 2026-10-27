<?php

use App\Enums\ProductStatus;
use App\Models\Collection;
use App\Models\Event;
use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/../../LegacyTags/helpers.php';

test('athletes cannot reach the collections admin', function () {
    $athlete = tagStaff('athlete');

    $this->actingAs($athlete)->get('/admin/catalogo/colecciones')->assertForbidden();
    $this->actingAs($athlete)->post('/admin/catalogo/colecciones', ['name' => 'X', 'status' => 'draft'])->assertForbidden();
});

test('sales staff without products.manage cannot manage collections', function () {
    $this->actingAs(tagStaff('sales_staff'))->get('/admin/catalogo/colecciones')->assertForbidden();
});

test('admin lists collections with filters and search', function () {
    $admin = tagStaff();
    Collection::factory()->create(['name' => 'Cozumel 2026', 'discipline' => 'Triatlón', 'year' => 2026, 'status' => ProductStatus::Active]);
    Collection::factory()->create(['name' => 'Valle 2025', 'discipline' => 'Trail', 'year' => 2025, 'status' => ProductStatus::Draft]);

    $this->actingAs($admin)->get('/admin/catalogo/colecciones?discipline=Trail')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/catalog/collections/Index')
            ->has('collections.data', 1)
            ->where('collections.data.0.name', 'Valle 2025')
            ->where('disciplines', ['Trail', 'Triatlón']));

    $this->actingAs($admin)->get('/admin/catalogo/colecciones?q=cozu&status=active&year=2026')
        ->assertInertia(fn (Assert $page) => $page->has('collections.data', 1)->where('collections.data.0.name', 'Cozumel 2026'));
});

test('admin creates a collection with auto slug and images on the public disk', function () {
    Storage::fake('public');
    $admin = tagStaff();
    $event = Event::factory()->create();

    $this->actingAs($admin)->post('/admin/catalogo/colecciones', [
        'name' => 'Finisher Legacy — Cozumel 2026',
        'discipline' => 'Triatlón',
        'destination' => 'Cozumel',
        'year' => 2026,
        'event_id' => $event->id,
        'status' => 'active',
        'launch_at' => '2026-11-01',
        'accent_color' => '#C9A45C',
        'seo_title' => 'Cozumel 2026',
        'cover' => UploadedFile::fake()->image('cover.jpg', 1200, 800),
        'hero' => UploadedFile::fake()->image('hero.png', 1600, 900),
    ])->assertRedirect();

    $collection = Collection::query()->where('name', 'Finisher Legacy — Cozumel 2026')->firstOrFail();

    expect($collection->slug)->toBe('finisher-legacy-cozumel-2026')
        ->and($collection->event_id)->toBe($event->id)
        ->and($collection->launch_at->toDateString())->toBe('2026-11-01')
        ->and($collection->cover_path)->toStartWith('collections/')
        ->and($collection->hero_path)->toStartWith('collections/');

    Storage::disk('public')->assertExists([$collection->cover_path, $collection->hero_path]);

    $this->actingAs($admin)->get("/admin/catalogo/colecciones/{$collection->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/catalog/collections/Show')->where('collection.slug', 'finisher-legacy-cozumel-2026'));
});

test('collection validation rejects non-image uploads, bad colors and duplicate slugs', function () {
    Storage::fake('public');
    $admin = tagStaff();
    Collection::factory()->create(['slug' => 'tomado']);

    $this->actingAs($admin)->post('/admin/catalogo/colecciones', [
        'name' => 'Otra',
        'slug' => 'tomado',
        'status' => 'draft',
        'accent_color' => 'gold',
        'cover' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
    ])->assertSessionHasErrors(['slug', 'accent_color', 'cover']);
});

test('admin updates a collection and the show page lists products with unit counts', function () {
    $admin = tagStaff();
    $variant = tagVariant();
    $collection = $variant->product->collection;
    tagStock($variant, tagLocation(), 3, $admin);

    $this->actingAs($admin)->post("/admin/catalogo/colecciones/{$collection->id}", [
        'name' => 'Cozumel Renovada',
        'slug' => 'cozumel-renovada',
        'status' => 'archived',
    ])->assertRedirect("/admin/catalogo/colecciones/{$collection->id}");

    expect($collection->fresh()->slug)->toBe('cozumel-renovada')
        ->and($collection->fresh()->status)->toBe(ProductStatus::Archived);

    $this->actingAs($admin)->get("/admin/catalogo/colecciones/{$collection->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->has('products', 1)
            ->where('products.0.variants.0.units', 3)
            ->where('products.0.variants.0.units_in_stock', 3)
            ->where('products.0.variants.0.stock', 3));
});

test('a collection with products is archived instead of deleted; an empty one is deleted', function () {
    $admin = tagStaff();
    $withProducts = Collection::factory()->create();
    Product::factory()->create(['collection_id' => $withProducts->id]);
    $empty = Collection::factory()->create();

    $this->actingAs($admin)->delete("/admin/catalogo/colecciones/{$withProducts->id}")->assertRedirect();
    expect($withProducts->fresh())->not->toBeNull()
        ->and($withProducts->fresh()->status)->toBe(ProductStatus::Archived);

    $this->actingAs($admin)->delete("/admin/catalogo/colecciones/{$empty->id}")->assertRedirect('/admin/catalogo/colecciones');
    expect(Collection::query()->find($empty->id))->toBeNull();
});
