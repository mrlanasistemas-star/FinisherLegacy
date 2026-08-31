<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\ResolveIncident;
use App\Enums\IncidentResolutionType;
use App\Enums\IncidentStatus;
use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ResolveIncidentRequest;
use App\Models\EventIncident;
use Illuminate\Http\JsonResponse;

/**
 * "Resolver" always records what actually happened (brief §136-§138) —
 * gated by `incidents.manage`.
 */
class IncidentController extends Controller
{
    use ApiResponses;

    public function resolve(ResolveIncidentRequest $request, EventIncident $incident, ResolveIncident $resolve): JsonResponse
    {
        abort_if($incident->status === IncidentStatus::Resolved, 409, 'Esta incidencia ya está resuelta.');

        $resolved = $resolve->handle(
            $incident,
            IncidentResolutionType::from($request->string('resolution_type')->toString()),
            $request->user(),
            $request->string('notes')->toString() ?: null,
            $request->array('before_data') ?: null,
            $request->array('after_data') ?: null,
        );

        return $this->respond([
            'id' => $resolved->id,
            'status' => $resolved->status->value,
            'resolution_type' => $resolved->resolution_type->value,
            'resolved_at' => $resolved->resolved_at->toIso8601String(),
        ], 'Incidencia resuelta.');
    }
}
