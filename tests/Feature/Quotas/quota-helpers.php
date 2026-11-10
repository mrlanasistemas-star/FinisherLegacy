<?php

use App\Enums\ProductPriceType;
use App\Models\PhotoExpansionPack;
use App\Models\Product;
use App\Models\ProductPriceSchedule;

require_once __DIR__.'/../LegacyTags/story-helpers.php';

/*
| Fixtures for tests/Feature/Quotas — amounts here are test data only,
| never real tariffs.
*/

/** A global, sellable expansion pack with one price in force. */
function expansionPack(int $photos = 10, int $amountMinor = 5000, string $currency = 'USD', array $priceOverrides = []): PhotoExpansionPack
{
    $pack = PhotoExpansionPack::create(['name' => 'Pack de prueba', 'photos' => $photos, 'active' => true]);

    ProductPriceSchedule::create([
        'product_id' => Product::query()->where('slug', PhotoExpansionPack::PRODUCT_SLUG)->value('id'),
        'photo_expansion_pack_id' => $pack->id,
        'price_type' => ProductPriceType::Standard,
        'amount_minor' => $amountMinor,
        'currency' => $currency,
        'tax_included' => true,
        'starts_at' => now()->subDay(),
        'active' => true,
        ...$priceOverrides,
    ]);

    return $pack->fresh();
}
