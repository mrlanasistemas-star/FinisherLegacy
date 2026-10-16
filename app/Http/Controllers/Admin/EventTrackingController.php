<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EventEdition;
use App\Queries\Tracking\GetEventTrackingOverview;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Event workspace → "Tracking" tab (SPEC §9): who is being followed live
 * right now, last update, battery, map overview. Read-only.
 */
class EventTrackingController extends Controller
{
    public function show(EventEdition $eventEdition, GetEventTrackingOverview $overview): Response
    {
        $eventEdition->loadMissing('event:id,name');
        $data = $overview->handle($eventEdition);

        return Inertia::render('admin/events/Tracking', [
            'edition' => [
                'id' => $eventEdition->id,
                'name' => $eventEdition->name,
                'event' => $eventEdition->event?->name,
            ],
            'stats' => $data['stats'],
            'sessions' => $data['sessions'],
            'pollSeconds' => 30,
            'map' => config('tracking.map'),
        ]);
    }
}
