<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Athletes\EnsureAthleteForUser;
use App\Actions\Commerce\ClaimAthleteOwnedProduct;
use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Api\V1\Concerns\ResolvesAuthenticatedUser;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AthleteOwnedProductResource;
use App\Http\Resources\Api\V1\PublicGearResource;
use App\Models\AthleteOwnedProduct;
use App\Queries\Nfc\GetMyGear;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Mi equipo" API. `gear/{code}` + `gear/{code}/claim` are the historical
 * QR asset-code flow — DEPRECATED in favour of `/nfc/{token}` (docs/NFC.md
 * §Compatibilidad) but kept working for existing app versions.
 */
class GearController extends Controller
{
    use ApiResponses;
    use ResolvesAuthenticatedUser;

    /**
     * Public, no PII, no auth — brief §88/§107. Deprecated: use GET /nfc/{token}.
     */
    public function publicShow(string $code): JsonResponse
    {
        $owned = AthleteOwnedProduct::query()->where('asset_code', $code)->firstOrFail();

        return $this->respond(new PublicGearResource($owned->loadMissing('product', 'productVariant')))
            ->header('Deprecation', 'true');
    }

    /**
     * Products + connected Legacy Plates, NFC-connected items flagged as
     * "Producto conectado" (additive fields — docs/NFC.md §Mi equipo).
     */
    public function meIndex(Request $request, EnsureAthleteForUser $ensureAthlete, GetMyGear $query): JsonResponse
    {
        $athlete = $ensureAthlete->handle($this->sanctumUser($request), 'me_gear');

        return $this->respond($query->handle($athlete));
    }

    /**
     * Deprecated: use POST /nfc/{token}/claim.
     */
    public function claim(Request $request, string $code, EnsureAthleteForUser $ensureAthlete, ClaimAthleteOwnedProduct $claim): JsonResponse
    {
        $athlete = $ensureAthlete->handle($this->sanctumUser($request), 'gear_claim');
        $owned = $claim->handle($code, $athlete);

        return $this->respond(new AthleteOwnedProductResource($owned->loadMissing('product.media', 'productVariant', 'activeNfcAssignment')), 'Producto reclamado.')
            ->header('Deprecation', 'true');
    }
}
