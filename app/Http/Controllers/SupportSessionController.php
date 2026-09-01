<?php

namespace App\Http\Controllers;

use App\Actions\Athletes\EnsureAthleteForUser;
use App\Actions\Support\CreateAthleteSupportSession;
use App\Actions\Support\ModerateSupportMessage;
use App\Enums\SupportActivityType;
use App\Models\AthleteSupportMessage;
use App\Models\EventParticipant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The athlete-facing half of "MI EQUIPO DE APOYO" (product UX
 * consolidation brief §32-§35, §44-§45) — lives inside Mi Legado's event
 * detail (App\Http\Controllers\AthleteHistoryController::legadoShow), not
 * a separate sidebar module (brief §33).
 */
class SupportSessionController extends Controller
{
    public function store(Request $request, EventParticipant $eventParticipant, EnsureAthleteForUser $ensureAthlete, CreateAthleteSupportSession $create): RedirectResponse
    {
        $athlete = $ensureAthlete->handle($request->user(), 'support_session_create');
        abort_unless($eventParticipant->athlete_id === $athlete->id, 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'target_distance_meters' => ['nullable', 'integer', 'min:1'],
        ]);

        $create->handle(
            athlete: $athlete,
            title: $data['title'],
            activityType: SupportActivityType::Event,
            eventParticipant: $eventParticipant,
            targetDistanceMeters: $data['target_distance_meters'] ?? null,
        );

        return back();
    }

    public function approve(Request $request, AthleteSupportMessage $message, EnsureAthleteForUser $ensureAthlete, ModerateSupportMessage $moderate): RedirectResponse
    {
        $athlete = $ensureAthlete->handle($request->user(), 'support_message_moderate');
        $message->loadMissing('session');
        abort_unless($message->session->athlete_id === $athlete->id, 403);

        $moderate->approve($message);

        return back();
    }

    public function reject(Request $request, AthleteSupportMessage $message, EnsureAthleteForUser $ensureAthlete, ModerateSupportMessage $moderate): RedirectResponse
    {
        $athlete = $ensureAthlete->handle($request->user(), 'support_message_moderate');
        $message->loadMissing('session');
        abort_unless($message->session->athlete_id === $athlete->id, 403);

        $moderate->reject($message);

        return back();
    }
}
