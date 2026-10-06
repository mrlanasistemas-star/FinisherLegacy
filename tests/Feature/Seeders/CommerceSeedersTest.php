<?php

use App\Models\LegacyPlateModel;
use App\Models\Organizer;
use App\Models\Product;
use Database\Seeders\CommerceDemoSeeder;
use Database\Seeders\LegacyPlateModelSeeder;
use Database\Seeders\ProductCatalogSeeder;
use App\Enums\ProductType;

test('LegacyPlateModelSeeder seeds exactly three front/back layouts without a printed QR', function () {
    $this->seed(LegacyPlateModelSeeder::class);
    $this->seed(LegacyPlateModelSeeder::class);

    expect(LegacyPlateModel::count())->toBe(3)
        ->and(LegacyPlateModel::query()->pluck('layout_slot')->sort()->values()->all())->toBe([1, 2, 3]);

    $model = LegacyPlateModel::where('slug', 'nucleo-reveal')->firstOrFail();
    expect($model->fields()->count())->toBe(9)
        ->and($model->fields()->where('field_key', 'qr')->exists())->toBeFalse()
        ->and($model->fields()->where('field_key', 'athlete_name')->value('face'))->toBe('front')
        ->and($model->fields()->where('field_key', 'event_name')->value('face'))->toBe('back');
});

test('ProductCatalogSeeder seeds the five ecosystem products with purchasable variants', function () {
    $this->seed(ProductCatalogSeeder::class);

    // The internal "fotografia-digital" product comes from a migration, not this seeder.
    expect(Product::where('type', '!=', ProductType::DigitalPhoto)->count())->toBe(5);
    $trisuit = Product::where('slug', 'trisuit')->firstOrFail();
    expect($trisuit->variants()->count())->toBe(24);

    $legacyPlate = Product::where('slug', 'legacy-plate')->firstOrFail();
    expect($legacyPlate->qr_capable)->toBeTrue()
        ->and($legacyPlate->tracks_inventory)->toBeFalse();
});

test('ProductCatalogSeeder is idempotent — running it twice does not duplicate rows', function () {
    $this->seed(ProductCatalogSeeder::class);
    $this->seed(ProductCatalogSeeder::class);

    // The internal "fotografia-digital" product comes from a migration, not this seeder.
    expect(Product::where('type', '!=', ProductType::DigitalPhoto)->count())->toBe(5);
});

test('CommerceDemoSeeder seeds one manual and one API organizer', function () {
    $this->seed(CommerceDemoSeeder::class);

    expect(Organizer::count())->toBe(2);
    $manual = Organizer::where('slug', 'carrera-local-demo')->firstOrFail();
    expect($manual->dataSource->type->value)->toBe('manual');

    $api = Organizer::where('slug', 'sports-timing-mexico-demo')->firstOrFail();
    expect($api->dataSource->type->value)->toBe('api')
        ->and($api->dataSource->providerConnection)->not->toBeNull();
});
