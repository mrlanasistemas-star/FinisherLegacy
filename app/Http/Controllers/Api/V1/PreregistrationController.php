<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePreregistrationRequest;
use App\Models\EventEdition;
use App\Models\EventPreregistration;
use App\Models\EventRace;
use App\Services\PreregistrationService;
use Illuminate\Http\JsonResponse;

class PreregistrationController extends Controller
{
    use ApiResponses;

    public function __construct(private readonly PreregistrationService $preregistrations) {}

    public function store(StorePreregistrationRequest $request, EventEdition $edition): JsonResponse
    {
        abort_unless($this->preregistrations->isOpen($edition), 403);

        $race = EventRace::query()
            ->where('event_edition_id', $edition->id)
            ->when(
                $request->filled('event_race_uuid'),
                fn ($q) => $q->where('uuid', $request->string('event_race_uuid')->toString()),
                fn ($q) => $q->whereKey($request->integer('event_race_id')),
            )
            ->firstOrFail();

        $preregistration = $this->preregistrations->create(
            $edition,
            $race,
            $request->safe()->except(['event_race_id', 'event_race_uuid']),
            $request->user(),
        );

        return $this->respond([
            'token' => $preregistration->token,
            'status' => $preregistration->status->value,
        ], 'Prerregistro confirmado.', status: 201);
    }

    public function show(string $token): JsonResponse
    {
        $preregistration = EventPreregistration::query()
            ->where('token', $token)
            ->with(['eventEdition.event', 'eventRace'])
            ->firstOrFail();

        return $this->respond([
            'token' => $preregistration->token,
            'status' => $preregistration->status->value,
            'first_name' => $preregistration->first_name,
            'last_name' => $preregistration->last_name,
            'bib_number' => $preregistration->bib_number,
            'event' => $preregistration->eventEdition->event->name,
            'edition' => $preregistration->eventEdition->name,
            'race' => $preregistration->eventRace->name,
        ]);
    }
}
