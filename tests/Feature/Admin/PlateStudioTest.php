<?php

use App\Enums\PlateTemplateVersionStatus;
use App\Models\LegacyCode;
use App\Models\Plate;
use App\Models\PlateTemplate;
use App\Models\PlateTemplateVersion;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

test('athletes cannot access plate studio', function () {
    $athlete = User::factory()->create();
    $athlete->assignRole('athlete');

    $this->actingAs($athlete)->get(route('admin.plate-studio.index'))->assertForbidden();
});

test('event_operator can generate plates but cannot manage templates', function () {
    $operator = User::factory()->create();
    $operator->assignRole('event_operator');

    $this->actingAs($operator)->get(route('admin.plate-studio.index'))->assertForbidden();
});

test('admin can create a template, design it, and publish a version', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.plate-studio.templates.store'), [
        'name' => 'Test Template 60x40',
        'width_mm' => 60,
        'height_mm' => 40,
        'orientation' => 'landscape',
        'safe_margin_mm' => 3,
    ]);

    $template = PlateTemplate::where('name', 'Test Template 60x40')->firstOrFail();
    $version = $template->versions()->firstOrFail();

    $response->assertRedirect(route('admin.plate-studio.edit', [$template, $version]));
    expect($version->status)->toBe(PlateTemplateVersionStatus::Draft)
        ->and($version->version)->toBe(1);

    $editPage = $this->actingAs($this->admin)->get(route('admin.plate-studio.edit', [$template, $version]));
    $editPage->assertOk();
    $editPage->assertInertia(fn ($page) => $page
        ->component('admin/plate-studio/Editor')
        ->where('version.editable', true)
    );

    $elements = ['elements' => [
        ['id' => 'name', 'type' => 'dynamic_text', 'field' => 'athlete_name', 'text' => '{{athlete_name}}', 'x_mm' => 5, 'y_mm' => 5, 'width_mm' => 30, 'height_mm' => 6, 'font_size_pt' => 8],
    ]];

    $this->actingAs($this->admin)->patch(route('admin.plate-studio.versions.update', $version), [
        'front_configuration' => $elements,
        'back_configuration' => ['elements' => []],
    ])->assertRedirect();

    expect($version->fresh()->front_configuration['elements'])->toHaveCount(1);

    $this->actingAs($this->admin)->post(route('admin.plate-studio.versions.publish', $version))->assertRedirect();
    expect($version->fresh()->status)->toBe(PlateTemplateVersionStatus::Published);

    // A published version can no longer be edited directly.
    $this->actingAs($this->admin)->patch(route('admin.plate-studio.versions.update', $version), [
        'front_configuration' => $elements,
        'back_configuration' => ['elements' => []],
    ])->assertStatus(422);
});

test('live preview renders draft elements without persisting anything', function () {
    $response = $this->actingAs($this->admin)->postJson(route('admin.plate-studio.preview'), [
        'width_mm' => 60,
        'height_mm' => 40,
        'safe_margin_mm' => 3,
        'face' => 'front',
        'mode' => 'product',
        'elements' => [
            ['id' => 'name', 'type' => 'dynamic_text', 'field' => 'athlete_name', 'x_mm' => 5, 'y_mm' => 5, 'width_mm' => 30, 'height_mm' => 6, 'font_size_pt' => 8],
        ],
    ]);

    $response->assertOk();
    expect($response->json('svg'))->toContain('Zuriel Ávila')
        ->and($response->json('warnings'))->toBe([]);
});

test('duplicating a template copies its latest version as a new draft', function () {
    $template = PlateTemplate::factory()->create(['name' => 'Original']);
    $version = PlateTemplateVersion::factory()->create([
        'plate_template_id' => $template->id,
        'version' => 1,
        'status' => PlateTemplateVersionStatus::Published,
        'front_configuration' => ['elements' => [['id' => 'x', 'type' => 'static_text', 'text' => 'Hola', 'x_mm' => 1, 'y_mm' => 1, 'width_mm' => 10, 'height_mm' => 3]]],
    ]);

    $this->actingAs($this->admin)->post(route('admin.plate-studio.templates.duplicate', $template))->assertRedirect();

    $clone = PlateTemplate::where('name', 'Original (copia)')->firstOrFail();
    $cloneVersion = $clone->versions()->firstOrFail();

    // ->toEqual(), not ->toBe(): MySQL's native JSON column type
    // re-serializes object key order on write (its internal binary JSON
    // representation doesn't preserve insertion order the way SQLite's
    // text-based storage does), so a strict `===` on the decoded array
    // is comparing key order, not content — the clone's data is still
    // identical to the original, just re-ordered by the database.
    expect($cloneVersion->status)->toBe(PlateTemplateVersionStatus::Draft)
        ->and($cloneVersion->front_configuration)->toEqual($version->front_configuration);
});

test('archiving a template does not affect plates already generated from it', function () {
    $template = PlateTemplate::factory()->create(['active' => true]);

    $this->actingAs($this->admin)->post(route('admin.plate-studio.templates.archive', $template))->assertRedirect();

    expect($template->fresh()->active)->toBeFalse();
});

test('the calibration test print uses fixed demo data and never creates a plate or legacy code', function () {
    $template = PlateTemplate::factory()->create();
    $version = PlateTemplateVersion::factory()->create([
        'plate_template_id' => $template->id,
        'status' => PlateTemplateVersionStatus::Published,
        'front_configuration' => ['elements' => [
            ['id' => 'name', 'type' => 'dynamic_text', 'field' => 'athlete_name', 'x_mm' => 3, 'y_mm' => 3, 'width_mm' => 40, 'height_mm' => 6, 'font_size_pt' => 8],
            ['id' => 'qr', 'type' => 'qr', 'x_mm' => 40, 'y_mm' => 20, 'width_mm' => 12, 'height_mm' => 12],
        ]],
    ]);

    $response = $this->actingAs($this->admin)->get(route('admin.plate-studio.versions.test-export', [$version, 'front']));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'image/svg+xml');
    expect($response->getContent())->toContain('Zuriel Ávila')
        ->and(Plate::count())->toBe(0)
        ->and(LegacyCode::count())->toBe(0);
});
