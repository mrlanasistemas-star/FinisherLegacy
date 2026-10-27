<?php

use App\Actions\LegacyTags\RecordInPersonSale;
use App\Enums\PaymentMethod;
use App\Models\Ally;
use App\Models\InventoryLocation;
use App\Models\Order;
use App\Models\User;

require_once __DIR__.'/../../LegacyTags/helpers.php';

/*
| Fixtures for tests/Feature/Admin/Commercial/* (admin commercial layer).
*/

/** An account linked to `$ally` with only the `ally` (portal) role. */
function allyUser(Ally $ally, string $role = 'seller'): User
{
    $user = User::factory()->create();
    $user->assignRole('ally');
    $ally->users()->attach($user->id, ['role' => $role]);

    return $user;
}

/**
 * @param  list<string>  $codes
 */
function inPersonSale(InventoryLocation $location, array $codes, ?User $seller = null): Order
{
    return app(RecordInPersonSale::class)->handle(
        $location,
        $seller ?? User::factory()->create(),
        PaymentMethod::Cash,
        $codes,
    );
}
