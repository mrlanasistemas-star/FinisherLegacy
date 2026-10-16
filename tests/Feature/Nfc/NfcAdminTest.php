<?php

require_once __DIR__.'/helpers.php';

use App\Enums\NfcTagStatus;
use App\Models\Athlete;
use App\Models\AthleteOwnedProduct;
use App\Models\NfcTag;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Activitylog\Models\Activity;

test('admin generates a batch and exports the encoder CSV of URLs', function () {
    $admin = nfcAdmin();

    $this->actingAs($admin)->post(route('admin.legacy-nfc.tags.generate'), ['count' => 5, 'batch_code' => 'Lote 1'])
        ->assertRedirect(route('admin.legacy-nfc.tags.index', ['batch' => 'Lote 1']));

    expect(NfcTag::query()->where('batch_code', 'Lote 1')->count())->toBe(5)
        ->and(Activity::query()->where('description', 'nfc.tags.generated')->count())->toBe(1);

    $csv = $this->actingAs($admin)->get(route('admin.legacy-nfc.tags.export', ['batch' => 'Lote 1']))->assertOk()->streamedContent();
    $tag = NfcTag::query()->first();

    expect($csv)->toContain(route('nfc.tap', $tag->token))
        ->and(substr_count(trim($csv), "\n"))->toBe(5);
});

test('the tags list never exposes the hardware uid and shows assignment + last tap', function () {
    $admin = nfcAdmin();
    [, $tag] = nfcPlateWithTag();
    $tag->update(['uid_hash' => str_repeat('f', 64)]);
    $this->get("/n/{$tag->token}");

    $response = $this->actingAs($admin)->get(route('admin.legacy-nfc.tags.index'))->assertOk();

    $response->assertInertia(fn ($page) => $page->component('admin/legacy-nfc/Tags')
        ->where('tags.data.0.assignment.type', 'legacy_plate')
        ->where('tags.data.0.tap_count', 1));
    expect(json_encode($response->viewData('page')['props']))->not->toContain(str_repeat('f', 64));
});

test('disable and re-enable are audited and restore the previous state', function () {
    $admin = nfcAdmin();
    [, $tag] = nfcPlateWithTag();

    $this->actingAs($admin)->post(route('admin.legacy-nfc.tags.disable', $tag))->assertRedirect();
    expect($tag->fresh()->status)->toBe(NfcTagStatus::Disabled);
    $this->getJson("/api/v1/nfc/{$tag->token}")->assertJsonPath('data.status', 'disabled');

    $this->actingAs($admin)->post(route('admin.legacy-nfc.tags.enable', $tag))->assertRedirect();
    expect($tag->fresh()->status)->toBe(NfcTagStatus::Assigned)
        ->and(Activity::query()->whereIn('description', ['nfc.disabled', 'nfc.enabled'])->count())->toBe(2);
});

test('connecting a product to an existing unit connects it to its owner immediately', function () {
    $admin = nfcAdmin();
    $tag = NfcTag::factory()->create();
    $owned = AthleteOwnedProduct::create([
        'uuid' => (string) Str::uuid(),
        'athlete_id' => Athlete::factory()->create()->id,
        'product_id' => Product::factory()->create()->id,
        'status' => 'active',
        'acquired_at' => now(),
    ]);

    $this->actingAs($admin)->post(route('admin.legacy-nfc.products.store'), [
        'tag' => $tag->token,
        'owned_product_uuid' => $owned->uuid,
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($owned->activeNfcAssignment()->first()?->isClaimed())->toBeTrue();

    $this->actingAs($admin)->get(route('admin.legacy-nfc.products.index'))->assertOk()
        ->assertInertia(fn ($page) => $page->where('items.data.0.connected', true));
});

test('the workspace is closed to people without NFC/production permissions', function () {
    $this->seed(RolePermissionSeeder::class);
    $athlete = User::factory()->create();

    $this->actingAs($athlete)->get(route('admin.legacy-nfc.index'))->assertForbidden();
    $this->actingAs($athlete)->get(route('admin.legacy-nfc.tags.index'))->assertForbidden();
    $this->actingAs($athlete)->post(route('admin.legacy-nfc.tags.generate'), ['count' => 1])->assertForbidden();
    $this->actingAs($athlete)->get(route('admin.legacy-nfc.products.index'))->assertForbidden();

    $operator = User::factory()->create();
    $operator->assignRole('production_operator');
    // Production operators can see tags (to assign them) but not generate.
    $this->actingAs($operator)->get(route('admin.legacy-nfc.tags.index'))->assertOk();
    $this->actingAs($operator)->post(route('admin.legacy-nfc.tags.generate'), ['count' => 1])->assertForbidden();
});
