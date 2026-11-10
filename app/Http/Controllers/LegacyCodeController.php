<?php

namespace App\Http\Controllers;

use App\Enums\LegacyCodeStatus;
use App\Exceptions\Athletes\AthleteIdentityConflictException;
use App\Exceptions\LegacyCodeClaimConflictException;
use App\Exceptions\LegacyCodeUnavailableException;
use App\Models\LegacyCode;
use App\Services\ClaimLegacyCodeService;
use App\Services\LegacyCodeQrService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class LegacyCodeController extends Controller
{
    public function __construct(
        private readonly ClaimLegacyCodeService $claims,
        private readonly LegacyCodeQrService $qrService,
    ) {}

    public function show(Request $request, string $code): Response
    {
        $legacyCode = LegacyCode::query()
            ->where('code', $code)
            ->with(['plate', 'user.athleteProfile.mainSport'])
            ->first();

        abort_unless($legacyCode !== null, 404);

        $unavailableStatuses = [LegacyCodeStatus::Blocked, LegacyCodeStatus::Cancelled, LegacyCodeStatus::Replaced];

        if (in_array($legacyCode->status, $unavailableStatuses, true)) {
            return Inertia::render('legacy-code/Show', [
                'code' => $legacyCode->code,
                'available' => false,
                'linked' => false,
                'ownedByMe' => false,
                'plate' => null,
                'athlete' => null,
                'qrUrl' => null,
            ]);
        }

        $plate = $legacyCode->plate;
        $profile = $legacyCode->user?->athleteProfile;
        $showProfile = $profile && $profile->profile_visibility->value === 'public';

        return Inertia::render('legacy-code/Show', [
            'code' => $legacyCode->code,
            'available' => true,
            'linked' => $legacyCode->user_id !== null,
            'ownedByMe' => $request->user()?->id !== null && $legacyCode->claimed_by_user_id === $request->user()->id,
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
            'qrUrl' => route('legacy-code.qr', $legacyCode->code),
        ]);
    }

    /**
     * Sends a guest to login/register while remembering this code, so
     * Fortify's redirect()->intended() brings them straight back here to
     * finish the claim once they're authenticated.
     */
    public function continueTo(Request $request, string $code, string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, ['login', 'register'], true), 404);

        $request->session()->put('url.intended', route('legacy-code.show', $code));

        return redirect()->route($provider);
    }

    public function qr(string $code): HttpResponse
    {
        $legacyCode = LegacyCode::query()->where('code', $code)->firstOrFail();

        return response($this->qrService->svg($legacyCode), 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    public function claim(Request $request, string $code): RedirectResponse
    {
        try {
            $result = $this->claims->claim($code, $request->user(), $request->ip());
        } catch (ModelNotFoundException) {
            abort(404);
        } catch (LegacyCodeUnavailableException) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Este código no está disponible para vincularse.')]);

            return back();
        } catch (LegacyCodeClaimConflictException) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Esta placa ya forma parte de otro Legacy.')]);

            return back();
        } catch (AthleteIdentityConflictException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return back();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $result['alreadyOwned']
                ? __('Esta placa ya es parte de tu Legacy.')
                : __('Esta historia ya forma parte de tu Legacy.'),
        ]);

        if ($result['medal']) {
            return to_route('dashboard.medals.show', $result['medal']);
        }

        return redirect()->route('legacy-code.show', $code);
    }
}
