<?php

use App\Enums\PlateTemplateVersionStatus;
use App\Models\PlateTemplateVersion;
use App\Services\FontOutlineService;
use App\Services\PlateTemplateRenderService;
use App\Support\PlateRenderData;

function textAsPathsVersion(): PlateTemplateVersion
{
    return PlateTemplateVersion::factory()->create([
        'status' => PlateTemplateVersionStatus::Published,
        'front_configuration' => ['elements' => [
            ['id' => 'name', 'type' => 'dynamic_text', 'field' => 'athlete_name', 'x_mm' => 3, 'y_mm' => 3, 'width_mm' => 50, 'height_mm' => 8, 'font_size_pt' => 10, 'font_weight' => 700],
        ]],
        'back_configuration' => ['elements' => [
            ['id' => 'qr', 'type' => 'qr', 'x_mm' => 20, 'y_mm' => 10, 'width_mm' => 15, 'height_mm' => 15],
        ]],
    ]);
}

test('the bundled DejaVu Sans font is available for outline conversion', function () {
    expect(app(FontOutlineService::class)->isAvailable())->toBeTrue();
});

test('production svg export with text_as_paths converts text to real glyph outlines', function () {
    $version = textAsPathsVersion();
    $renderer = app(PlateTemplateRenderService::class);

    $svg = $renderer->renderSvg($version, 'front', PlateRenderData::demo(), PlateTemplateRenderService::MODE_PRODUCTION, true);

    expect($svg)->not->toContain('<text')
        ->toContain('<path')
        ->not->toContain('Zuriel Ávila');

    $doc = new DOMDocument;
    expect($doc->loadXML($svg))->toBeTrue();
});

test('the web preview always keeps real <text> even when text_as_paths would be requested (production-only)', function () {
    $version = textAsPathsVersion();
    $renderer = app(PlateTemplateRenderService::class);

    $preview = $renderer->renderSvg($version, 'front', PlateRenderData::demo(), PlateTemplateRenderService::MODE_PRODUCT, true);

    expect($preview)->toContain('<text')
        ->toContain('Zuriel Ávila')
        ->not->toContain('<path');
});

test('without text_as_paths, production export renders normal text as before', function () {
    $version = textAsPathsVersion();
    $renderer = app(PlateTemplateRenderService::class);

    $svg = $renderer->renderSvg($version, 'front', PlateRenderData::demo(), PlateTemplateRenderService::MODE_PRODUCTION);

    expect($svg)->toContain('<text')
        ->toContain('Zuriel Ávila');
});

test('accented composite glyphs (á, é, í, ó, ú, ñ) are not silently dropped from the outline', function () {
    $doc = new DOMDocument;
    $service = app(FontOutlineService::class);

    // DejaVu Sans encodes every one of these as a TrueType *composite*
    // glyph (base letter + separate accent mark component) rather than a
    // simple glyph with its own contours — Outline::getSVGContours() only
    // returns data for simple glyphs, so composites need explicit handling
    // or the accented character renders as an invisible gap.
    $withAccents = $service->buildTextPaths($doc, 'áéíóúñ', 0, 0, 50, 10, 'start', 10, false, '#000000');
    $withoutAccents = $service->buildTextPaths($doc, 'aeiounn', 0, 0, 50, 10, 'start', 10, false, '#000000');

    expect($withAccents)->not->toBeNull()
        ->and($withoutAccents)->not->toBeNull();

    $accentedPathCount = $withAccents->getElementsByTagName('path')->length;
    $plainPathCount = $withoutAccents->getElementsByTagName('path')->length;

    // Each accented character contributes at least 2 path fragments (base
    // letter + accent mark) versus 1 for its plain counterpart — if
    // composite glyphs were being silently skipped, this count would be
    // equal or lower instead of strictly greater.
    expect($accentedPathCount)->toBeGreaterThan($plainPathCount);
});
