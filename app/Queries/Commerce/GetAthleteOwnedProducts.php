<?php

namespace App\Queries\Commerce;

use App\Models\Athlete;
use App\Models\AthleteOwnedProduct;
use Illuminate\Database\Eloquent\Collection;

/**
 * The Digital Closet read model (brief §91/§103/§111) — no frontend yet,
 * this is what /api/v1/me/gear and the future "Mi equipo" page both call.
 */
class GetAthleteOwnedProducts
{
    /**
     * @return Collection<int, AthleteOwnedProduct>
     */
    public function handle(Athlete $athlete): Collection
    {
        return $athlete->ownedProducts()
            ->with(['product', 'productVariant'])
            ->orderByDesc('acquired_at')
            ->get();
    }
}
