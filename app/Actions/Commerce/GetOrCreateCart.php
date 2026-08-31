<?php

namespace App\Actions\Commerce;

use App\Models\Cart;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Guest carts key off a session token, authenticated carts off user_id
 * (brief §55/§87) — deliberately no merge-on-login logic yet (out of
 * scope), documented as known debt.
 */
class GetOrCreateCart
{
    public function handle(?User $user, ?string $sessionToken): Cart
    {
        if ($user !== null) {
            return Cart::query()->firstOrCreate(
                ['user_id' => $user->id],
                ['uuid' => (string) Str::uuid(), 'currency' => 'MXN'],
            );
        }

        if ($sessionToken === null) {
            throw new \InvalidArgumentException('Se requiere un usuario autenticado o un session_token de invitado.');
        }

        return Cart::query()->firstOrCreate(
            ['session_token' => $sessionToken],
            ['uuid' => (string) Str::uuid(), 'currency' => 'MXN'],
        );
    }
}
