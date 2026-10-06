<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AthleteEventMediaType;
use App\Http\Controllers\Controller;
use App\Models\AthleteEventMedia;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Administración → Fotografías: an overview of the event media that
 * exists today (AthleteEventMedia, uploaded by athletes per
 * participation). Photographer uploads/review are a future module — the
 * page says so instead of offering an upload flow with nothing behind it.
 */
class EventPhotoController extends Controller
{
    public function index(): Response
    {
        $byEvent = AthleteEventMedia::query()
            ->join('event_participants', 'event_participants.id', '=', 'athlete_event_media.event_participant_id')
            ->join('event_editions', 'event_editions.id', '=', 'event_participants.event_edition_id')
            ->join('events', 'events.id', '=', 'event_editions.event_id')
            ->groupBy('event_editions.id', 'events.name', 'event_editions.name', 'event_editions.event_date')
            ->orderByDesc('event_editions.event_date')
            ->limit(15)
            ->get([
                'event_editions.id as edition_id',
                'events.name as event',
                'event_editions.name as edition',
                'event_editions.event_date as event_date',
                DB::raw('count(*) as total'),
                DB::raw('sum(case when athlete_event_media.is_public = 1 then 1 else 0 end) as public_total'),
            ]);

        return Inertia::render('admin/photos/Index', [
            'stats' => [
                'images' => AthleteEventMedia::query()->where('type', AthleteEventMediaType::Image)->count(),
                'videos' => AthleteEventMedia::query()->where('type', AthleteEventMediaType::Video)->count(),
                'public' => AthleteEventMedia::query()->where('is_public', true)->count(),
                'private' => AthleteEventMedia::query()->where('is_public', false)->count(),
            ],
            'byEvent' => $byEvent->map(fn ($row) => [
                'edition_id' => (int) $row->edition_id,
                'event' => $row->event,
                'edition' => $row->edition,
                'event_date' => $row->event_date ? substr((string) $row->event_date, 0, 10) : null,
                'total' => (int) $row->total,
                'public_total' => (int) $row->public_total,
            ]),
            'recent' => AthleteEventMedia::query()
                ->where('type', AthleteEventMediaType::Image)
                ->with(['eventParticipant.eventEdition.event', 'athlete'])
                ->latest('id')
                ->limit(12)
                ->get()
                ->map(fn (AthleteEventMedia $media) => [
                    'uuid' => $media->uuid,
                    'url' => $media->url(),
                    'is_public' => $media->is_public,
                    'event' => $media->eventParticipant?->eventEdition?->event?->name,
                    'athlete' => $media->athlete?->full_name,
                ]),
        ]);
    }
}
