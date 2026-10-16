<?php

namespace App\Http\Controllers\Nfc;

use App\Actions\Athletes\EnsureAthleteForUser;
use App\Http\Controllers\Controller;
use App\Queries\Nfc\GetMyGear;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Mi equipo" (route `dashboard.my-gear`, URL kept) — connected products and
 * Legacy Plates. Same read model as `/api/v1/me/gear`.
 */
class MyGearController extends Controller
{
    public function __invoke(Request $request, EnsureAthleteForUser $ensureAthlete, GetMyGear $gear): Response
    {
        $athlete = $ensureAthlete->handle($request->user(), 'dashboard_my_gear');

        return Inertia::render('dashboard/MyGear', [
            'items' => $gear->handle($athlete),
            'highlight' => $request->string('item')->limit(80)->toString() ?: null,
        ]);
    }
}
