<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreateEvent;
use App\Enums\EventStatus;
use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CreateEventRequest;
use App\Http\Resources\EventEditionCardResource;
use App\Models\Event;
use App\Services\EventCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    use ApiResponses;

    public function __construct(private readonly EventCatalogService $events) {}

    /**
     * Manual event creation (brief §19-§21/§121/§182) — staff-only
     * (`events.manage`), Organizer optional, no data source required.
     */
    public function store(CreateEventRequest $request, CreateEvent $createEvent): JsonResponse
    {
        $edition = $createEvent->handle([
            'name' => $request->string('name')->toString(),
            'slug' => $request->string('slug')->toString() ?: null,
            'sport_id' => $request->integer('sport_id'),
            'organizer_id' => $request->filled('organizer_id') ? $request->integer('organizer_id') : null,
            'edition_name' => $request->string('edition_name')->toString(),
            'year' => $request->integer('year'),
            'event_date' => $request->string('event_date')->toString(),
            'timezone' => $request->string('timezone')->toString() ?: null,
            'city' => $request->string('city')->toString(),
            'state' => $request->string('state')->toString() ?: null,
            'country' => $request->string('country')->toString(),
            'data_source_type' => $request->string('data_source_type')->toString() ?: null,
            'data_source_provider_connection_id' => $request->filled('data_source_provider_connection_id')
                ? $request->integer('data_source_provider_connection_id')
                : null,
            'races' => array_values(array_map(fn (array $race) => [
                'name' => (string) $race['name'],
                'distance_value' => isset($race['distance_value']) ? (float) $race['distance_value'] : null,
                'distance_unit' => isset($race['distance_unit']) ? (string) $race['distance_unit'] : null,
                'race_type' => isset($race['race_type']) ? (string) $race['race_type'] : null,
            ], $request->array('races'))),
        ]);

        return $this->respond([
            'event' => ['id' => $edition->event->id, 'name' => $edition->event->name, 'slug' => $edition->event->slug],
            'edition' => ['id' => $edition->id, 'name' => $edition->name, 'year' => $edition->year],
            'races' => $edition->races->map(fn ($race) => ['id' => $race->id, 'name' => $race->name]),
        ], 'Evento creado.', status: 201);
    }

    public function index(Request $request): JsonResponse
    {
        $editions = $this->events->publishedEditions([
            'q' => $request->query('q'),
            'sport' => $request->query('sport'),
            'status' => $request->query('status'),
        ]);

        return EventEditionCardResource::collection($editions)->response();
    }

    public function show(Event $event): JsonResponse
    {
        abort_unless($event->status === EventStatus::Published, 404);

        $event->load(['organizer', 'sport']);
        $edition = $this->events->currentEdition($event);

        return $this->respond([
            'name' => $event->name,
            'slug' => $event->slug,
            'description' => $event->description,
            'cover_url' => $event->cover_path ? asset('storage/'.$event->cover_path) : null,
            'sport' => $event->sport->name,
            'organizer' => $event->organizer?->name,
            'edition' => $edition ? [
                'name' => $edition->name,
                'year' => $edition->year,
                'event_date' => $edition->event_date->toDateString(),
                'city' => $edition->city,
                'state' => $edition->state,
                'country' => $edition->country,
                'phase' => $edition->phase,
                'registration_open_at' => $edition->registration_open_at?->toDateString(),
                'registration_close_at' => $edition->registration_close_at?->toDateString(),
                'races' => $edition->races->map(fn ($race) => [
                    'name' => $race->name,
                    'distance_value' => $race->distance_value,
                    'distance_unit' => $race->distance_unit,
                    'start_time' => $race->start_time,
                ]),
            ] : null,
        ]);
    }
}
