<?php

namespace App\Http\Controllers\Api\V1\Tracking;

use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Models\TrackingShare;
use App\Queries\Tracking\GetLiveTrackingSnapshot;
use Illuminate\Http\JsonResponse;

/**
 * `GET /api/v1/live/{token}` — public, throttled (`live-view`). Unknown and
 * revoked tokens answer 404; a share that ran its course (its session
 * stopped, or its time is up) answers 410 so the page can say "terminó".
 * Neither carries any athlete data.
 */
class LiveTrackingController extends Controller
{
    use ApiResponses;

    public function show(string $token, GetLiveTrackingSnapshot $snapshot): JsonResponse
    {
        $share = TrackingShare::findByToken($token);

        $reason = $share?->unavailableReason();

        // Family shares are never readable by token (theirs is never handed out).
        if ($share === null || $reason === 'revoked' || $share->audience !== TrackingShare::AUDIENCE_LINK) {
            return response()->json([
                'error' => ['code' => 'NOT_FOUND', 'message' => __('Este enlace de seguimiento ya no está disponible.'), 'details' => (object) []],
            ], 404);
        }

        if ($reason !== null) {
            return response()->json([
                'error' => ['code' => 'TRACKING_ENDED', 'message' => __('El seguimiento terminó.'), 'details' => (object) []],
            ], 410);
        }

        return $this->respond($snapshot->handle($share));
    }
}
