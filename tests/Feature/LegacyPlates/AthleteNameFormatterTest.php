<?php

use App\Models\Athlete;
use App\Services\LegacyPlates\AthleteNameFormatter;

test('suggests progressively shorter forms for a long two-part name', function () {
    $athlete = Athlete::factory()->create([
        'first_name' => 'Jesús Alejandro',
        'last_name' => 'Ávila González',
    ]);

    $suggestions = app(AthleteNameFormatter::class)->suggestions($athlete);

    expect($suggestions)->toBe([
        'JESÚS ALEJANDRO ÁVILA GONZÁLEZ',
        'JESÚS ÁVILA',
        'JESÚS A. ÁVILA',
        'J. ALEJANDRO ÁVILA',
    ]);
});

test('a single given name and single surname only produces the short form', function () {
    $suggestions = app(AthleteNameFormatter::class)->suggestionsFor('Ana', 'López');

    expect($suggestions)->toBe(['ANA LÓPEZ']);
});
