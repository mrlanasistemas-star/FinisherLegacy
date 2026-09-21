<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PublicMedalResource;
use App\Models\AthleteProfile;
use App\Services\PublicAthleteProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicAthleteController extends Controller
{
    use ApiResponses;

    public function __construct(private readonly PublicAthleteProfileService $profiles) {}

    public function show(Request $request, AthleteProfile $athleteProfile): JsonResponse
    {
        $athleteProfile->load(['user', 'mainSport']);

        // Public route (no auth:sanctum middleware) — a Bearer-
        // authenticated mobile viewer must be recognized too, and 'sanctum'
        // is checked first so a stale, unrelated 'web' session cookie can
        // never be mistaken for who is actually asking (see
        // App\Http\Controllers\Api\V1\Concerns\ResolvesAuthenticatedUser).
        $viewer = $request->user('sanctum') ?? $request->user();

        abort_unless($this->profiles->isVisibleTo($athleteProfile, $viewer), 404);

        $medals = $this->profiles->publicMedals($athleteProfile);

        return $this->respond([
            'profile' => $this->profiles->profilePayload($athleteProfile),
            'stats' => $this->profiles->statsPayload($athleteProfile, $medals),
            'medals' => PublicMedalResource::collection($medals),
        ]);
    }
}
