<?php

use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Store V2 product gallery (product UX consolidation brief §106-§110).
 */
beforeEach(function () {
    Storage::fake('product_media');
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $this->product = Product::factory()->create();
});

test('uploading the first image makes it primary automatically', function () {
    $this->actingAs($this->admin)->post("/admin/products/{$this->product->id}/media", [
        'file' => UploadedFile::fake()->image('front.jpg'),
    ])->assertRedirect();

    $media = $this->product->media()->firstOrFail();
    expect($media->is_primary)->toBeTrue()
        ->and($media->type->value)->toBe('image');
    Storage::disk('product_media')->assertExists($media->path);
});

test('uploading multiple files in one request creates one row per file and only the first is primary', function () {
    $this->actingAs($this->admin)->post("/admin/products/{$this->product->id}/media", [
        'files' => [
            UploadedFile::fake()->image('front.jpg'),
            UploadedFile::fake()->image('back.jpg'),
            UploadedFile::fake()->create('clip.mp4', 500, 'video/mp4'),
        ],
    ])->assertRedirect();

    $media = $this->product->media()->orderBy('sort_order')->get();

    expect($media)->toHaveCount(3)
        ->and($media[0]->is_primary)->toBeTrue()
        ->and($media[1]->is_primary)->toBeFalse()
        ->and($media[2]->is_primary)->toBeFalse()
        ->and($media[2]->type->value)->toBe('video');
});

test('a batch upload larger than the per-request limit is rejected', function () {
    $files = array_map(fn ($i) => UploadedFile::fake()->image("img{$i}.jpg"), range(1, 11));

    $this->actingAs($this->admin)->post("/admin/products/{$this->product->id}/media", [
        'files' => $files,
    ])->assertSessionHasErrors('files');

    expect($this->product->media()->count())->toBe(0);
});

test('a second upload is not primary until explicitly set', function () {
    $first = ProductMedia::factory()->for($this->product)->create(['is_primary' => true, 'sort_order' => 0]);

    $this->actingAs($this->admin)->post("/admin/products/{$this->product->id}/media", [
        'file' => UploadedFile::fake()->image('back.jpg'),
    ]);

    $second = $this->product->media()->where('id', '!=', $first->id)->firstOrFail();
    expect($second->is_primary)->toBeFalse();

    $this->actingAs($this->admin)->post("/admin/products/media/{$second->id}/primary")->assertRedirect();

    expect($first->fresh()->is_primary)->toBeFalse()
        ->and($second->fresh()->is_primary)->toBeTrue();
});

test('reordering updates each item\'s sort_order', function () {
    $a = ProductMedia::factory()->for($this->product)->create(['sort_order' => 0]);
    $b = ProductMedia::factory()->for($this->product)->create(['sort_order' => 1]);

    $this->actingAs($this->admin)->post("/admin/products/{$this->product->id}/media/reorder", [
        'order' => [$b->id, $a->id],
    ])->assertRedirect();

    expect($b->fresh()->sort_order)->toBe(0)
        ->and($a->fresh()->sort_order)->toBe(1);
});

test('deleting the primary item promotes the next one, and removes the file', function () {
    $primary = ProductMedia::factory()->for($this->product)->create(['is_primary' => true, 'sort_order' => 0]);
    $other = ProductMedia::factory()->for($this->product)->create(['is_primary' => false, 'sort_order' => 1]);
    Storage::disk('product_media')->put($primary->path, 'fake-content');

    $this->actingAs($this->admin)->delete("/admin/products/media/{$primary->id}")->assertRedirect();

    Storage::disk('product_media')->assertMissing($primary->path);
    expect(ProductMedia::query()->find($primary->id))->toBeNull()
        ->and($other->fresh()->is_primary)->toBeTrue();
});

test('the public product page falls back to image_path when no gallery is configured', function () {
    $product = Product::factory()->create(['active' => true, 'image_path' => 'products/fallback.jpg']);

    $response = $this->get("/tienda/{$product->slug}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('product.gallery', [])
        ->where('product.image_url', fn ($url) => str_contains((string) $url, 'fallback.jpg')));
});

test('the public product page includes the configured gallery and content sections', function () {
    $product = Product::factory()->create(['active' => true]);
    ProductMedia::factory()->for($product)->create(['is_primary' => true, 'sort_order' => 0]);
    $product->contentSections()->create([
        'type' => 'features',
        'title' => 'Características',
        'content' => ['items' => ['Ligero', 'Transpirable']],
    ]);

    $response = $this->get("/tienda/{$product->slug}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('product.gallery', 1)
        ->has('product.contentSections', 1)
        ->where('product.contentSections.0.content.items', ['Ligero', 'Transpirable']));
});
