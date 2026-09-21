<?php

namespace App\Actions\Athletes;

use App\Models\EventGearSelection;

class RemoveOwnedProductFromEvent
{
    public function handle(EventGearSelection $selection): void
    {
        $selection->delete();
    }
}
