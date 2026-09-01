<?php

namespace App\Http\Controllers;

use App\Queries\Athletes\GetAthleteLegado;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request, GetAthleteLegado $legado): Response
    {
        $user = $request->user();
        $user->loadMissing(['legacyId', 'athleteProfile']);

        // Read-only counts for "Mi Legado" (product UX consolidation brief
        // §3-§6): the ecosystem summary reaches beyond medals/events into
        // what the athlete owns, never a second read model — just cheap
        // counts off the same canonical Athlete used by GetAthleteHistory.
        $athlete = $user->athlete()->first();

        $profile = $user->athleteProfile;
        $completion = null;

        if ($profile) {
            $checks = [
                filled($profile->username),
                filled($profile->profile_photo_path),
                filled($profile->bio),
                filled($profile->city),
                $profile->main_sport_id !== null,
                filled($profile->cover_photo_path),
            ];

            $completion = (int) round((count(array_filter($checks)) / count($checks)) * 100);
        }

        return Inertia::render('Dashboard', [
            'legacyId' => $user->legacyId?->code,
            'profile' => $profile ? [
                'username' => $profile->username,
                'profile_visibility' => $profile->profile_visibility->value,
                'completion' => $completion,
            ] : null,
            'stats' => [
                'medals' => $user->medals()->count(),
                'events' => $user->eventParticipations()->count(),
                'plates' => $user->plates()->count(),
                'legacyCodes' => $user->legacyCodes()->count(),
                'ownedProducts' => $athlete === null ? 0 : $athlete->ownedProducts()->count(),
                'media' => $athlete === null ? 0 : $athlete->eventMedia()->count(),
            ],
            'legado' => $athlete === null ? [] : $legado->handle($athlete),
        ]);
    }
}
