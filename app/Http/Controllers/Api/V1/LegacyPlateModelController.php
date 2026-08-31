<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\LegacyPlateModelResource;
use App\Models\LegacyPlateModel;
use Illuminate\Http\JsonResponse;

/**
 * `GET /api/v1/legacy-plate-models` — active models only, app-safe fields
 * only (brief §119).
 */
class LegacyPlateModelController extends Controller
{
    use ApiResponses;

    public function index(): JsonResponse
    {
        $models = LegacyPlateModel::query()->where('active', true)->orderBy('name')->get();

        return $this->respond(LegacyPlateModelResource::collection($models));
    }
}
