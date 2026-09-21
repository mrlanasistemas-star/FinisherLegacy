<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\DataSourcePurpose;
use App\Http\Controllers\Api\V1\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SetOrganizerDataSourceRequest;
use App\Http\Resources\Api\V1\OrganizerDataSourceResource;
use App\Models\Organizer;
use App\Models\OrganizerDataSource;
use Illuminate\Http\JsonResponse;

/**
 * "Cómo recibe datos este Organizer" (brief §16-§17/§49-§51/§122) — staff
 * API, gated by `eventdata.manage`. Never returns
 * `ProviderConnection.credentials`.
 */
class OrganizerDataSourceController extends Controller
{
    use ApiResponses;

    public function show(Organizer $organizer): JsonResponse
    {
        $dataSource = $organizer->dataSource()->with('providerConnection')->first();

        return $this->respond($dataSource ? new OrganizerDataSourceResource($dataSource) : null);
    }

    public function update(SetOrganizerDataSourceRequest $request, Organizer $organizer): JsonResponse
    {
        // Targets the default 'both'-purpose source specifically, not just
        // "any row for this organizer" — Organizer now supports multiple
        // sources (brief §23-§27), so organizer_id alone is no longer
        // unique. This endpoint's contract stays "set THE default source"
        // exactly as before; multi-source management is a web-only
        // surface (see Admin\OrganizerDataSourceController).
        $dataSource = OrganizerDataSource::query()->updateOrCreate(
            ['organizer_id' => $organizer->id, 'is_default' => true, 'purpose' => DataSourcePurpose::Both->value],
            [
                'type' => $request->string('type')->toString(),
                'provider_connection_id' => $request->integer('provider_connection_id') ?: null,
                'active' => $request->boolean('active', true),
            ],
        );

        return $this->respond(
            new OrganizerDataSourceResource($dataSource->load('providerConnection')),
            'Fuente de datos actualizada.',
        );
    }
}
