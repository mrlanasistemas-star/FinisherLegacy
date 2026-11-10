<?php

use App\Services\Portrait\PortraitComposer;
use App\Services\Portrait\PortraitData;
use App\Services\Portrait\PortraitImageTooSmallException;
use App\Services\Portrait\PortraitSpec;
use App\Services\Portrait\PortraitTemplates;
use App\Services\Portrait\TextFitter;

/*
| Portarretrato (docs/renovacion/portarretrato.md): print dimensions, both
| templates, and text that never leaves its box (shrink, then ellipsis).
*/

function portraitJpeg(int $w, int $h): string
{
    $img = imagecreatetruecolor($w, $h);
    imagefilledrectangle($img, 0, 0, $w, $h, (int) imagecolorallocate($img, 180, 60, 50));
    ob_start();
    imagejpeg($img, null, 80);

    return (string) ob_get_clean();
}

function longPortraitData(): PortraitData
{
    return new PortraitData(
        name: 'María Fernanda de los Ángeles Villaseñor Castañeda Hernández-Ortiz',
        eventLine: PortraitData::join([
            'IRONMAN 70.3 Cozumel presentado por una marca con un nombre larguísimo',
            'Cozumel, Quintana Roo, México',
            '20 de septiembre de 2026',
        ]),
        time: '12:57:34.123',
        resultLine: PortraitData::join(['70.3 millas (113 km)', 'Lugar 1,112 de 2,340 · 3º en su categoría de edad 40-44']),
        dedication: str_repeat('Para mamá, que me esperó en cada meta y nunca dejó de creer en mí. ', 8),
    );
}

/**
 * @param  list<array<string, mixed>>  $layout
 */
function assertInsideCanvas(array $layout, int $w, int $h): void
{
    foreach ($layout as $item) {
        if ($item['kind'] === 'text') {
            expect($item['width'])->toBeLessThanOrEqual($item['max_width'] + 0.5)
                ->and($item['left'])->toBeGreaterThanOrEqual(-0.5)
                ->and($item['right'])->toBeLessThanOrEqual($w + 0.5)
                ->and($item['baseline'])->toBeLessThanOrEqual($h);
        }

        if ($item['kind'] === 'paragraph') {
            foreach ($item['lines'] as $line) {
                expect($line['width'])->toBeLessThanOrEqual($item['max_width'] + 0.5)
                    ->and($line['left'])->toBeGreaterThanOrEqual(-0.5)
                    ->and($line['right'])->toBeLessThanOrEqual($w + 0.5);
            }

            expect($item['bottom'])->toBeLessThanOrEqual($h);
        }

        if ($item['kind'] === 'mark' || $item['kind'] === 'rule') {
            expect($item['left'])->toBeGreaterThanOrEqual(0)
                ->and($item['right'])->toBeLessThanOrEqual($w)
                ->and($item['bottom'])->toBeLessThanOrEqual($h);
        }
    }
}

test('the default print is 20×25 cm at 300 dpi = 2362×2953 px', function () {
    $spec = PortraitSpec::fromConfig();

    expect($spec->widthPx())->toBe(2362)
        ->and($spec->heightPx())->toBe(2953)
        ->and($spec->label())->toBe('20 × 25 cm');
});

test('the print size is configurable', function () {
    config(['portrait.print.width_cm' => 13, 'portrait.print.height_cm' => 18, 'portrait.print.dpi' => 300]);

    expect(PortraitSpec::fromConfig()->widthPx())->toBe(1535)
        ->and(PortraitSpec::fromConfig()->heightPx())->toBe(2126);
});

test('there are at least two data-driven templates', function () {
    expect(app(PortraitTemplates::class)->keys())->toContain('carbono', 'hueso')
        ->and(count(app(PortraitTemplates::class)->keys()))->toBeGreaterThanOrEqual(2);
});

test('each template renders a full-resolution jpeg at the print size', function (string $template) {
    $result = app(PortraitComposer::class)->compose($template, portraitJpeg(2400, 2400), null, new PortraitData(
        name: 'Zuriel Ávila',
        eventLine: PortraitData::join(['IRONMAN 70.3 Cozumel', '20 de septiembre de 2026']),
        time: '06:57:34',
        resultLine: '70.3 mi',
        dedication: 'Para mamá.',
    ));

    $size = getimagesizefromstring($result->jpeg);

    expect($size[0])->toBe(2362)
        ->and($size[1])->toBe(2953)
        ->and($size['mime'])->toBe('image/jpeg')
        ->and($result->width)->toBe(2362)
        ->and($result->height)->toBe(2953)
        ->and($result->effectiveDpi)->toBeGreaterThanOrEqual(300);

    // The FL monogram and the NFC call to action are always printed.
    expect(collect($result->layout)->where('kind', 'mark')->count())->toBeGreaterThanOrEqual(1)
        ->and(collect($result->layout)->firstWhere('field', 'cta')['text'])->toBe('ACERCA TU CELULAR');
})->with(['carbono', 'hueso']);

test('long names and texts shrink and get an ellipsis but never overflow', function (string $template) {
    $composer = app(PortraitComposer::class);
    $spec = PortraitSpec::fromConfig();
    $layout = $composer->layout($template, longPortraitData(), $spec);

    assertInsideCanvas($layout, $spec->widthPx(), $spec->heightPx());

    $name = collect($layout)->firstWhere('field', 'name');
    $dedication = collect($layout)->firstWhere('field', 'dedication');

    expect($name['shrunk'])->toBeTrue()
        ->and($name['truncated'])->toBeTrue()
        ->and($name['text'])->toEndWith(TextFitter::ELLIPSIS)
        ->and($dedication['truncated'])->toBeTrue()
        ->and(count($dedication['lines']))->toBeLessThanOrEqual(3)
        ->and(end($dedication['lines'])['text'])->toEndWith(TextFitter::ELLIPSIS);

    // The rendered file with those texts still has the exact print size.
    $result = $composer->compose($template, portraitJpeg(2400, 2400), null, longPortraitData(), $spec);
    expect(getimagesizefromstring($result->jpeg)[0])->toBe(2362);
})->with(['carbono', 'hueso']);

test('short texts keep their full size', function () {
    $layout = app(PortraitComposer::class)->layout('carbono', new PortraitData(name: 'Ana', time: '45:10'));
    $name = collect($layout)->firstWhere('field', 'name');

    expect($name['text'])->toBe('ANA')
        ->and($name['shrunk'])->toBeFalse()
        ->and($name['truncated'])->toBeFalse()
        // Empty fields leave their slot out.
        ->and(collect($layout)->firstWhere('field', 'dedication'))->toBeNull();
});

test('a photo too small for the print size is rejected with a clear message', function () {
    expect(fn () => app(PortraitComposer::class)->compose('carbono', portraitJpeg(600, 600), null, new PortraitData(name: 'Ana')))
        ->toThrow(PortraitImageTooSmallException::class, 'muy pequeña para imprimir a 20 × 25 cm');
});

test('a tight crop counts against the effective resolution', function () {
    $composer = app(PortraitComposer::class);

    // Whole 2400 px photo: fine. A 30 % crop of it: ~ 100 dpi → rejected.
    expect($composer->compose('hueso', portraitJpeg(2400, 2400), null, new PortraitData)->effectiveDpi)->toBeGreaterThan(250);

    expect(fn () => $composer->compose('hueso', portraitJpeg(2400, 2400), ['x' => 0.1, 'y' => 0.1, 'w' => 0.3, 'h' => 0.3], new PortraitData))
        ->toThrow(PortraitImageTooSmallException::class);
});

test('the crop is forced to the photo box ratio whatever the client sends', function () {
    $composer = app(PortraitComposer::class);
    $rect = $composer->cropRect(3000, 2000, 1.0, ['x' => 0.0, 'y' => 0.0, 'w' => 1.0, 'h' => 1.0]);

    expect($rect['w'])->toBe($rect['h'])
        ->and($rect['w'])->toBe(2000)
        ->and($rect['x'])->toBe(500);
});
