<?php

namespace App\Support\Commerce;

use App\Models\InventoryLocation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Which inventory locations a person may sell from (Venta rápida) — the
 * server-side boundary of the ally portal:
 *
 * - Admin POS (`pos.sell`, /admin/venta-rapida): every active location.
 * - Ally portal (`ally.portal`, /aliado/venta-rapida): only the active
 *   locations of the allies the user is linked to through `ally_user`.
 *   An ally user can never read or sell another ally's stock (IDOR).
 */
class PosLocations
{
    /** @return Builder<InventoryLocation> */
    public static function for(User $user, bool $allyPortal): Builder
    {
        $query = InventoryLocation::query()->where('active', true);

        if ($allyPortal) {
            $query->whereIn('ally_id', self::allyIds($user));
        }

        return $query;
    }

    /** @return list<int> */
    public static function allyIds(User $user): array
    {
        return array_values($user->allies()->pluck('allies.id')->map(fn ($id) => (int) $id)->all());
    }
}
