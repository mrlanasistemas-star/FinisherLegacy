<?php

use App\Enums\InventoryLocationType;
use App\Models\Ally;
use App\Models\SupportCase;
use App\Models\User;

require_once __DIR__.'/helpers.php';

test('an ally user sees only their own ally stock, sales and incidents', function () {
    tagStaff();
    $variant = tagVariant();
    $allyA = Ally::factory()->create(['name' => 'Aliado A']);
    $allyB = Ally::factory()->create(['name' => 'Aliado B']);
    $locationA = tagLocation(InventoryLocationType::Ally, $allyA);
    $locationB = tagLocation(InventoryLocationType::Ally, $allyB);
    $tagsA = tagStock($variant, $locationA, 3);
    $tagsB = tagStock($variant, $locationB, 5);
    inPersonSale($locationB, [$tagsB[0]->public_code]);
    inPersonSale($locationA, [$tagsA[0]->public_code]);

    $user = allyUser($allyA);

    $this->actingAs($user)->get('/aliado')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('ally/Dashboard')
            ->has('allies', 1)
            ->where('allies.0.name', 'Aliado A')
            ->has('locations', 1)
            ->where('locations.0.units', 2)
            ->has('sales', 1)
            ->where('month.orders', 1));

    // No admin panel for the portal-only role.
    $this->actingAs($user)->get('/admin')->assertForbidden();
    $this->actingAs($user)->get('/admin/aliados')->assertForbidden();
});

test('ally users report incidents only for their ally and their pieces', function () {
    tagStaff();
    $variant = tagVariant();
    $allyA = Ally::factory()->create();
    $allyB = Ally::factory()->create();
    $tagsA = tagStock($variant, tagLocation(InventoryLocationType::Ally, $allyA), 1);
    $tagsB = tagStock($variant, tagLocation(InventoryLocationType::Ally, $allyB), 1);
    $user = allyUser($allyA);

    $this->actingAs($user)->post('/aliado/incidencias', [
        'ally_id' => $allyB->id, 'type' => 'defect', 'subject' => 'Intento ajeno',
    ])->assertForbidden();

    $this->actingAs($user)->post('/aliado/incidencias', [
        'ally_id' => $allyA->id, 'type' => 'defect', 'subject' => 'Pieza ajena', 'tag_code' => $tagsB[0]->public_code,
    ])->assertSessionHasErrors('tag_code');

    $this->actingAs($user)->post('/aliado/incidencias', [
        'ally_id' => $allyA->id, 'type' => 'nfc_failure', 'subject' => 'No abre con NFC', 'tag_code' => $tagsA[0]->public_code,
    ])->assertRedirect();

    $case = SupportCase::query()->sole();
    expect($case->ally_id)->toBe($allyA->id)
        ->and($case->legacy_tag_id)->toBe($tagsA[0]->id)
        ->and($case->reporter_user_id)->toBe($user->id)
        ->and($case->number)->toStartWith('SC-'.now()->format('ym').'-');
});

test('accounts without ally.portal cannot open the portal', function () {
    tagStaff();
    $athlete = User::factory()->create();
    $athlete->assignRole('athlete');

    $this->actingAs($athlete)->get('/aliado')->assertForbidden();
    $this->actingAs($athlete)->get('/aliado/venta-rapida')->assertForbidden();
});
