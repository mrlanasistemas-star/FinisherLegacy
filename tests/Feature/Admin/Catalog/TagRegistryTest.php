<?php

use App\Actions\LegacyTags\ActivateTag;
use App\Enums\InventoryLocationType;
use App\Enums\LegacyTagEventType;
use App\Enums\LegacyTagStatus;
use App\Enums\TagAccessSource;
use App\Models\Ally;
use App\Models\InventoryLevel;
use App\Models\LegacyTag;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/../../LegacyTags/helpers.php';

function activatedTag(?string $email = null): LegacyTag
{
    $variant = tagVariant();
    $tag = LegacyTag::factory()->create(['product_variant_id' => $variant->id, 'status' => LegacyTagStatus::Delivered, 'sold_at' => now()]);
    app(ActivateTag::class)->handle($tag, User::factory()->create($email ? ['email' => $email] : []), TagAccessSource::Nfc);

    return $tag->fresh();
}

test('tags index filters by status / location and finds a piece by pasted URL, serial or owner email', function () {
    $admin = tagStaff();
    $variant = tagVariant();
    $warehouse = tagLocation();
    $tags = tagStock($variant, $warehouse, 3, $admin);
    $activated = activatedTag('duena@example.com');

    $this->actingAs($admin)->get('/admin/tags?status=in_stock&location='.$warehouse->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/tags/Index')
            ->has('tags.data', 3)
            ->where('counts.in_stock', 3));

    $url = 'https://finisherlegacy.com/t/'.$tags[1]->public_code.'?src=qr';
    $this->actingAs($admin)->get('/admin/tags?q='.urlencode($url))
        ->assertInertia(fn (Assert $page) => $page->has('tags.data', 1)->where('tags.data.0.serial', $tags[1]->serial));

    $this->actingAs($admin)->get('/admin/tags?q='.urlencode(strtolower($tags[2]->displayCode())))
        ->assertInertia(fn (Assert $page) => $page->has('tags.data', 1)->where('tags.data.0.uuid', $tags[2]->uuid));

    $this->actingAs($admin)->get('/admin/tags?q='.$tags[0]->serial)
        ->assertInertia(fn (Assert $page) => $page->has('tags.data', 1)->where('tags.data.0.uuid', $tags[0]->uuid));

    $this->actingAs($admin)->get('/admin/tags?q=duena@example.com')
        ->assertInertia(fn (Assert $page) => $page->has('tags.data', 1)->where('tags.data.0.uuid', $activated->uuid));

    $this->actingAs($admin)->get('/admin/tags?activated=si')
        ->assertInertia(fn (Assert $page) => $page->has('tags.data', 1));

    $this->actingAs($admin)->get('/admin/tags?variant='.$variant->id.'&activated=no&date_field=received_at&from='.now()->toDateString())
        ->assertInertia(fn (Assert $page) => $page->has('tags.data', 3));
});

test('tag detail renders the traceability timeline, history and opens (no QR)', function () {
    $admin = tagStaff();
    $tag = activatedTag('duena@example.com');

    $this->actingAs($admin)->get("/admin/tags/{$tag->uuid}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/tags/Show')
            ->where('tag.serial', $tag->serial)
            ->where('tag.status', 'activated')
            ->where('tag.owner.email', 'duena@example.com')
            ->where('tag.nfc_url', fn ($u) => str_ends_with($u, '/t/'.$tag->public_code))
            ->has('tag.story.public_id')
            ->has('events', fn (Assert $events) => $events->where('0.type', 'activated')->etc())
            ->missing('tag.qr_url')
            // nfc, code, link — `qr` only shows up once an old ?src=qr link was used.
            ->has('scans', 3)
            ->where('can.manage', true));

    // The tag has no printed QR, so there is no QR image to render.
    $this->actingAs($admin)->get("/admin/tags/{$tag->uuid}/qr.svg")->assertNotFound();
});

test('unknown tag uuid is a 404', function () {
    $this->actingAs(tagStaff())->get('/admin/tags/'.Str::uuid())->assertNotFound();
    $this->actingAs(tagStaff())->post('/admin/tags/'.Str::uuid().'/desactivar', ['reason' => 'perdida'])->assertNotFound();
});

test('sales staff can view tags but every manage action is 403', function () {
    $staff = tagStaff('sales_staff');
    $tag = tagStock(tagVariant(), tagLocation(), 1)->first();

    $this->actingAs($staff)->get('/admin/tags')->assertOk();
    $this->actingAs($staff)->get("/admin/tags/{$tag->uuid}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('can.manage', false));

    foreach (['desactivar', 'reactivar', 'reemplazar', 'devolver', 'revocar', 'notas'] as $action) {
        $this->actingAs($staff)->post("/admin/tags/{$tag->uuid}/{$action}", ['reason' => 'prueba', 'notes' => 'x', 'replacement' => 'x'])->assertForbidden();
    }

    expect($tag->fresh()->status)->toBe(LegacyTagStatus::InStock);
});

test('athletes cannot open the tag registry', function () {
    $this->actingAs(tagStaff('athlete'))->get('/admin/tags')->assertForbidden();
});

test('disable requires a reason, takes the piece out of stock, enable puts it back', function () {
    $admin = tagStaff();
    $variant = tagVariant();
    $warehouse = tagLocation();
    $tag = tagStock($variant, $warehouse, 2, $admin)->first();
    $onHand = fn () => (int) InventoryLevel::query()->where('product_variant_id', $variant->id)->where('inventory_location_id', $warehouse->id)->value('quantity_on_hand');

    $this->actingAs($admin)->post("/admin/tags/{$tag->uuid}/desactivar", ['reason' => ''])->assertSessionHasErrors('reason');

    $this->actingAs($admin)->post("/admin/tags/{$tag->uuid}/desactivar", ['reason' => 'Pieza dañada'])->assertSessionHasNoErrors();
    expect($tag->fresh()->status)->toBe(LegacyTagStatus::Disabled)->and($onHand())->toBe(1);

    $this->actingAs($admin)->post("/admin/tags/{$tag->uuid}/reactivar", ['reason' => 'Era otra pieza'])->assertSessionHasNoErrors();
    expect($tag->fresh()->status)->toBe(LegacyTagStatus::InStock)
        ->and($onHand())->toBe(2)
        ->and($tag->events()->where('type', LegacyTagEventType::Note->value)->exists())->toBeTrue();
});

test('replace moves owner and story to an in-stock piece found by its pasted code', function () {
    $admin = tagStaff();
    $old = activatedTag();
    $new = tagStock($old->productVariant, tagLocation(), 1, $admin)->first();
    $storyId = $old->story->id;

    $this->actingAs($admin)->post("/admin/tags/{$old->uuid}/reemplazar", [
        'replacement' => 'https://finisherlegacy.com/t/'.$new->public_code,
        'reason' => 'NFC no responde',
    ])->assertSessionHasNoErrors();

    expect($old->fresh()->status)->toBe(LegacyTagStatus::Replaced)
        ->and($new->fresh()->status)->toBe(LegacyTagStatus::Activated)
        ->and($new->fresh()->owner_athlete_id)->toBe($old->owner_athlete_id)
        ->and($new->fresh()->story->id)->toBe($storyId);

    // A non-stock replacement is a validation error, not a 500.
    $other = activatedTag();
    $this->actingAs($admin)->post("/admin/tags/{$other->uuid}/reemplazar", ['replacement' => $old->serial, 'reason' => 'otra'])
        ->assertSessionHasErrors('replacement');
});

test('return with restock and revoke ownership', function () {
    $admin = tagStaff();
    $returning = activatedTag();
    $restock = tagLocation();

    $this->actingAs($admin)->post("/admin/tags/{$returning->uuid}/devolver", ['reason' => 'Reembolso', 'restock_location_id' => $restock->id])
        ->assertSessionHasNoErrors();
    expect($returning->fresh()->status)->toBe(LegacyTagStatus::InStock)
        ->and($returning->fresh()->inventory_location_id)->toBe($restock->id)
        ->and($returning->fresh()->owner_athlete_id)->toBeNull();

    $revoking = activatedTag();
    $this->actingAs($admin)->post("/admin/tags/{$revoking->uuid}/revocar", ['reason' => 'Reclamo equivocado'])->assertSessionHasNoErrors();
    expect($revoking->fresh()->status)->toBe(LegacyTagStatus::Delivered)->and($revoking->fresh()->owner_athlete_id)->toBeNull();

    // Revoking a non-activated piece is refused with a message.
    $this->actingAs($admin)->post("/admin/tags/{$revoking->uuid}/revocar", ['reason' => 'otra vez'])->assertSessionHasErrors('reason');
});

test('staff can add a note to the unit history', function () {
    $admin = tagStaff();
    $tag = tagStock(tagVariant(), tagLocation(), 1, $admin)->first();

    $this->actingAs($admin)->post("/admin/tags/{$tag->uuid}/notas", ['notes' => 'Revisada en mostrador'])->assertSessionHasNoErrors();

    expect($tag->events()->where('type', 'note')->value('notes'))->toBe('Revisada en mostrador');
});

test('transfer by quantity and by scanned codes', function () {
    $admin = tagStaff();
    $variant = tagVariant();
    $warehouse = tagLocation();
    $shop = tagLocation(InventoryLocationType::Ally, Ally::factory()->create());
    $tags = tagStock($variant, $warehouse, 6, $admin);
    $onHand = fn ($loc) => (int) InventoryLevel::query()->where('product_variant_id', $variant->id)->where('inventory_location_id', $loc->id)->value('quantity_on_hand');

    $this->actingAs($admin)->post('/admin/inventario/transferir', [
        'mode' => 'quantity', 'from_location_id' => $warehouse->id, 'to_location_id' => $shop->id,
        'product_variant_id' => $variant->id, 'quantity' => 3,
    ])->assertSessionHasNoErrors()->assertSessionHas('transfer_result.count', 3);

    expect($onHand($warehouse))->toBe(3)->and($onHand($shop))->toBe(3);

    $this->actingAs($admin)->post('/admin/inventario/transferir', [
        'mode' => 'quantity', 'from_location_id' => $warehouse->id, 'to_location_id' => $shop->id,
        'product_variant_id' => $variant->id, 'quantity' => 10,
    ])->assertSessionHasErrors('quantity');

    $remaining = LegacyTag::query()->where('inventory_location_id', $warehouse->id)->orderBy('id')->get();
    $this->actingAs($admin)->post('/admin/inventario/transferir', [
        'mode' => 'units', 'to_location_id' => $shop->id,
        'codes' => 'https://finisherlegacy.com/t/'.$remaining[0]->public_code."?src=qr\n".$remaining[1]->serial,
    ])->assertSessionHasNoErrors();

    expect($remaining[0]->fresh()->inventory_location_id)->toBe($shop->id)
        ->and($remaining[1]->fresh()->inventory_location_id)->toBe($shop->id)
        ->and($onHand($shop))->toBe(5);

    $this->actingAs($admin)->post('/admin/inventario/transferir', [
        'mode' => 'units', 'to_location_id' => $warehouse->id, 'codes' => 'ABCDEFGHJKMN',
    ])->assertSessionHasErrors('codes');

    $this->actingAs($admin)->get('/admin/inventario/transferir')
        ->assertInertia(fn (Assert $page) => $page->component('admin/inventory/Transfer')
            ->where('available.'.$warehouse->id.':'.$variant->id, 1));

    $this->actingAs(tagStaff('sales_staff'))->get('/admin/inventario/transferir')->assertForbidden();
});

test('stock summary counts units by SKU and location and flags low stock', function () {
    $admin = tagStaff();
    $variant = tagVariant();
    $variant->update(['minimum_stock' => 5]);
    $warehouse = tagLocation();
    tagStock($variant, $warehouse, 3, $admin);

    $this->actingAs($admin)->get('/admin/inventario/existencias?low=1')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/inventory/Stock')
            ->has('rows', 1)
            ->where('rows.0.sku', $variant->sku)
            ->where('rows.0.totals.in_stock', 3)
            ->where('rows.0.low', true)
            ->where('rows.0.locations.0.location', $warehouse->name));

    $this->actingAs(tagStaff('sales_staff'))->get('/admin/inventario/existencias')->assertForbidden();
});
