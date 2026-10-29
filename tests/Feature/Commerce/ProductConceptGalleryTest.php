<?php

use App\Models\Product;

it('builds the studio-render gallery for the RACEPACK, hero first', function () {
    $gallery = (new Product(['slug' => 'racepack']))->conceptGallery();

    expect($gallery)->toHaveCount(4)
        ->and($gallery[0]['url'])->toEndWith('/media/products/concepts/racepack-1200.webp')
        ->and($gallery[1]['url'])->toEndWith('/media/products/concepts/racepack-front-1200.webp')
        ->and($gallery[0]['srcset'])->toContain('racepack-480.webp 480w')
        ->and($gallery[0]['alt'])->not->toBeEmpty();
});

it('ships every concept render file the gallery points at', function (string $slug) {
    $gallery = (new Product(['slug' => $slug]))->conceptGallery();

    expect($gallery)->not->toBeEmpty();

    foreach ($gallery as $item) {
        foreach ([480, 800, 1200] as $w) {
            $file = public_path(ltrim(str_replace('-1200.webp', "-{$w}.webp", (string) parse_url($item['url'], PHP_URL_PATH)), '/'));
            expect(file_exists($file))->toBeTrue("missing {$file}");
        }
    }
})->with(['racepack', 'trisuit', 'chill-band', 'gorra-fl']);

it('has no concept gallery for products without a render', function () {
    expect((new Product(['slug' => 'no-such-product']))->conceptGallery())->toBe([]);
});
