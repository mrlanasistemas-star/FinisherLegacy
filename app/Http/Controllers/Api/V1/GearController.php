<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Athletes\EnsureAthleteForUser;
use App\Actions\Commerce\ClaimAthleteOwnedProduct;
use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AthleteOwnedProductResource;
use App\Http\Resources\Api\V1\PublicGearResource;
use App\Models\AthleteOwnedProduct;
use App\Queries\Commerce\GetAthleteOwnedProducts;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GearController extends Controller
{
    use ApiResponses;

    /**
     * Public, no PII, no auth — brief §88/§107.
     */
    public function publicShow(string $code): JsonResponse
    {
        $owned = AthleteOwnedProduct::query()->where('asset_code', $code)->firstOrFail();

        return $this->respond(new PublicGearResource($owned->loadMissing('product', 'productVariant')));
    }

    public function meIndex(Request $request, EnsureAthleteForUser $ensureAthlete, GetAthleteOwnedProducts $query): JsonResponse
    {
        $athlete = $ensureAthlete->handle($request->user(), 'me_gear');

        return $this->respond(AthleteOwnedProductResource::collection($query->handle($athlete)));
    }

    public function claim(Request $request, string $code, EnsureAthleteForUser $ensureAthlete, ClaimAthleteOwnedProduct $claim): JsonResponse
    {
        $athlete = $ensureAthlete->handle($request->user(), 'gear_claim');
        $owned = $claim->handle($code, $athlete);

        return $this->respond(new AthleteOwnedProductResource($owned->loadMissing('product', 'productVariant')), 'Producto reclamado.');
    }
}
