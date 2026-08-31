<?php

namespace App\Actions\Commerce;

use App\Enums\AthleteOwnedProductStatus;
use App\Exceptions\AssetAlreadyClaimedException;
use App\Models\Athlete;
use App\Models\AthleteOwnedProduct;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * An unclaimed QR-capable unit (sold without a known Athlete yet) can be
 * claimed by whoever scans/enters its asset code while authenticated
 * (brief §89). A `Cache::lock` per asset code plus a row lock inside the
 * transaction make two simultaneous claims resolve to exactly one owner
 * (brief §150).
 */
class ClaimAthleteOwnedProduct
{
    public function handle(string $assetCode, Athlete $athlete): AthleteOwnedProduct
    {
        return Cache::lock("gear-claim:{$assetCode}", 10)->block(5, function () use ($assetCode, $athlete) {
            return DB::transaction(function () use ($assetCode, $athlete) {
                $owned = AthleteOwnedProduct::query()
                    ->where('asset_code', $assetCode)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($owned->status !== AthleteOwnedProductStatus::Unclaimed) {
                    throw new AssetAlreadyClaimedException;
                }

                $owned->update([
                    'athlete_id' => $athlete->id,
                    'status' => AthleteOwnedProductStatus::Active,
                    'activated_at' => now(),
                ]);

                return $owned->fresh();
            });
        });
    }
}
