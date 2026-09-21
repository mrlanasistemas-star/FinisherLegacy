<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Athletes\EnsureAthleteForUser;
use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Api\V1\Concerns\ResolvesAuthenticatedUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateAthleteProfileRequest;
use App\Http\Resources\Api\V1\AthleteProfileResource;
use App\Queries\Athletes\GetAthleteProfileStats;
use App\Services\AthleteProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    use ApiResponses;
    use ResolvesAuthenticatedUser;

    public function __construct(private readonly AthleteProfileService $profiles) {}

    /**
     * `GET /api/v1/me/profile` (product consolidation brief §26) — the
     * Athlete's `uuid` doubles as its "Legacy ID": a stable, public
     * identifier that already exists for every Athlete, so this reuses it
     * rather than adding a second identifier column.
     */
    public function show(Request $request, EnsureAthleteForUser $ensureAthlete, GetAthleteProfileStats $stats): JsonResponse
    {
        $user = $this->sanctumUser($request);
        $athlete = $ensureAthlete->handle($user, 'api_profile_show');
        $profile = $user->athleteProfile;

        return $this->respond([
            'athlete' => [
                'legacy_id' => $athlete->uuid,
                'full_name' => $athlete->full_name,
            ],
            'profile' => $profile ? new AthleteProfileResource($profile->loadMissing('mainSport')) : null,
            'stats' => $stats->handle($athlete),
        ]);
    }

    public function update(UpdateAthleteProfileRequest $request): JsonResponse
    {
        $profile = $this->profiles->update(
            $this->sanctumUser($request),
            $request->safe()->except(['profile_photo', 'cover_photo']),
            $request->file('profile_photo'),
            $request->file('cover_photo'),
        );

        return $this->respond(
            new AthleteProfileResource($profile->loadMissing('mainSport')),
            'Tu Legacy Profile fue actualizado.',
        );
    }
}
