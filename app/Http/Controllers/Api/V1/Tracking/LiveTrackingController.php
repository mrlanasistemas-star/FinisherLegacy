<?php

namespace App\Http\Controllers\Api\V1\Tracking;

use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Models\TrackingShare;
use App\Queries\Tracking\GetLiveTrackingSnapshot;
use Illuminate\Http\JsonResponse;

/**
 * `GET /api/v1/live/{token}` — public, throttled (`live-view`). Revoked,
 * expired and unknown tokens all answer the same 404 (no oracle).
 */
class LiveTrackingController extends Controller
{
    use ApiResponses;

    public function show(string $token, GetLiveTrackingSnapshot $snapshot): JsonResponse
    {
        $share = TrackingShare::findByToken($token);

        if ($share === null || ! $share->isUsable()) {
            return response()->json([
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Este enlace de seguimiento ya no está disponible.', 'details' => (object) []],
            ], 404);
        }

        return $this->respond($snapshot->handle($share));
    }
}
