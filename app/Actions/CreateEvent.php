<?php

namespace App\Actions;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\EventEdition;
use App\Models\EventRace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Manual event creation (brief §19-§21/§62-§63/§127/§182): Organizer is
 * optional, no API/file source is required. Creates Event + EventEdition
 * + EventRace[] in one transaction — the same Action a future Web admin
 * form and `POST /api/v1/events` both call, so there is never a second
 * "create event" code path.
 */
class CreateEvent
{
    /**
     * @param  array{
     *     name: string, slug?: ?string, sport_id: int, organizer_id?: ?int,
     *     edition_name: string, year: int, event_date: string, timezone?: ?string,
     *     city: string, state?: ?string, country: string,
     *     races: list<array{name: string, distance_value?: ?float, distance_unit?: ?string, race_type?: ?string}>,
     *     data_source_type?: ?string, data_source_provider_connection_id?: ?int,
     * }  $data
     */
    public function handle(array $data): EventEdition
    {
        return DB::transaction(function () use ($data) {
            $event = Event::create([
                'organizer_id' => $data['organizer_id'] ?? null,
                'sport_id' => $data['sport_id'],
                'name' => $data['name'],
                'slug' => $data['slug'] ?? $this->uniqueSlug($data['name']),
                'status' => EventStatus::Draft,
            ]);

            $edition = EventEdition::create([
                'event_id' => $event->id,
                'name' => $data['edition_name'],
                'year' => $data['year'],
                'event_date' => $data['event_date'],
                'city' => $data['city'],
                'state' => $data['state'] ?? null,
                'country' => $data['country'],
                'timezone' => $data['timezone'] ?? 'America/Mexico_City',
                'data_source_type' => $data['data_source_type'] ?? null,
                'data_source_provider_connection_id' => $data['data_source_provider_connection_id'] ?? null,
            ]);

            foreach ($data['races'] as $race) {
                EventRace::create([
                    'event_edition_id' => $edition->id,
                    'name' => $race['name'],
                    'distance_value' => $race['distance_value'] ?? null,
                    'distance_unit' => $race['distance_unit'] ?? null,
                    'race_type' => $race['race_type'] ?? null,
                    'active' => true,
                ]);
            }

            return $edition->fresh(['event', 'races']);
        });
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 1;

        while (Event::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
