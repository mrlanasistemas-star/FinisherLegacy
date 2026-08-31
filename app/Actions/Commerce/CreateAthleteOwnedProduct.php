<?php

namespace App\Actions\Commerce;

use App\Enums\AthleteOwnedProductStatus;
use App\Models\Athlete;
use App\Models\AthleteOwnedProduct;
use App\Models\OrderItem;
use App\Services\Commerce\AssetCodeService;
use Illuminate\Support\Str;

/**
 * The only place an AthleteOwnedProduct is created — when a QR-capable
 * OrderItem is fulfilled to a known Athlete, it's born already `assigned`
 * (brief §90); when the buyer isn't known yet (a gift, a bulk event-day
 * sale), CreateAthleteOwnedProduct isn't called at all until an Athlete
 * claims it — see App\Actions\Commerce\ClaimAthleteOwnedProduct.
 */
class CreateAthleteOwnedProduct
{
    public function __construct(private readonly AssetCodeService $assetCodes) {}

    public function handle(Athlete $athlete, OrderItem $orderItem): AthleteOwnedProduct
    {
        $orderItem->loadMissing('product');
        $product = $orderItem->product;

        return AthleteOwnedProduct::create([
            'uuid' => (string) Str::uuid(),
            'athlete_id' => $athlete->id,
            'order_item_id' => $orderItem->id,
            'product_id' => $product->id,
            'product_variant_id' => $orderItem->product_variant_id,
            'asset_code' => $product->qr_capable ? $this->assetCodes->generate() : null,
            'status' => AthleteOwnedProductStatus::Assigned,
            'acquired_at' => now(),
            'activated_at' => now(),
        ]);
    }
}
