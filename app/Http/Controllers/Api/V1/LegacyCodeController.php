<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\LegacyCodeStatus;
use App\Exceptions\Athletes\AthleteIdentityConflictException;
use App\Exceptions\LegacyCodeClaimConflictException;
use App\Exceptions\LegacyCodeUnavailableException;
use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Api\V1\Concerns\ResolvesAuthenticatedUser;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\MedalResource;
use App\Models\LegacyCode;
use App\Services\ClaimLegacyCodeService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LegacyCodeController extends Controller
{
    use ApiResponses;
    use ResolvesAuthenticatedUser;

    public function __construct(private readonly ClaimLegacyCodeService $claims) {}

    public function show(Request $request, string $code): JsonResponse
    {
        $legacyCode = LegacyCode::query()
            ->where('code', $code)
            ->with(['plate', 'user.athleteProfile.mainSport'])
            ->first();

        abort_unless($legacyCode !== null, 404);

        $unavailable = in_array($legacyCode->status, [
            LegacyCodeStatus::Blocked,
            LegacyCodeStatus::Cancelled,
            LegacyCodeStatus::Replaced,
        ], true);

        if ($unavailable) {
            return $this->respond([
                'code' => $legacyCode->code,
                'available' => false,
                'linked' => false,
                'plate' => null,
            ]);
        }

        $plate = $legacyCode->plate;
        $profile = $legacyCode->user?->athleteProfile;
        $showProfile = $profile && $profile->profile_visibility->value === 'public';
        // Public route (no auth:sanctum middleware) — a logged-in web
        // session and a Bearer-authenticated mobile client are both valid
        // "viewers" here, so both guards are checked explicitly rather
        // than relying on the default guard alone.
        $viewer = $request->user('sanctum') ?? $request->user();

        return $this->respond([
            'code' => $legacyCode->code,
            'available' => true,
            'linked' => $legacyCode->user_id !== null,
            'owned_by_me' => $viewer !== null && $legacyCode->claimed_by_user_id === $viewer->id,
            'plate' => $plate ? [
                'athlete_name' => $plate->athlete_name,
                'event_name' => $plate->event_name,
                'race_name' => $plate->race_name,
                'official_time' => $plate->official_time,
                'pace' => $plate->pace,
                'event_date' => $plate->event_date?->toDateString(),
                'status' => $plate->status->value,
            ] : null,
            'athlete' => $showProfile ? [
                'username' => $profile->username,
                'city' => $profile->city,
                'sport' => $profile->mainSport?->name,
            ] : null,
        ]);
    }

    public function claim(Request $request, string $code): JsonResponse
    {
        try {
            $result = $this->claims->claim($code, $this->sanctumUser($request), $request->ip());
        } catch (ModelNotFoundException) {
            abort(404);
        } catch (LegacyCodeUnavailableException) {
            return $this->respond(null, 'Este código no está disponible para vincularse.', status: 403);
        } catch (LegacyCodeClaimConflictException) {
            return $this->respond(null, 'Esta placa ya forma parte de otro Legacy.', status: 409);
        } catch (AthleteIdentityConflictException $e) {
            return $this->respond(null, $e->getMessage(), status: 409);
        }

        return $this->respond([
            'legacy_code' => $result['legacyCode']->code,
            'medal' => $result['medal'] ? new MedalResource($result['medal']) : null,
        ], $result['alreadyOwned']
            ? 'Esta placa ya es parte de tu Legacy.'
            : 'Esta historia ya forma parte de tu Legacy.');
    }
}
