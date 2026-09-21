<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Athlete;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Functional, not a CRM (§59) — search + a detail page that proves the
 * whole point of Slice 3: one Athlete, several events, several bibs. See
 * docs/adr/0004-athlete-canonical-identity.md §58-60.
 */
class AthleteController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('q')->toString();

        $athletes = Athlete::query()
            ->where('identity_status', '!=', 'merged')
            ->with('user.legacyId')
            ->withCount('eventParticipations')
            ->when($search !== '', fn ($q) => $q->where(function ($query) use ($search) {
                $query->where('full_name', 'like', "%{$search}%")
                    ->orWhere('normalized_full_name', 'like', '%'.mb_strtolower($search).'%')
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($u) => $u->whereHas('legacyId', fn ($l) => $l->where('code', 'like', "%{$search}%")));
            }))
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();

        $athletes->through(fn (Athlete $athlete) => [
            'id' => $athlete->id,
            'full_name' => $athlete->full_name,
            'email' => $athlete->email ?? '—',
            'has_user' => $athlete->user_id !== null ? 'Sí' : 'No',
            'legacy_id' => $athlete->user?->legacyId->code ?? '—',
            'event_count' => $athlete->event_participations_count,
            'identity_status' => $athlete->identity_status->value,
        ]);

        return Inertia::render('admin/athletes/Index', [
            'athletes' => $athletes,
            'filters' => ['q' => $search],
        ]);
    }

    public function show(Athlete $athlete): Response
    {
        $athlete->loadMissing('user');
        $participations = $athlete->eventParticipations()
            ->with(['eventEdition.event', 'eventRace', 'result'])
            ->orderByDesc('created_at')
            ->get();
        $plates = $athlete->plates()->with(['eventEdition.event', 'legacyCode'])->get();
        $medals = $athlete->medals()->get();

        return Inertia::render('admin/athletes/Show', [
            'athlete' => [
                'id' => $athlete->id,
                'uuid' => $athlete->uuid,
                'full_name' => $athlete->full_name,
                'email' => $athlete->email,
                'phone' => $athlete->phone,
                'birth_date' => $athlete->birth_date?->toDateString(),
                'country' => $athlete->country,
                'identity_status' => $athlete->identity_status->value,
                'user' => $athlete->user ? [
                    'id' => $athlete->user->id,
                    'name' => $athlete->user->name,
                    'email' => $athlete->user->email,
                ] : null,
            ],
            'participations' => $participations->map(fn ($p) => [
                'id' => $p->id,
                'event' => $p->eventEdition?->event?->name,
                'edition' => $p->eventEdition?->name,
                'race' => $p->eventRace?->name,
                'bib_number' => $p->bib_number,
                'official_time' => $p->result?->official_time,
                'source' => $p->source->value,
            ]),
            'plates' => $plates->map(fn ($p) => [
                'id' => $p->id,
                'serial_number' => $p->serial_number,
                'event' => $p->eventEdition?->event?->name,
                'legacy_code' => $p->legacyCode?->code,
                'status' => $p->status->value,
            ]),
            'medals' => $medals->map(fn ($m) => [
                'id' => $m->id,
                'title' => $m->title,
                'event_date' => $m->event_date?->toDateString(),
                'distance_label' => $m->distance_label,
            ]),
        ]);
    }
}
