<?php

namespace App\Actions\Commerce;

use App\Enums\AthleteOwnedProductStatus;
use App\Models\AthleteOwnedProduct;

/**
 * Explicit activation step for a unit that was created already assigned to
 * an Athlete but not yet marked usable (e.g. pending a physical QR print
 * step) — most flows go straight to Active via CreateAthleteOwnedProduct,
 * this exists for whichever ones don't.
 */
class ActivateAthleteOwnedProduct
{
    public function handle(AthleteOwnedProduct $owned): AthleteOwnedProduct
    {
        $owned->update([
            'status' => AthleteOwnedProductStatus::Active,
            'activated_at' => $owned->activated_at ?? now(),
        ]);

        return $owned->fresh();
    }
}
