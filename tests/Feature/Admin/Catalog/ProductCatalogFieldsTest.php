<?php

use App\Enums\ProductType;
use App\Models\Ally;
use App\Models\Collection;
use App\Models\Product;
use App\Models\ProductPriceSchedule;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

require_once __DIR__.'/../../LegacyTags/helpers.php';

test('product form persists the Finisher Legacy Tag fields', function () {
    $admin = tagStaff();
    $collection = Collection::factory()->create();
    $product = Product::factory()->create();

    $this->actingAs($admin)->patch("/admin/products/{$product->id}", [
        'name' => 'Tag Cozumel',
        'type' => 'legacy_tag',
        'status' => 'active',
        'collection_id' => $collection->id,
        'tracks_units' => true,
        'material' => 'Acero inoxidable',
        'strap_type' => 'Correa de silicón',
        'dimensions' => '70 × 45 mm',
        'instructions' => 'Acerca tu teléfono.',
        'seo_title' => 'Tag Cozumel 2026',
        'seo_description' => 'El recuerdo de tu carrera.',
    ])->assertSessionHasNoErrors()->assertRedirect();

    $product->refresh();

    expect($product->type)->toBe(ProductType::LegacyTag)
        ->and($product->collection_id)->toBe($collection->id)
        ->and($product->tracks_units)->toBeTrue()
        ->and($product->material)->toBe('Acero inoxidable')
        ->and($product->strap_type)->toBe('Correa de silicón')
        ->and($product->dimensions)->toBe('70 × 45 mm')
        ->and($product->instructions)->toBe('Acerca tu teléfono.')
        ->and($product->seo_title)->toBe('Tag Cozumel 2026')
        ->and($product->seo_description)->toBe('El recuerdo de tu carrera.');

    $this->actingAs($admin)->get("/admin/products/{$product->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/products/Show')
            ->where('product.collection_id', $collection->id)
            ->where('product.tracks_units', true)
            ->has('collections')
            ->has('channelPricing.channels', 4));
});

test('omitting the new fields keeps tracks_units untouched', function () {
    $admin = tagStaff();
    $product = Product::factory()->create(['tracks_units' => true]);

    $this->actingAs($admin)->patch("/admin/products/{$product->id}", [
        'name' => 'Sin campos nuevos', 'type' => 'apparel', 'status' => 'active',
    ])->assertSessionHasNoErrors();

    expect($product->fresh()->tracks_units)->toBeTrue();
});

test('channel prices are created per channel / ally, disabled (never deleted) and logged', function () {
    $admin = tagStaff();
    $variant = tagVariant();
    $ally = Ally::factory()->create();

    $this->actingAs($admin)->post("/admin/products/{$variant->product_id}/precios-canal", [
        'sales_channel' => 'ally',
        'ally_id' => $ally->id,
        'product_variant_id' => $variant->id,
        'amount_minor' => 34900,
    ])->assertSessionHasNoErrors();

    $schedule = ProductPriceSchedule::query()->where('product_id', $variant->product_id)->firstOrFail();

    expect($schedule->sales_channel)->toBe('ally')
        ->and($schedule->ally_id)->toBe($ally->id)
        ->and($schedule->amount_minor)->toBe(34900)
        ->and($schedule->active)->toBeTrue()
        ->and(Activity::query()->where('description', 'products.channel_price.created')->exists())->toBeTrue();

    $this->actingAs($admin)->patch("/admin/products/precios-canal/{$schedule->id}", ['active' => false])->assertSessionHasNoErrors();

    expect($schedule->fresh()->active)->toBeFalse()
        ->and(Activity::query()->where('description', 'products.channel_price.disabled')->exists())->toBeTrue();

    // An ally on a non-ally channel is refused; a SKU from another product too.
    $this->actingAs($admin)->post("/admin/products/{$variant->product_id}/precios-canal", [
        'sales_channel' => 'direct', 'ally_id' => $ally->id, 'amount_minor' => 30000,
    ])->assertSessionHasErrors('ally_id');

    $other = tagVariant();
    $this->actingAs($admin)->post("/admin/products/{$variant->product_id}/precios-canal", [
        'sales_channel' => 'direct', 'product_variant_id' => $other->id, 'amount_minor' => 30000,
    ])->assertSessionHasErrors('product_variant_id');
});

test('sales staff cannot manage channel prices', function () {
    $variant = tagVariant();

    $this->actingAs(tagStaff('sales_staff'))->post("/admin/products/{$variant->product_id}/precios-canal", [
        'sales_channel' => 'direct', 'amount_minor' => 30000,
    ])->assertForbidden();
});
