<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Notifications\CampaignOpenTracker;
use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /api/v1/notifications/{id}/opened — the app reports that the person
 * tapped a push (docs/renovacion/campanas-push.md §Aperturas). `{id}` is
 * the push `data.campaign_id` or the notification-center id. Always 200
 * with `recorded` (false for an unknown / already-recorded id), so the app
 * can fire and forget.
 */
class NotificationOpenedController extends Controller
{
    use ApiResponses;

    public function __invoke(Request $request, string $id, CampaignOpenTracker $tracker): JsonResponse
    {
        $user = $request->user('sanctum') ?? $request->user();

        return $this->respond(['recorded' => $tracker->record($user, $id)], 'Apertura registrada.');
    }
}
