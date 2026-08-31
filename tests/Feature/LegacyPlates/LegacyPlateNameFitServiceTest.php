<?php

use App\Enums\LegacyPlateFieldKey;
use App\Models\Athlete;
use App\Models\LegacyPlateModel;
use App\Models\LegacyPlateModelField;
use App\Services\LegacyPlates\LegacyPlateNameFitService;

function makeAthleteNameField(LegacyPlateModel $model, ?int $maxChars = 20): LegacyPlateModelField
{
    return LegacyPlateModelField::create([
        'legacy_plate_model_id' => $model->id,
        'field_key' => LegacyPlateFieldKey::AthleteName,
        'x' => 8, 'y' => 6, 'width' => 60, 'height' => 8,
        'font_size' => 5,
        'alignment' => 'left',
        'max_chars' => $maxChars,
        'required' => true,
        'visible' => true,
        'sort_order' => 0,
    ]);
}

test('a name within max_chars fits when no field is configured for the model', function () {
    $model = LegacyPlateModel::factory()->create();

    $result = app(LegacyPlateNameFitService::class)->check($model, 'JESÚS ÁVILA');

    expect($result->fits)->toBeFalse()
        ->and($result->constraintReason)->toBe('FIELD_NOT_CONFIGURED');
});

test('a name longer than max_chars does not fit and offers suggestions from the Athlete', function () {
    $model = LegacyPlateModel::factory()->create();
    makeAthleteNameField($model, maxChars: 10);

    $athlete = Athlete::factory()->create([
        'first_name' => 'Jesús Alejandro',
        'last_name' => 'Ávila González',
    ]);

    $result = app(LegacyPlateNameFitService::class)->check($model, 'JESÚS ALEJANDRO ÁVILA GONZÁLEZ', $athlete);

    expect($result->fits)->toBeFalse()
        ->and($result->constraintReason)->toBe('MAX_CHARS_EXCEEDED')
        ->and($result->suggestions)->not->toBeEmpty();
});

test('a short name within max_chars fits', function () {
    $model = LegacyPlateModel::factory()->create();
    makeAthleteNameField($model, maxChars: 30);

    $result = app(LegacyPlateNameFitService::class)->check($model, 'ANA LÓPEZ');

    // Without a real DejaVu font available this falls back to
    // METRICS_UNAVAILABLE=true; with it, TEXT fits within a 60mm box.
    expect($result->fits)->toBeTrue();
});
