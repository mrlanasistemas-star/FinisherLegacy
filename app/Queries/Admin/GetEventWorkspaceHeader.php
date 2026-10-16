<?php

namespace App\Queries\Admin;

use App\Models\EventEdition;
use App\Models\EventPhoto;
use App\Models\EventResult;

/**
 * The header every tab of the admin event workspace shares (spec §9):
 * identity of the event + one count per tab. Four cheap indexed counts on
 * event_edition_id — never a per-row query, never the tab's own list.
 */
class GetEventWorkspaceHeader
{
    /**
     * @return array<string, mixed>
     */
    public function handle(EventEdition $edition): array
    {
        $edition->loadMissing('event.organizer');

        return [
            'id' => $edition->id,
            'base_url' => "/admin/events/{$edition->id}",
            'name' => $edition->event->name,
            'edition' => $edition->name,
            'event_date' => $edition->event_date->toDateString(),
            'city' => $edition->city,
            'state' => $edition->state,
            'country' => $edition->country,
            'status' => $edition->status->value,
            'phase' => $edition->phase,
            'slug' => $edition->event->slug,
            'organizer' => $edition->event->organizer?->name,
            'counts' => [
                'participants' => $edition->participants()->count(),
                'results' => EventResult::query()
                    ->whereIn('event_participant_id', $edition->participants()->select('id'))
                    ->count(),
                'preregistrations' => $edition->preregistrations()->count(),
                'media' => EventPhoto::query()->where('event_edition_id', $edition->id)->count(),
            ],
        ];
    }
}
