<?php

use App\Enums\PlateGenerationMode;
use App\Enums\PlateStatus;
use App\Enums\PlateTemplateVersionStatus;
use App\Models\LegacyCode;
use App\Models\Plate;
use App\Models\PlateTemplate;
use App\Models\PlateTemplateVersion;
use App\Models\ProductionJob;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->productionUser = User::factory()->create();
    $this->productionUser->assignRole('production_operator');
});

function producibleJob(): ProductionJob
{
    $template = PlateTemplate::factory()->create(['width_mm' => 60, 'height_mm' => 40]);
    $version = PlateTemplateVersion::factory()->create([
        'plate_template_id' => $template->id,
        'status' => PlateTemplateVersionStatus::Published,
        'front_configuration' => ['elements' => []],
        'back_configuration' => ['elements' => []],
    ]);

    $plate = Plate::factory()->create([
        'generation_mode' => PlateGenerationMode::Quick,
        'status' => PlateStatus::Queued,
        'plate_template_id' => $template->id,
        'plate_template_version_id' => $version->id,
    ]);
    $legacyCode = LegacyCode::factory()->create(['plate_id' => $plate->id]);
    $plate->update(['legacy_code_id' => $legacyCode->id]);

    return ProductionJob::factory()->create(['plate_id' => $plate->id, 'status' => 'queued']);
}

function advanceToStatus(User $actor, ProductionJob $job, string $status): void
{
    match ($status) {
        'assigned', 'preparing' => test()->actingAs($actor)->patch(route('production.jobs.prepare', $job)),
        'engraving_front' => test()->actingAs($actor)->patch(route('production.jobs.front.start', $job)),
        'awaiting_flip' => test()->actingAs($actor)->patch(route('production.jobs.front.complete', $job)),
        'engraving_back' => (function () use ($actor, $job) {
            test()->actingAs($actor)->patch(route('production.jobs.flip.confirm', $job));
            test()->actingAs($actor)->patch(route('production.jobs.back.start', $job));
        })(),
        'verifying_qr' => test()->actingAs($actor)->patch(route('production.jobs.back.complete', $job)),
        'ready' => test()->actingAs($actor)->post(route('production.jobs.qr.verify', $job), [
            'decoded_value' => route('legacy-code.show', $job->plate->fresh()->legacyCode->code),
        ]),
        'delivered' => test()->actingAs($actor)->patch(route('production.jobs.deliver', $job)),
        default => null,
    };
}

test('production routes are blocked without the production.access permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('production.index'))->assertForbidden();
});

test('the old kanban URL redirects to Preparación y entrega, which maps the same jobs to stages', function () {
    producibleJob(); // queued -> pending

    $processing = producibleJob();
    advanceToStatus($this->productionUser, $processing, 'preparing');

    $ready = producibleJob();
    foreach (['preparing', 'engraving_front', 'awaiting_flip', 'engraving_back', 'verifying_qr', 'ready'] as $status) {
        advanceToStatus($this->productionUser, $ready, $status);
    }

    $delivered = producibleJob();
    foreach (['preparing', 'engraving_front', 'awaiting_flip', 'engraving_back', 'verifying_qr', 'ready', 'delivered'] as $status) {
        advanceToStatus($this->productionUser, $delivered, $status);
    }

    $this->actingAs($this->productionUser)->get(route('production.index'))
        ->assertRedirect(route('admin.legacy-nfc.preparation.index'));

    // Lanes: pending, preparing, personalized, nfc_assigned, ready, delivered.
    // A job the machine closed as "ready" without an NFC tag still needs it.
    $this->actingAs($this->productionUser)->get(route('admin.legacy-nfc.preparation.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/legacy-nfc/Preparation')
            ->where('lanes.0.count', 1)
            ->where('lanes.1.count', 1)
            ->where('lanes.2.count', 1)
            ->where('lanes.5.count', 1)
        );
});

test('a job advances through the full physical workflow to delivered', function () {
    $job = producibleJob();

    foreach (['preparing', 'engraving_front', 'awaiting_flip', 'engraving_back', 'verifying_qr', 'ready', 'delivered'] as $status) {
        advanceToStatus($this->productionUser, $job, $status);
    }

    $job->refresh();
    expect($job->status->value)->toBe('delivered')
        ->and($job->front_engraved_at)->not->toBeNull()
        ->and($job->back_engraved_at)->not->toBeNull()
        ->and($job->qr_verified_at)->not->toBeNull()
        ->and($job->plate->fresh()->status)->toBe(PlateStatus::Delivered);
});

test('a job cannot skip straight from queued to ready', function () {
    $job = producibleJob();

    $response = $this->actingAs($this->productionUser)->post(route('production.jobs.qr.verify', $job), [
        'decoded_value' => 'anything',
    ]);

    $response->assertRedirect();
    expect($job->fresh()->status->value)->toBe('queued');
});

test('back cannot start before the flip is confirmed', function () {
    $job = producibleJob();
    advanceToStatus($this->productionUser, $job, 'preparing');
    advanceToStatus($this->productionUser, $job, 'engraving_front');
    advanceToStatus($this->productionUser, $job, 'awaiting_flip');

    $this->actingAs($this->productionUser)->patch(route('production.jobs.back.start', $job))->assertRedirect();

    expect($job->fresh()->status->value)->toBe('awaiting_flip');
});

test('a wrong QR scan keeps the job in verifying_qr', function () {
    $job = producibleJob();
    foreach (['preparing', 'engraving_front', 'awaiting_flip', 'engraving_back', 'verifying_qr'] as $status) {
        advanceToStatus($this->productionUser, $job, $status);
    }

    $this->actingAs($this->productionUser)->post(route('production.jobs.qr.verify', $job), [
        'decoded_value' => route('legacy-code.show', 'FL-WRONGCODE'),
    ])->assertRedirect();

    expect($job->fresh()->status->value)->toBe('verifying_qr')
        ->and($job->fresh()->qr_verified_at)->toBeNull();
});

test('the board stays on a bounded query budget with ~30 jobs in production', function () {
    foreach (range(1, 30) as $i) {
        producibleJob();
    }

    DB::enableQueryLog();

    $response = $this->actingAs($this->productionUser)->get(route('admin.legacy-nfc.preparation.index'));

    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    $response->assertOk();
    // A handful of fixed queries (auth, permissions, the eager-loaded job
    // query and its relations) — must NOT scale with the number of jobs.
    expect($queryCount)->toBeLessThan(20);
});

test('production.view alone cannot mutate a job', function () {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(['production.access', 'production.view']);
    $job = producibleJob();

    $this->actingAs($viewer)
        ->patch(route('production.jobs.prepare', $job))
        ->assertForbidden();
});
