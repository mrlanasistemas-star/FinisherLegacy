<?php

require_once __DIR__.'/helpers.php';

use App\Enums\LegacyPlateEntitlementStatus;
use App\Enums\PlateStatus;
use App\Enums\PreparationStage;
use App\Enums\ProductionJobStatus;
use App\Models\EventEdition;
use App\Models\LegacyPlateEntitlement;
use App\Models\LegacyPlateModel;
use App\Models\NfcTag;
use App\Models\ProductionJob;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

test('the stage mapping covers every production status without touching the state machine', function (string $status, bool $hasNfc, PreparationStage $expected) {
    $job = new ProductionJob(['status' => $status]);

    expect(PreparationStage::fromJob($job, $hasNfc))->toBe($expected);
})->with([
    ['queued', false, PreparationStage::Pending],
    ['assigned', false, PreparationStage::Preparing],
    ['preparing', false, PreparationStage::Preparing],
    ['engraving_front', false, PreparationStage::Preparing],
    ['awaiting_flip', false, PreparationStage::Personalized],
    ['engraving_back', false, PreparationStage::Personalized],
    ['verifying_qr', false, PreparationStage::Personalized],
    ['verifying_qr', true, PreparationStage::NfcAssigned],
    ['ready', false, PreparationStage::Personalized],
    ['ready', true, PreparationStage::Ready],
    ['delivered', true, PreparationStage::Delivered],
    ['failed', false, PreparationStage::Attention],
    ['cancelled', true, PreparationStage::Attention],
]);

test('a V3 plate goes Pendiente → Preparando → Personalizada → NFC asignado → Lista → Entregada', function () {
    $admin = nfcAdmin();
    $plate = nfcPlate();
    $entitlement = LegacyPlateEntitlement::create([
        'uuid' => (string) Str::uuid(),
        'event_edition_id' => EventEdition::factory()->create()->id,
        'legacy_plate_model_id' => LegacyPlateModel::factory()->create()->id,
        'plate_id' => $plate->id,
        'status' => LegacyPlateEntitlementStatus::Queued,
        'price_type' => 'regular',
    ]);
    $job = $plate->latestProductionJob;
    $tag = NfcTag::factory()->create();

    $stage = fn () => PreparationStage::fromJob($job->fresh(), $plate->activeNfcAssignment()->exists());

    $this->actingAs($admin)->post(route('admin.legacy-nfc.preparation.start', $job))->assertRedirect();
    expect($stage())->toBe(PreparationStage::Preparing);

    $this->actingAs($admin)->post(route('admin.legacy-nfc.preparation.personalize', $job))->assertRedirect();
    expect($stage())->toBe(PreparationStage::Personalized)
        ->and($job->fresh()->front_engraved_at)->not->toBeNull()
        // Front-only plate: no invented back engraving.
        ->and($job->fresh()->back_engraved_at)->toBeNull();

    // "Lista" is impossible before the NFC tag is attached.
    $this->actingAs($admin)->post(route('admin.legacy-nfc.preparation.ready', $job))->assertRedirect();
    expect($job->fresh()->status)->toBe(ProductionJobStatus::VerifyingQr);

    $this->actingAs($admin)->post(route('admin.legacy-nfc.preparation.assign-nfc', $plate), [
        'tag' => route('nfc.tap', $tag->token),
    ])->assertRedirect()->assertSessionHasNoErrors();
    expect($stage())->toBe(PreparationStage::NfcAssigned);

    $this->actingAs($admin)->post(route('admin.legacy-nfc.preparation.ready', $job))->assertRedirect();
    expect($stage())->toBe(PreparationStage::Ready)
        ->and($plate->fresh()->status)->toBe(PlateStatus::Ready)
        ->and($entitlement->fresh()->status)->toBe(LegacyPlateEntitlementStatus::Produced);

    $this->actingAs($admin)->post(route('admin.legacy-nfc.preparation.deliver', $job))->assertRedirect();
    expect($stage())->toBe(PreparationStage::Delivered)
        ->and($plate->fresh()->status)->toBe(PlateStatus::Delivered)
        ->and($entitlement->fresh()->status)->toBe(LegacyPlateEntitlementStatus::Delivered);
});

test('a tag already in use cannot be assigned to another plate', function () {
    $admin = nfcAdmin();
    [, $tag] = nfcPlateWithTag();
    $other = nfcPlate();

    $this->actingAs($admin)->post(route('admin.legacy-nfc.preparation.assign-nfc', $other), ['nfc_tag_id' => $tag->id])
        ->assertRedirect();

    expect($other->activeNfcAssignment()->exists())->toBeFalse();
});

test('the board groups plates by stage on a bounded query budget and hides laser vocabulary', function () {
    $admin = nfcAdmin();
    foreach (range(1, 25) as $i) {
        nfcPlate();
    }

    DB::enableQueryLog();
    $response = $this->actingAs($admin)->get(route('admin.legacy-nfc.preparation.index'));
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    $response->assertOk()->assertInertia(fn ($page) => $page->component('admin/legacy-nfc/Preparation')
        ->where('lanes.0.key', 'pending')
        ->where('lanes.0.title', 'Pendiente')
        ->where('lanes.0.count', 25)
        ->has('lanes', 6));

    expect($queries)->toBeLessThan(25);

    // Keys, stage keys and action names: no machine/laser vocabulary.
    $card = $response->viewData('page')['props']['lanes'][0]['items'][0];
    $vocabulary = mb_strtolower(implode(' ', [...array_keys($card), $card['stage'], (string) $card['next_action']]));
    expect($vocabulary)->not->toMatch('/\b(laser|láser|engrav\w*|flip|qr|frequency|power|usb|driver)\b/u');
});

test('engraving data download is the machine contract (values + layout, no hardware parameters)', function () {
    $admin = nfcAdmin();
    $plate = nfcPlate(['engraving_display_name' => 'ANA']);

    $this->actingAs($admin)->get(route('admin.legacy-nfc.preparation.data', $plate))
        ->assertOk()
        ->assertJsonPath('serial', $plate->serial_number)
        ->assertJsonPath('values.athlete_name', 'ANA');

    $this->actingAs($admin)->get(route('admin.legacy-nfc.preparation.export'))->assertOk();
});

test('preparation is gated by production permissions and the old URLs redirect', function () {
    $admin = nfcAdmin();
    $athlete = User::factory()->create();
    $job = nfcPlate()->latestProductionJob;

    $this->actingAs($athlete)->get(route('admin.legacy-nfc.preparation.index'))->assertForbidden();
    $this->actingAs($athlete)->post(route('admin.legacy-nfc.preparation.start', $job))->assertForbidden();

    $viewer = User::factory()->create();
    $viewer->givePermissionTo(['production.access']);
    $this->actingAs($viewer)->get(route('admin.legacy-nfc.preparation.index'))->assertOk();
    $this->actingAs($viewer)->post(route('admin.legacy-nfc.preparation.start', $job))->assertForbidden();

    $operator = User::factory()->create();
    $operator->assignRole('production_operator');
    $this->actingAs($operator)->get(route('admin.legacy-nfc.index'))->assertRedirect(route('admin.legacy-nfc.preparation.index'));
    $this->actingAs($operator)->get('/production')->assertRedirect(route('admin.legacy-nfc.preparation.index'));

    $this->actingAs($admin)->get('/admin/legacy-plates/production')->assertRedirect(route('admin.legacy-nfc.preparation.index'));
});
