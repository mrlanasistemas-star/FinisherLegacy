<?php

use App\Services\Portrait\PortraitComposer;
use App\Services\Portrait\PortraitData;
use App\Services\Portrait\PortraitImageTooSmallException;
use App\Services\Portrait\PortraitOptions;
use App\Services\Portrait\PortraitPdf;
use App\Services\Portrait\PortraitSpec;
use App\Services\Portrait\PortraitTemplates;
use App\Services\Portrait\SrgbProfile;
use App\Services\Portrait\TextFitter;

/*
| Portarretrato (docs/renovacion/portarretrato.md): print dimensions, every
| template, text that never leaves its box (shrink, then ellipsis), rows
| that flow when data is missing, bleed / crop marks / sRGB / PDF export.
*/

const PORTRAIT_TEMPLATES = ['carbono', 'hueso', 'minimal', 'editorial'];

function portraitJpeg(int $w, int $h): string
{
    $img = imagecreatetruecolor($w, $h);
    imagefilledrectangle($img, 0, 0, $w, $h, (int) imagecolorallocate($img, 180, 60, 50));
    ob_start();
    imagejpeg($img, null, 80);

    return (string) ob_get_clean();
}

function fullPortraitData(): PortraitData
{
    return new PortraitData(
        name: 'Zuriel Ávila',
        eventLine: PortraitData::join(['IRONMAN 70.3 Cozumel', '20 de septiembre de 2026']),
        time: '06:57:34',
        dedication: 'Para mamá.',
        distance: '70.3 mi',
        position: '112',
        pace: '5:52 min/km',
        bib: '1284',
        category: 'Varonil 35-39',
    );
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
        dedication: str_repeat('Para mamá, que me esperó en cada meta y nunca dejó de creer en mí. ', 8),
        distance: '70.3 millas (113 kilómetros)',
        position: '1,112 de 2,340 participantes',
        pace: '5:52 min/km promedio',
        bib: '123456789012',
        category: 'Veteranos Varonil 40-44 años',
    );
}

/**
 * Nothing leaves its box, the safe margin or its stack, and stacks never
 * run into each other.
 *
 * @param  list<array<string, mixed>>  $layout
 */
function assertInsideCanvas(array $layout, int $w, int $h): void
{
    // The frame covers the border of the print: 4 % stays free of text.
    $margin = 0.04 * $w;
    $stacks = [];

    foreach ($layout as $item) {
        if ($item['kind'] === 'text') {
            expect($item['width'])->toBeLessThanOrEqual($item['max_width'] + 1.0)
                ->and($item['left'])->toBeGreaterThanOrEqual($margin)
                ->and($item['right'])->toBeLessThanOrEqual($w - $margin + 1.0)
                ->and($item['top'])->toBeGreaterThanOrEqual($margin)
                ->and($item['bottom'])->toBeLessThanOrEqual($h - $margin);
        }

        if ($item['kind'] === 'paragraph') {
            foreach ($item['lines'] as $line) {
                expect($line['width'])->toBeLessThanOrEqual($item['max_width'] + 1.0)
                    ->and($line['left'])->toBeGreaterThanOrEqual($margin)
                    ->and($line['right'])->toBeLessThanOrEqual($w - $margin + 1.0);
            }

            expect($item['bottom'])->toBeLessThanOrEqual($h - $margin);
        }

        if ($item['kind'] === 'mark' || $item['kind'] === 'rule') {
            expect($item['left'])->toBeGreaterThanOrEqual(0)
                ->and($item['right'])->toBeLessThanOrEqual($w)
                ->and($item['bottom'])->toBeLessThanOrEqual($h);
        }

        if ($item['kind'] === 'stack') {
            expect($item['top'])->toBeGreaterThanOrEqual($item['limit_top'] - 1.0)
                ->and($item['bottom'])->toBeLessThanOrEqual($item['limit_bottom'] + 1.0);
            $stacks[] = $item;
        }
    }

    usort($stacks, fn (array $a, array $b) => $a['top'] <=> $b['top']);

    foreach ($stacks as $i => $stack) {
        if ($i > 0) {
            expect($stack['top'])->toBeGreaterThanOrEqual($stacks[$i - 1]['bottom']);
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

test('the four data-driven templates exist and carbono is the default', function () {
    $templates = app(PortraitTemplates::class);

    expect($templates->keys())->toBe(PORTRAIT_TEMPLATES)
        ->and($templates->defaultKey())->toBe('carbono');

    foreach ($templates->forClient(PortraitSpec::fromConfig()) as $template) {
        expect($template['label'])->not->toBe('')
            ->and($template['description'])->not->toBe('')
            ->and($template['aspect'])->toBeGreaterThan(0.5);
    }
});

test('each template renders a full-resolution jpeg at the print size', function (string $template) {
    $result = app(PortraitComposer::class)->compose($template, portraitJpeg(2600, 3200), null, fullPortraitData());
    $size = getimagesizefromstring($result->jpeg);

    expect($size[0])->toBe(2362)
        ->and($size[1])->toBe(2953)
        ->and($size['mime'])->toBe('image/jpeg')
        ->and($result->width)->toBe(2362)
        ->and($result->height)->toBe(2953)
        ->and($result->trimWidth)->toBe(2362)
        ->and($result->bleedPx)->toBe(0)
        ->and($result->effectiveDpi)->toBeGreaterThanOrEqual(300);

    assertInsideCanvas($result->layout, 2362, 2953);

    // The FL monogram and the NFC call to action are always printed.
    expect(collect($result->layout)->where('kind', 'mark')->count())->toBeGreaterThanOrEqual(1)
        ->and(collect($result->layout)->firstWhere('field', 'cta')['text'])->toBe('ACERCA TU CELULAR')
        ->and(collect($result->layout)->firstWhere('field', 'time')['text'])->toBe('06:57:34');
})->with(PORTRAIT_TEMPLATES);

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
        ->and(count($dedication['lines']))->toBeLessThanOrEqual(2)
        ->and(end($dedication['lines'])['text'])->toEndWith(TextFitter::ELLIPSIS);

    // The rendered file with those texts still has the exact print size.
    $result = $composer->compose($template, portraitJpeg(2600, 3200), null, longPortraitData(), $spec);
    expect(getimagesizefromstring($result->jpeg)[0])->toBe(2362);
})->with(PORTRAIT_TEMPLATES);

test('the layout holds at any print size', function (string $template) {
    config(['portrait.print.width_cm' => 13, 'portrait.print.height_cm' => 18]);
    $spec = PortraitSpec::fromConfig();

    foreach ([fullPortraitData(), longPortraitData(), new PortraitData(name: 'Ana')] as $data) {
        assertInsideCanvas(app(PortraitComposer::class)->layout($template, $data, $spec), $spec->widthPx(), $spec->heightPx());
    }
})->with(PORTRAIT_TEMPLATES);

test('short texts keep their full size and empty fields leave no row', function (string $template) {
    $layout = collect(app(PortraitComposer::class)->layout($template, new PortraitData(name: 'Ana')));
    $name = $layout->firstWhere('field', 'name');

    expect($name['text'])->toBe('ANA')
        ->and($name['truncated'])->toBeFalse()
        ->and($layout->firstWhere('field', 'dedication'))->toBeNull()
        ->and($layout->firstWhere('field', 'time'))->toBeNull()
        ->and($layout->firstWhere('field', 'time_label'))->toBeNull()
        ->and($layout->filter(fn (array $i) => str_starts_with((string) ($i['field'] ?? ''), 'stat.'))->count())->toBe(0)
        // Still signed: monogram + call to action.
        ->and($layout->firstWhere('field', 'cta'))->not->toBeNull();

    assertInsideCanvas($layout->all(), 2362, 2953);
})->with(PORTRAIT_TEMPLATES);

test('race data prints as labelled stats, with the free-text result as fallback', function () {
    $composer = app(PortraitComposer::class);
    $layout = collect($composer->layout('hueso', fullPortraitData()));

    expect($layout->firstWhere('field', 'stat.distance.label')['text'])->toBe('DISTANCIA')
        ->and($layout->firstWhere('field', 'stat.distance.value')['text'])->toBe('70.3 MI')
        ->and($layout->firstWhere('field', 'stat.pace.value')['text'])->toBe('5:52 MIN/KM')
        ->and($layout->firstWhere('field', 'stat.position.label')['text'])->toBe('LUGAR')
        ->and($layout->firstWhere('field', 'stat.category.value')['text'])->toBe('VARONIL 35-39')
        // Four columns at most: the bib is the fifth and is left out.
        ->and($layout->firstWhere('field', 'stat.bib.value'))->toBeNull();

    $legacy = collect($composer->layout('carbono', new PortraitData(name: 'Ana', time: '45:10', resultLine: '10 km · Lugar 8')));

    expect($legacy->firstWhere('field', 'stat.result_line.value')['text'])->toBe('10 KM · LUGAR 8');

    $stats = (new PortraitData(bib: '77', pace: '4:30'))->stats();
    expect(array_column($stats, 'key'))->toBe(['pace', 'bib']);
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

test('a preview is the same layout at screen size and skips the resolution check', function () {
    $spec = PortraitSpec::fromConfig()->scaledToWidth(720);
    $result = app(PortraitComposer::class)->compose('editorial', portraitJpeg(400, 500), null, fullPortraitData(), $spec, PortraitOptions::preview());
    $size = getimagesizefromstring($result->jpeg);

    expect($size[0])->toBeGreaterThanOrEqual(716)->toBeLessThanOrEqual(724)
        ->and(abs($size[1] / $size[0] - 1.25))->toBeLessThan(0.01);

    assertInsideCanvas($result->layout, $size[0], $size[1]);
});

test('the print-shop export adds 3 mm of bleed around the trim size', function () {
    $result = app(PortraitComposer::class)->compose('minimal', portraitJpeg(2600, 3200), null, fullPortraitData(), null, PortraitOptions::forPrintShop());
    $size = getimagesizefromstring($result->jpeg);

    // 3 mm at 300 dpi = 35 px per side.
    expect($result->bleedPx)->toBe(35)
        ->and($size[0])->toBe(2362 + 70)
        ->and($size[1])->toBe(2953 + 70)
        ->and($result->trimWidth)->toBe(2362)
        ->and($result->trimHeight)->toBe(2953);

    // The bleed continues the artwork (full-bleed photo), it is not white.
    $image = imagecreatefromstring($result->jpeg);
    $corner = imagecolorsforindex($image, imagecolorat($image, 5, 5));
    expect($corner['red'])->toBeGreaterThan(120)->and($corner['green'])->toBeLessThan(110);
});

test('crop marks sit in a white slug outside the bleed', function () {
    $result = app(PortraitComposer::class)->compose('carbono', portraitJpeg(2600, 3200), null, fullPortraitData(), null, PortraitOptions::forPrintShop(cropMarks: true));
    $image = imagecreatefromstring($result->jpeg);

    // 6 mm slug = 71 px; 71 + 35 = 106 px from the file edge to the trim.
    expect($result->slugPx)->toBe(71)
        ->and($result->width)->toBe(2362 + 2 * 106)
        ->and($result->height)->toBe(2953 + 2 * 106);

    $luma = fn (int $x, int $y): int => imagecolorsforindex($image, imagecolorat($image, $x, $y))['green'];

    expect($luma(8, 8))->toBeGreaterThan(240)          // slug corner: white
        ->and($luma(106, 20))->toBeLessThan(60)        // vertical mark on the trim line
        ->and($luma(20, 106))->toBeLessThan(60)        // horizontal mark on the trim line
        ->and($luma(90, 90))->toBeLessThan(60)         // bleed: carbon background
        ->and($luma(106, 68))->toBeGreaterThan(200);   // marks stop before the bleed
});

test('the exported jpeg is tagged sRGB and keeps its print resolution', function () {
    $result = app(PortraitComposer::class)->compose('hueso', portraitJpeg(2600, 3200), null, fullPortraitData());

    expect($result->jpeg)->toContain("ICC_PROFILE\0")
        // JFIF density: units = dots per inch, 300 × 300.
        ->and(substr($result->jpeg, 13, 5))->toBe("\x01\x01\x2C\x01\x2C")
        ->and(imagecreatefromstring($result->jpeg))->not->toBeFalse();

    $profile = SrgbProfile::bytes();

    expect(unpack('N', substr($profile, 0, 4))[1])->toBe(strlen($profile))
        ->and(substr($profile, 12, 12))->toBe('mntrRGB XYZ ')
        ->and(substr($profile, 36, 4))->toBe('acsp')
        ->and(strlen($profile) % 4)->toBe(0);
});

test('the pdf export is one page at the exact physical size', function () {
    $result = app(PortraitComposer::class)->compose('carbono', portraitJpeg(2600, 3200), null, fullPortraitData());
    $pdf = app(PortraitPdf::class)->wrap($result);

    expect($pdf)->toStartWith('%PDF-')
        ->and(preg_match_all('#/Type\s*/Page\b#', $pdf))->toBe(1);

    // 20 × 25 cm = 566.9 × 708.7 pt.
    preg_match('#/MediaBox\s*\[\s*0(?:\.0+)?\s+0(?:\.0+)?\s+([\d.]+)\s+([\d.]+)\s*\]#', $pdf, $box);

    expect(abs((float) $box[1] - 566.9))->toBeLessThan(0.5)
        ->and(abs((float) $box[2] - 708.7))->toBeLessThan(0.5);
});
