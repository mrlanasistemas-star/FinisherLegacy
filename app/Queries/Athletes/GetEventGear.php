<?php

namespace App\Queries\Athletes;

use App\Models\EventGearSelection;
use App\Models\EventParticipant;
use Illuminate\Database\Eloquent\Collection;

/**
 * "Equipo utilizado" for one participation (product consolidation brief
 * §19/§102) — what App\Models\AthleteOwnedProduct entries this
 * App\Models\EventParticipant used, not everything the Athlete owns.
 */
class GetEventGear
{
    /**
     * @return Collection<int, EventGearSelection>
     */
    public function handle(EventParticipant $participant): Collection
    {
        return $participant->gearSelections()
            ->with(['athleteOwnedProduct.product', 'athleteOwnedProduct.productVariant'])
            ->orderByDesc('selected_at')
            ->get();
    }
}
