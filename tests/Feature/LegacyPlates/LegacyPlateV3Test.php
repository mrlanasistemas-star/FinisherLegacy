<?php

use App\Enums\LegacyPlateFieldKey;
use App\Models\EventParticipant;
use App\Models\EventResult;
use App\Models\LegacyPlateModel;
use App\Models\Plate;
use App\Models\User;
use App\Services\PlateGenerationService;
use App\Support\LegacyPlateLayouts;
use Database\Seeders\LegacyPlateModelSeeder;
use Database\Seeders\RolePermissionSeeder;

/**
 * Legacy Plate V3 (docs/architecture/legacy-plate-v3.md): 70 × 45 mm,
 * design on the FRONT only, stainless money clip on the unprinted back,
 * physical spec editable, and every produced plate keeps a frozen copy of
 * the layout it was made with.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

test('the three default layouts are front-only V3 designs at 70 × 45 mm', function () {
    $this->seed(LegacyPlateModelSeeder::class);

    $models = LegacyPlateModel::with('fields')->get();

    expect($models)->toHaveCount(3);

    foreach ($models as $model) {
        expect((float) $model->width_mm)->toBe(70.0)
            ->and((float) $model->height_mm)->toBe(45.0)
            ->and($model->spec_version)->toBe('v3')
            ->and((float) $model->clip_length_mm)->toBe(52.0)
            ->and((float) $model->total_depth_mm)->toBe(6.0)
            ->and($model->fields->pluck('face')->unique()->values()->all())->toBe(['front']);

        // Every field fits inside the plate.
        foreach ($model->fields as $field) {
            expect((float) $field->x + (float) $field->width)->toBeLessThanOrEqual(70.0)
                ->and((float) $field->y + (float) $field->height)->toBeLessThanOrEqual(45.0);
        }
    }

    // Hidden fields really travel as hidden (regression: Eloquent's own ).
    $nucleo = $models->firstWhere('layout_style', 'nucleo')->toViewerArray();
    expect(collect($nucleo['fields'])->firstWhere('field_key', 'event_name')['visible'])->toBeFalse()
        ->and(collect($nucleo['fields'])->firstWhere('field_key', 'athlete_name')['visible'])->toBeTrue();

    $distancia = $models->firstWhere('layout_style', 'distancia');
    expect((float) $distancia->fields->firstWhere('field_key', LegacyPlateFieldKey::Distance)->font_size)->toBeGreaterThan(10.0);
});

test('the viewer payload never carries back-face fields', function () {
    $model = LegacyPlateModel::factory()->create();
    $model->fields()->create(['field_key' => 'athlete_name', 'face' => 'front', 'x' => 4, 'y' => 4, 'width' => 40, 'height' => 8, 'alignment' => 'left']);
    $model->fields()->create(['field_key' => 'event_name', 'face' => 'back', 'x' => 4, 'y' => 4, 'width' => 40, 'height' => 8, 'alignment' => 'left']);

    $viewer = $model->load('fields')->toViewerArray();

    expect(collect($viewer['fields'])->pluck('field_key')->all())->toBe(['athlete_name'])
        ->and($viewer)->not->toHaveKey('back_background')
        ->and($viewer['spec']['clip_material'])->toBe('Acero inoxidable estampado');
});

test('the layout editor rejects fields on the back — it is the clip', function () {
    $model = LegacyPlateModel::factory()->create();
    $field = $model->fields()->create(['field_key' => 'pace', 'face' => 'front', 'x' => 4, 'y' => 4, 'width' => 20, 'height' => 5, 'alignment' => 'left']);

    $this->actingAs($this->admin)
        ->patch("/admin/legacy-plate-models/{$model->id}/fields", ['fields' => [[
            'id' => $field->id, 'face' => 'back', 'x' => 4, 'y' => 4, 'width' => 20, 'height' => 5, 'alignment' => 'left',
        ]]])
        ->assertSessionHasErrors('fields.0.face');

    $this->actingAs($this->admin)
        ->patch("/admin/legacy-plate-models/{$model->id}/fields", ['fields' => [[
            'id' => $field->id, 'x' => 6, 'y' => 4, 'width' => 20, 'height' => 5, 'alignment' => 'left',
        ]]])
        ->assertSessionHasNoErrors();

    expect($field->fresh()->face)->toBe('front')
        ->and((float) $field->fresh()->x)->toBe(6.0);
});

test('the physical spec is editable and applies to every layout', function () {
    $this->seed(LegacyPlateModelSeeder::class);

    $this->actingAs($this->admin)
        ->patch('/admin/legacy-plate-models/spec', [
            'width_mm' => 72, 'height_mm' => 46,
            'clip_length_mm' => 54, 'clip_height_mm' => 21, 'clip_thickness_mm' => 1.2, 'total_depth_mm' => 6.5,
            'body_material' => 'Zamak niquelado', 'clip_material' => 'Acero inoxidable estampado',
        ])
        ->assertSessionHasNoErrors();

    expect(LegacyPlateModel::query()->pluck('width_mm')->map(fn ($v) => (float) $v)->unique()->all())->toBe([72.0])
        ->and(LegacyPlateModel::query()->pluck('clip_length_mm')->map(fn ($v) => (float) $v)->unique()->all())->toBe([54.0]);

    // A clip longer than the plate is not a real piece.
    $this->actingAs($this->admin)
        ->patch('/admin/legacy-plate-models/spec', [
            'width_mm' => 70, 'height_mm' => 45,
            'clip_length_mm' => 80, 'clip_height_mm' => 20, 'clip_thickness_mm' => 1, 'total_depth_mm' => 6,
            'body_material' => 'Zamak niquelado', 'clip_material' => 'Acero',
        ])
        ->assertSessionHasErrors('clip_length_mm');
});

test('a generated plate freezes its layout so later edits never change an order', function () {
    $this->seed(LegacyPlateModelSeeder::class);
    $model = LegacyPlateModel::with('fields')->where('layout_style', 'nucleo')->firstOrFail();
    $participant = EventParticipant::factory()->create();
    EventResult::factory()->create(['event_participant_id' => $participant->id]);

    $plate = app(PlateGenerationService::class)->generateForLegacyPlateModel($participant, $model);

    expect($plate->layout_version)->toBe(LegacyPlateLayouts::SPEC_VERSION)
        ->and($plate->layout_snapshot['spec_version'])->toBe('v3')
        ->and((float) $plate->layout_snapshot['width_mm'])->toBe(70.0);

    // The admin later moves a field and changes the plate size…
    $model->fields()->where('field_key', 'athlete_name')->update(['x' => 20]);
    $model->update(['width_mm' => 80]);

    $viewer = $plate->fresh()->layoutViewer();
    expect((float) $viewer['width_mm'])->toBe(70.0)
        ->and((float) collect($viewer['fields'])->firstWhere('field_key', 'athlete_name')['x'])->toBe(4.0);
});

test('a historical plate snapshot keeps its printed back for the viewer', function () {
    $plate = Plate::factory()->create([
        'layout_snapshot' => [
            'spec_version' => 'v2',
            'name' => 'Núcleo',
            'width_mm' => 90,
            'height_mm' => 34,
            'back_background' => '#171714',
            'fields' => [['field_key' => 'event_date', 'face' => 'back', 'x' => 8, 'y' => 8, 'width' => 74, 'height' => 4, 'font_size' => 2.6, 'alignment' => 'center', 'visible' => true]],
        ],
    ]);

    $viewer = $plate->layoutViewer();

    expect($viewer['spec_version'])->toBe('v2')
        ->and($viewer['fields'][0]['face'])->toBe('back')
        ->and((float) $viewer['width_mm'])->toBe(90.0);
});
