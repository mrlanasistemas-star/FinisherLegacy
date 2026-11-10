<?php

use App\Enums\LegacyCodeStatus;
use App\Enums\PlateGenerationMode;
use App\Enums\PlateStatus;
use App\Models\LegacyCode;
use App\Models\Plate;
use App\Models\PlateTemplateVersion;
use App\Models\User;
use App\Services\PlateExportService;
use App\Services\PlateTemplateRenderService;
use App\Support\CodeGenerator;
use App\Support\PlateRenderData;
use Database\Seeders\PlateTemplateSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(PlateTemplateSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

function plateForPreset(string $slug): Plate
{
    $version = PlateTemplateVersion::query()
        ->whereHas('plateTemplate', fn ($q) => $q->where('slug', $slug))
        ->firstOrFail();

    $plate = Plate::factory()->create([
        'plate_template_id' => $version->plate_template_id,
        'plate_template_version_id' => $version->id,
        'generation_mode' => PlateGenerationMode::Quick,
        'status' => PlateStatus::Queued,
    ]);

    $legacyCode = LegacyCode::create([
        'code' => CodeGenerator::unique('FL', fn ($c) => LegacyCode::where('code', $c)->exists()),
        'uuid' => Str::uuid(),
        'plate_id' => $plate->id,
        'status' => LegacyCodeStatus::Assigned,
        'assigned_at' => now(),
    ]);
    $plate->update(['legacy_code_id' => $legacyCode->id]);

    return $plate->fresh(['legacyCode', 'plateTemplateVersion.plateTemplate']);
}

test('the running preset front has no QR and the back does', function () {
    $version = PlateTemplateVersion::query()->whereHas('plateTemplate', fn ($q) => $q->where('slug', 'running-classic-60x40'))->firstOrFail();

    $frontTypes = collect($version->front_configuration['elements'])->pluck('type');
    $backTypes = collect($version->back_configuration['elements'])->pluck('type');

    expect($frontTypes)->not->toContain('qr')
        ->and($backTypes)->toContain('qr');
});

test('the triathlon preset front has no QR and the back does', function () {
    $version = PlateTemplateVersion::query()->whereHas('plateTemplate', fn ($q) => $q->where('slug', 'triathlon-premium-60x40'))->firstOrFail();

    $frontTypes = collect($version->front_configuration['elements'])->pluck('type');
    $backTypes = collect($version->back_configuration['elements'])->pluck('type');

    expect($frontTypes)->not->toContain('qr')
        ->and($backTypes)->toContain('qr');
});

test('the cycling preset front has no QR and the back does', function () {
    $version = PlateTemplateVersion::query()->whereHas('plateTemplate', fn ($q) => $q->where('slug', 'cycling-classic-60x40'))->firstOrFail();

    $frontTypes = collect($version->front_configuration['elements'])->pluck('type');
    $backTypes = collect($version->back_configuration['elements'])->pluck('type');

    expect($frontTypes)->not->toContain('qr')
        ->and($backTypes)->toContain('qr');
});

test('the batch export zip uses sanitized bib_name_face filenames with no email', function () {
    $plate = plateForPreset('running-classic-60x40');
    $plate->update(['athlete_name' => 'María Cármen Alániz', 'bib_number' => '1242']);

    $svc = app(PlateExportService::class);
    $content = $svc->exportBatch(Plate::whereKey($plate->id)->get());

    $tmp = tempnam(sys_get_temp_dir(), 'zip-batch-test-');
    file_put_contents($tmp, $content);
    $zip = new ZipArchive;
    $zip->open($tmp);

    expect($zip->locateName('1242_MARIA-CARMEN-ALANIZ_FRONT.svg'))->not->toBeFalse()
        ->and($zip->locateName('1242_MARIA-CARMEN-ALANIZ_BACK.svg'))->not->toBeFalse();

    for ($i = 0; $i < $zip->numFiles; $i++) {
        expect($zip->getNameIndex($i))->not->toContain('@');
    }

    $zip->close();
    unlink($tmp);
});

test('the back QR resolves to the same permanent legacy-code URL as the plate\'s code', function () {
    $plate = plateForPreset('cycling-classic-60x40');
    $renderer = app(PlateTemplateRenderService::class);

    $resolved = $renderer->resolveElements($plate->plateTemplateVersion, 'back', PlateRenderData::fromPlate($plate));
    $qrElement = collect($resolved['elements'])->firstWhere('type', 'qr');

    expect($qrElement['qr_content'])->toBe(url('/l/'.$plate->legacyCode->code));
});
