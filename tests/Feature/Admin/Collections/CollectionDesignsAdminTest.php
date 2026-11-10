<?php

/**
 * Catálogo → Colecciones → Diseños extra del tag (CollectionDesignController)
 * y el selector de diseño en la ficha de producto tag.
 */

use App\Models\Collection;
use App\Models\CollectionDesign;
use App\Models\Product;
use App\Support\Collections\TagDesign;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

require_once __DIR__.'/../../LegacyTags/helpers.php';

function designFor(Collection $collection, array $attributes = []): CollectionDesign
{
    static $n = 0;
    $n++;

    return $collection->allDesigns()->create([
        'slug' => "diseno-{$n}",
        'name' => "Diseño {$n}",
        'sort_order' => $n,
        ...$attributes,
    ]);
}

test('staff without products.manage cannot manage designs', function () {
    $collection = Collection::factory()->create();
    $design = designFor($collection);

    $this->actingAs(tagStaff('sales_staff'))
        ->post("/admin/catalogo/colecciones/{$collection->id}/disenos", ['name' => 'X'])
        ->assertForbidden();
    $this->actingAs(tagStaff('athlete'))
        ->delete("/admin/catalogo/colecciones/{$collection->id}/disenos/{$design->id}")
        ->assertForbidden();
});

test('edit page lists every design, archived included, with product counts', function () {
    $admin = tagStaff();
    $collection = Collection::factory()->create();
    $reef = designFor($collection, ['name' => 'Arrecife', 'front_art_path' => 'collections/designs/a.webp']);
    designFor($collection, ['name' => 'Mapache', 'archived_at' => now()]);
    Product::factory()->create(['collection_id' => $collection->id, 'collection_design_id' => $reef->id]);

    $this->actingAs($admin)->get("/admin/catalogo/colecciones/{$collection->id}/editar")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/catalog/collections/Form')
            ->has('designs', 2)
            ->where('designs.0.name', 'Arrecife')
            ->where('designs.0.products_count', 1)
            ->where('designs.0.archived', false)
            ->where('designs.0.front_art_url', TagDesign::absoluteUrl('collections/designs/a.webp'))
            ->where('designs.1.archived', true));
});

test('admin creates a design with front art, auto slug and audit log', function () {
    Storage::fake('public');
    $admin = tagStaff();
    $collection = Collection::factory()->create();
    designFor($collection);

    $this->actingAs($admin)->post("/admin/catalogo/colecciones/{$collection->id}/disenos", [
        'name' => 'Arrecife de coral',
        'accent' => '#1E88E5',
        'art' => UploadedFile::fake()->image('reef.png', 600, 1200),
    ])->assertSessionHasNoErrors()->assertRedirect();

    $design = $collection->allDesigns()->where('name', 'Arrecife de coral')->firstOrFail();

    expect($design->slug)->toBe('arrecife-de-coral')
        ->and($design->accent)->toBe('#1E88E5')
        ->and($design->front_art_path)->toStartWith('collections/designs/')
        ->and($design->sort_order)->toBeGreaterThan($collection->allDesigns()->where('id', '!=', $design->id)->max('sort_order'));
    Storage::disk('public')->assertExists($design->front_art_path);
    expect(Activity::query()->where('description', 'collection_designs.created')->where('subject_id', $design->id)->exists())->toBeTrue();
});

test('design validation rejects bad accents, non-images, the reserved slug and duplicates', function () {
    Storage::fake('public');
    $admin = tagStaff();
    $collection = Collection::factory()->create();
    designFor($collection, ['slug' => 'tomado']);

    $this->actingAs($admin)->post("/admin/catalogo/colecciones/{$collection->id}/disenos", [
        'name' => '',
        'slug' => 'principal',
        'accent' => 'azul',
        'art' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'),
    ])->assertSessionHasErrors(['name', 'slug', 'accent', 'art']);

    $this->actingAs($admin)->post("/admin/catalogo/colecciones/{$collection->id}/disenos", [
        'name' => 'Otro', 'slug' => 'tomado',
    ])->assertSessionHasErrors('slug');

    // The same slug is fine in another collection.
    $other = Collection::factory()->create();
    $this->actingAs($admin)->post("/admin/catalogo/colecciones/{$other->id}/disenos", [
        'name' => 'Otro', 'slug' => 'tomado',
    ])->assertSessionHasNoErrors();
});

test('admin edits a design, replaces and removes its art', function () {
    Storage::fake('public');
    $admin = tagStaff();
    $collection = Collection::factory()->create();
    Storage::disk('public')->put('collections/designs/old.webp', 'x');
    $design = designFor($collection, ['front_art_path' => 'collections/designs/old.webp']);

    $this->actingAs($admin)->post("/admin/catalogo/colecciones/{$collection->id}/disenos/{$design->id}", [
        'name' => 'Mapache',
        'slug' => 'mapache',
        'accent' => '#AA5500',
        'art' => UploadedFile::fake()->image('new.webp', 600, 1200),
    ])->assertSessionHasNoErrors();

    $design->refresh();
    expect($design->name)->toBe('Mapache')->and($design->slug)->toBe('mapache')->and($design->accent)->toBe('#AA5500');
    Storage::disk('public')->assertMissing('collections/designs/old.webp');
    Storage::disk('public')->assertExists($design->front_art_path);

    $path = $design->front_art_path;
    $this->actingAs($admin)->post("/admin/catalogo/colecciones/{$collection->id}/disenos/{$design->id}", [
        'name' => 'Mapache', 'remove_art' => true,
    ])->assertSessionHasNoErrors();

    expect($design->fresh()->front_art_path)->toBeNull()->and($design->fresh()->accent)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

test('a design of another collection is not reachable through this one', function () {
    $admin = tagStaff();
    $collection = Collection::factory()->create();
    $foreign = designFor(Collection::factory()->create());

    $this->actingAs($admin)->post("/admin/catalogo/colecciones/{$collection->id}/disenos/{$foreign->id}", ['name' => 'X'])->assertNotFound();
    $this->actingAs($admin)->delete("/admin/catalogo/colecciones/{$collection->id}/disenos/{$foreign->id}")->assertNotFound();
});

test('admin reorders designs; an incomplete or foreign list is rejected', function () {
    $admin = tagStaff();
    $collection = Collection::factory()->create();
    [$a, $b, $c] = [designFor($collection), designFor($collection), designFor($collection)];
    $foreign = designFor(Collection::factory()->create());

    $this->actingAs($admin)->post("/admin/catalogo/colecciones/{$collection->id}/disenos/orden", ['ids' => [$c->id, $a->id, $b->id]])
        ->assertSessionHasNoErrors();

    expect($collection->allDesigns()->pluck('id')->all())->toBe([$c->id, $a->id, $b->id]);

    $this->actingAs($admin)->post("/admin/catalogo/colecciones/{$collection->id}/disenos/orden", ['ids' => [$a->id, $b->id]])
        ->assertSessionHasErrors('ids');
    $this->actingAs($admin)->post("/admin/catalogo/colecciones/{$collection->id}/disenos/orden", ['ids' => [$a->id, $b->id, $foreign->id]])
        ->assertSessionHasErrors('ids');
});

test('archived designs leave the store but keep product references; restore brings them back', function () {
    $admin = tagStaff();
    $collection = Collection::factory()->create();
    $design = designFor($collection);
    $product = Product::factory()->create(['collection_id' => $collection->id, 'collection_design_id' => $design->id]);

    $this->actingAs($admin)->post("/admin/catalogo/colecciones/{$collection->id}/disenos/{$design->id}/archivar", ['archived' => true])
        ->assertSessionHasNoErrors();

    expect($design->fresh()->archived_at)->not->toBeNull()
        ->and($collection->designs()->count())->toBe(0)
        ->and($product->fresh()->collection_design_id)->toBe($design->id);

    $this->actingAs($admin)->post("/admin/catalogo/colecciones/{$collection->id}/disenos/{$design->id}/archivar", ['archived' => false]);

    expect($design->fresh()->archived_at)->toBeNull()->and($collection->designs()->count())->toBe(1);
});

test('a design is deleted only when no product references it', function () {
    Storage::fake('public');
    $admin = tagStaff();
    $collection = Collection::factory()->create();
    $used = designFor($collection);
    Product::factory()->create(['collection_id' => $collection->id, 'collection_design_id' => $used->id]);
    Storage::disk('public')->put('collections/designs/free.webp', 'x');
    $free = designFor($collection, ['front_art_path' => 'collections/designs/free.webp']);

    $this->actingAs($admin)->delete("/admin/catalogo/colecciones/{$collection->id}/disenos/{$used->id}")
        ->assertSessionHasErrors('design');
    expect(CollectionDesign::query()->whereKey($used->id)->exists())->toBeTrue();

    $this->actingAs($admin)->delete("/admin/catalogo/colecciones/{$collection->id}/disenos/{$free->id}")
        ->assertSessionHasNoErrors();
    expect(CollectionDesign::query()->whereKey($free->id)->exists())->toBeFalse();
    Storage::disk('public')->assertMissing('collections/designs/free.webp');
    expect(Activity::query()->where('description', 'collection_designs.deleted')->exists())->toBeTrue();
});

test('product form offers its collection designs and only accepts a design of that collection', function () {
    $admin = tagStaff();
    $collection = Collection::factory()->create();
    $design = designFor($collection, ['name' => 'Arrecife']);
    designFor($collection, ['name' => 'Viejo', 'archived_at' => now()]);
    $foreign = designFor(Collection::factory()->create());
    $product = Product::factory()->create(['collection_id' => $collection->id]);

    $this->actingAs($admin)->get("/admin/products/{$product->id}")
        ->assertInertia(fn (Assert $page) => $page->component('admin/products/Show')
            ->where('product.collection_design_id', null)
            ->has('collectionDesigns', 2) // archived ones hidden unless selected
            ->where('collectionDesigns.0.name', 'Arrecife'));

    $payload = ['name' => 'Tag Cozumel', 'type' => 'legacy_tag', 'status' => 'active', 'collection_id' => $collection->id];

    $this->actingAs($admin)->patch("/admin/products/{$product->id}", [...$payload, 'collection_design_id' => $foreign->id])
        ->assertSessionHasErrors('collection_design_id');

    $this->actingAs($admin)->patch("/admin/products/{$product->id}", [...$payload, 'collection_design_id' => $design->id])
        ->assertSessionHasNoErrors();
    expect($product->fresh()->collection_design_id)->toBe($design->id)
        ->and(TagDesign::forProduct($product->fresh()))->not->toBeNull();

    // Moving the product to another collection without a design drops it.
    $other = Collection::factory()->create();
    $this->actingAs($admin)->patch("/admin/products/{$product->id}", [...$payload, 'collection_id' => $other->id])
        ->assertSessionHasNoErrors();
    expect($product->fresh()->collection_design_id)->toBeNull();
});
